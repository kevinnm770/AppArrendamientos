<?php

namespace App\Http\Controllers;

use App\Jobs\SendElectronicInvoiceJob;
use App\Jobs\SyncElectronicInvoiceStatusJob;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Models\InvoiceItem;
use App\Models\Lessor;
use App\Services\CostaRicaElectronicInvoiceService;
use App\Services\Hacienda\Catalogs;
use App\Services\Hacienda\ClaveGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(protected ClaveGenerator $claveGenerator)
    {
    }

    public function index(Request $request, CostaRicaElectronicInvoiceService $electronicInvoiceService)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $invoices = Invoice::with(['agreement.property', 'roomer.user', 'electronicDetail', 'items'])
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        // Facturas ya enviadas a Hacienda (tienen clave), candidatas a corregir/anular con Nota de Crédito.
        $referenceableInvoices = Invoice::with('agreement')
            ->where('lessor_id', $lessor->id)
            ->whereHas('electronicDetail', fn ($query) => $query->whereNotNull('hacienda_key'))
            ->orderByDesc('date')
            ->get();

        return view('admin.invoices.index', [
            'lessor' => $lessor,
            'invoices' => $invoices,
            'agreements' => $agreements,
            'referenceableInvoices' => $referenceableInvoices,
            'statusOptions' => Invoice::statusOptions(),
            'saleConditionOptions' => Invoice::saleConditionOptions(),
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'creditNoteReasonOptions' => Catalogs::creditNoteReasonOptions(),
            'haciendaStatusOptions' => $electronicInvoiceService->haciendaStatusOptions(),
            'nextInvoiceNumberPreview' => $this->nextInvoiceNumber($lessor, '01'),
        ]);
    }

    public function sendElectronic(Request $request, int $invoiceId)
    {
        $invoice = $this->resolveLessorInvoice($request, $invoiceId);

        if (!$invoice || !$invoice->electronicDetail) {
            return redirect()->route('admin.invoices.index')->withErrors('La factura no tiene detalle electrónico.');
        }

        if (!in_array($invoice->electronicDetail->electronic_status, [InvoiceElectronicDetail::STATE_PENDING, InvoiceElectronicDetail::STATE_REJECTED, InvoiceElectronicDetail::STATE_ERROR], true)) {
            return redirect()->route('admin.invoices.index')->withErrors('La factura no se puede encolar para envío desde su estado actual.');
        }

        try {
            SendElectronicInvoiceJob::dispatch($invoice->id);
        } catch (RuntimeException $exception) {
            // Con cola "sync" el job corre en la misma petición y puede relanzar la excepción
            // (para que un worker real la registre como fallida); aquí solo importa no tumbar
            // la página — el detalle del error ya quedó guardado en la factura por el job.
            return redirect()->route('admin.invoices.index')->withErrors('Hacienda rechazó el envío: ' . $exception->getMessage());
        }

        return redirect()->route('admin.invoices.index')->with('success', 'Factura encolada para envío electrónico.');
    }

    public function retryElectronic(Request $request, int $invoiceId)
    {
        $invoice = $this->resolveLessorInvoice($request, $invoiceId);

        if (!$invoice || !$invoice->electronicDetail) {
            return redirect()->route('admin.invoices.index')->withErrors('La factura no tiene detalle electrónico.');
        }

        if (!in_array($invoice->electronicDetail->electronic_status, [InvoiceElectronicDetail::STATE_REJECTED, InvoiceElectronicDetail::STATE_ERROR], true)) {
            return redirect()->route('admin.invoices.index')->withErrors('Solo se permiten reintentos para facturas rechazadas o con error.');
        }

        try {
            SendElectronicInvoiceJob::dispatch($invoice->id, true);
        } catch (RuntimeException $exception) {
            return redirect()->route('admin.invoices.index')->withErrors('Hacienda rechazó el reintento: ' . $exception->getMessage());
        }

        return redirect()->route('admin.invoices.index')->with('success', 'Reintento de envío encolado.');
    }

    public function checkElectronicStatus(Request $request, int $invoiceId)
    {
        $invoice = $this->resolveLessorInvoice($request, $invoiceId);

        if (!$invoice || !$invoice->electronicDetail) {
            return redirect()->route('admin.invoices.index')->withErrors('La factura no tiene detalle electrónico.');
        }

        SyncElectronicInvoiceStatusJob::dispatch($invoice->id, true);

        return redirect()->route('admin.invoices.index')->with('success', 'Consulta manual de estado encolada.');
    }

    protected function resolveLessorInvoice(Request $request, int $invoiceId): ?Invoice
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return null;
        }

        return Invoice::with('electronicDetail')->where('lessor_id', $lessor->id)->find($invoiceId);
    }

    /**
     * Número de factura = el "Número de Consecutivo" oficial de Hacienda: 20 dígitos
     * (sucursal 3 + terminal 5 + tipo de documento 2 + numeración 10). Delegado a
     * ClaveGenerator::nextConsecutivo(), que también usa CostaRicaElectronicInvoiceService
     * al reintentar un envío rechazado (Hacienda no permite reenviar una clave ya recibida).
     */
    protected function nextInvoiceNumber(Lessor $lessor, string $documentType): string
    {
        $sucursal = (string) config('services.cr_einvoice.branch', '001');
        $terminal = (string) config('services.cr_einvoice.terminal', '00001');

        return $this->claveGenerator->nextConsecutivo($lessor->id, $sucursal, $terminal, $documentType);
    }

    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'invoice_type' => ['required', Rule::in(['electronic', 'simple'])],
            'document_type' => ['required_if:invoice_type,electronic', 'nullable', Rule::in(['01', '03'])],
            'reference_invoice_id' => ['required_if:document_type,03', 'nullable', Rule::exists('invoices', 'id')->where('lessor_id', $lessor->id)],
            'credit_note_reason_code' => ['required_if:document_type,03', 'nullable', Rule::in(array_keys(Catalogs::creditNoteReasonOptions()))],
            'credit_note_reason_text' => ['required_if:document_type,03', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'late_fee_total' => ['nullable', 'numeric', 'min:0'],
            'sale_condition' => ['required', Rule::in(array_keys(Invoice::saleConditionOptions()))],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => [Rule::in(array_keys(Invoice::paymentMethodOptions()))],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            // Obligatorio para facturas electrónicas (Hacienda lo exige en el XSD real; una
            // línea sin CABYS produce rechazo). Las facturas "simple" no se envían a Hacienda.
            'items.*.cabys_code' => ['required_if:invoice_type,electronic', 'nullable', 'string', 'max:13'],
            'items.*.commercial_code_type' => ['nullable', Rule::in(['01', '02', '03', '04'])],
            'items.*.commercial_code' => ['nullable', 'required_with:items.*.commercial_code_type', 'string', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'items.*.commercial_unit_of_measure' => ['nullable', 'string', 'max:50'],
            'items.*.item_type' => ['nullable', Rule::in(['service', 'goods'])],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'items.*.cabys_code.required_if' => 'Cada línea de una factura electrónica necesita un código CABYS (búscalo en el modal de la línea).',
        ]);

        $agreement = Agreement::with('roomer')
            ->where('lessor_id', $lessor->id)
            ->findOrFail((int) $validated['agreement_id']);

        $documentType = $validated['invoice_type'] === 'electronic' ? $validated['document_type'] : null;
        $isCreditNote = $documentType === '03';

        if ($isCreditNote) {
            $referenceInvoice = Invoice::where('lessor_id', $lessor->id)
                ->findOrFail((int) $validated['reference_invoice_id']);

            if ((int) $referenceInvoice->agreement_id !== $agreement->id) {
                return redirect()
                    ->route('admin.invoices.index')
                    ->withErrors(['reference_invoice_id' => 'La factura a corregir debe pertenecer al mismo contrato seleccionado.'])
                    ->withInput();
            }
        }

        $lateFeeTotal = (float) ($validated['late_fee_total'] ?? 0);
        $issuedAt = now()->setDateFrom($validated['date']);
        $firstDescription = $validated['items'][0]['description'];
        $numberDocumentType = $documentType ?: '01';

        $invoice = DB::transaction(function () use ($request, $validated, $agreement, $lessor, $lateFeeTotal, $issuedAt, $firstDescription, $numberDocumentType, $isCreditNote) {
            $invoice = Invoice::create([
                'agreement_id' => $agreement->id,
                'reference_invoice_id' => $isCreditNote ? $validated['reference_invoice_id'] : null,
                'credit_note_reason_code' => $isCreditNote ? $validated['credit_note_reason_code'] : null,
                'credit_note_reason_text' => $isCreditNote ? $validated['credit_note_reason_text'] : null,
                'lessor_id' => $lessor->id,
                'roomer_id' => $agreement->roomer_id,
                'invoice_number' => $this->nextInvoiceNumber($lessor, $numberDocumentType),
                'date' => $validated['date'],
                'issued_at' => $issuedAt,
                'due_date' => $validated['due_date'] ?? null,
                'description' => $firstDescription,
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['currency'] === 'CRC' ? 1 : ($validated['exchange_rate'] ?? null),
                'subtotal' => 0,
                'discount_percent' => 0,
                'discount_total' => 0,
                'tax_percent' => 0,
                'tax_total' => 0,
                'total' => 0,
                'late_fee_total' => $validated['late_fee_total'] ?? 0,
                'sale_condition' => $validated['sale_condition'],
                'payment_methods' => $validated['payment_methods'],
                'reference_code' => $validated['reference_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $position => $itemInput) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => $position + 1,
                    ...InvoiceItem::computeFromInput($itemInput),
                ]);
            }

            // La mora se factura como su propia línea (en vez de sumarse suelta al total)
            // para que TotalVenta/TotalComprobante del comprobante siempre cuadren con la suma de líneas.
            if ($lateFeeTotal > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => count($validated['items']) + 1,
                    ...InvoiceItem::computeFromInput([
                        'description' => 'Interés moratorio / recargo por mora',
                        'quantity' => 1,
                        'unit_price' => $lateFeeTotal,
                        'tax_rate' => 0,
                        'tax_code' => null,
                    ]),
                ]);
            }

            $invoice->recalculateTotalsFromItems();

            return $invoice;
        });

        if ($documentType) {
            $invoice->electronicDetail()->create([
                'hacienda_key' => null,
                'hacienda_consecutive' => $invoice->invoice_number,
                'sucursal' => (string) config('services.cr_einvoice.branch', '001'),
                'terminal' => (string) config('services.cr_einvoice.terminal', '00001'),
                'document_type' => $documentType,
                'internal_number' => str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT),
                'emisor_nit' => (string) ($lessor->id_number ?? ''),
                'emisor_name' => (string) ($lessor->legal_name ?? ''),
                'receptor_nit' => (string) ($agreement->roomer?->id_number ?? ''),
                'receptor_name' => (string) ($agreement->roomer?->legal_name ?? ''),
                'electronic_status' => InvoiceElectronicDetail::STATE_PENDING,
                'last_transition_message' => 'Comprobante electrónico creado y pendiente de envío a Hacienda.',
                'transition_log' => [[
                    'from' => null,
                    'to' => InvoiceElectronicDetail::STATE_PENDING,
                    'message' => 'Comprobante electrónico creado y pendiente de envío a Hacienda.',
                    'at' => now()->toIso8601String(),
                ]],
            ]);
        }

        $message = match (true) {
            $isCreditNote => 'Nota de crédito electrónica creada. La clave oficial se generará al momento del envío.',
            $documentType !== null => 'Factura electrónica creada. La clave oficial se generará al momento del envío.',
            default => 'Factura simple creada exitosamente.',
        };

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', $message);
    }
}
