<?php

namespace App\Http\Controllers;

use App\Jobs\SendElectronicInvoiceJob;
use App\Jobs\SyncElectronicInvoiceStatusJob;
use App\Models\Agreement;
use App\Models\FilePayment;
use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Models\InvoiceItem;
use App\Models\Lessor;
use App\Services\CostaRicaElectronicInvoiceService;
use App\Services\Hacienda\Catalogs;
use App\Services\Hacienda\ClaveGenerator;
use App\Services\InvoicePaymentFileStorageService;
use App\Services\NotificationService;
use App\Services\TenantBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class InvoiceController extends Controller
{
    private const ALLOWED_EVIDENCE_MIMES = 'pdf,png,jpg,jpeg,webp';
    private const MAX_EVIDENCE_KB = 15360;

    public function __construct(
        protected ClaveGenerator $claveGenerator,
        private readonly NotificationService $notificationService,
        private readonly TenantBalanceService $tenantBalanceService,
        private readonly InvoicePaymentFileStorageService $fileStorage
    ) {
    }

    /**
     * Solo la tabla de facturas registradas, con filtros y paginación (la creación de
     * facturas vive en su propia vista, ver create()).
     */
    public function index(Request $request, CostaRicaElectronicInvoiceService $electronicInvoiceService)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $filters = $request->only(['search', 'hacienda_status', 'date_from', 'date_to', 'agreement_id']);

        $invoices = Invoice::with(['agreement.property', 'roomer.user', 'electronicDetail', 'items.filePayment'])
            ->where('lessor_id', $lessor->id)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('roomer', fn ($roomerQuery) => $roomerQuery->where('legal_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['agreement_id'] ?? null, fn ($query, $agreementId) => $query->where('agreement_id', $agreementId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['hacienda_status'] ?? null, function ($query, $status) {
                $query->whereHas('electronicDetail', fn ($detailQuery) => $detailQuery->where('electronic_status', $status));
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $agreementsForFilter = Agreement::with('property')
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('start_at')
            ->get();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'agreementsForFilter' => $agreementsForFilter,
            'statusOptions' => Invoice::statusOptions(),
            'haciendaStatusOptions' => $electronicInvoiceService->haciendaStatusOptions(),
            'saleConditionOptions' => Invoice::saleConditionOptions(),
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'filters' => $filters,
        ]);
    }

    /**
     * Formulario dedicado a crear una factura electrónica (líneas, CABYS, condiciones
     * comerciales, etc.) — separado de la tabla de facturas registradas.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

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

        return view('admin.invoices.create', [
            'lessor' => $lessor,
            'agreements' => $agreements,
            'referenceableInvoices' => $referenceableInvoices,
            'saleConditionOptions' => Invoice::saleConditionOptions(),
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'creditNoteReasonOptions' => Catalogs::creditNoteReasonOptions(),
            'nextInvoiceNumberPreview' => $this->nextInvoiceNumber($lessor, '01'),
        ]);
    }

    /**
     * Formulario simplificado para dejar constancia de un pago del inquilino solo en el
     * sistema (invoice_type = 'simple'): no genera XML ni se envía a Hacienda, así que no
     * pide CABYS ni tipo de documento.
     */
    public function createPaymentReceipt(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.invoices.payment-receipt', [
            'lessor' => $lessor,
            'agreements' => $agreements,
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'nextInvoiceNumberPreview' => $this->nextInvoiceNumber($lessor, '01'),
        ]);
    }

    /**
     * Reusa la misma vista de creación en modo edición — solo disponible para
     * comprobantes de pago simples y dentro de la ventana de Invoice::canEditOrDeleteReceipt().
     */
    public function edit(Request $request, int $invoiceId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $invoice = Invoice::with(['items.filePayment', 'electronicDetail'])
            ->where('lessor_id', $lessor->id)
            ->findOrFail($invoiceId);

        if (!$invoice->canEditOrDeleteReceipt()) {
            return redirect()
                ->route('admin.invoices.index')
                ->withErrors('Este comprobante ya no se puede editar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.invoices.payment-receipt', [
            'lessor' => $lessor,
            'agreements' => $agreements,
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'nextInvoiceNumberPreview' => $invoice->invoice_number,
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, int $invoiceId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $invoice = Invoice::with(['electronicDetail', 'items'])
            ->where('lessor_id', $lessor->id)
            ->findOrFail($invoiceId);

        if (!$invoice->canEditOrDeleteReceipt()) {
            return redirect()
                ->route('admin.invoices.index')
                ->withErrors('Este comprobante ya no se puede editar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'date' => ['required', 'date'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'sale_condition' => ['required', Rule::in(array_keys(Invoice::saleConditionOptions()))],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => [Rule::in(array_keys(Invoice::paymentMethodOptions()))],
            'payment_method_other_description' => [
                Rule::requiredIf(fn () => in_array('other', (array) $request->input('payment_methods', []), true)),
                'nullable', 'string', 'max:255',
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.concept' => ['required', Rule::in(array_keys(InvoiceItem::CONCEPT_OPTIONS))],
            'items.*.is_return' => ['nullable', 'boolean'],
            'items.*.evidence_file' => ['nullable', 'file', 'mimes:'.self::ALLOWED_EVIDENCE_MIMES, 'max:'.self::MAX_EVIDENCE_KB],
            'items.*.remove_evidence_file' => ['nullable', 'boolean'],
            'items.*.existing_file_payment_id' => ['nullable', 'string'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);
        $firstDescription = $validated['items'][0]['description'];

        // Se captura antes de tocar nada: como update() borra todas las líneas viejas y
        // crea nuevas (no hay id estable de línea entre un submit y otro), cualquier
        // file_payment que quedó sin ninguna línea nueva apuntándole al final se limpia
        // de R2 y de la BD (cubre reemplazo, remoción explícita, y borrar la línea entera).
        $oldFilePaymentIds = $invoice->items->pluck('file_payment_id')->filter()->values();

        DB::transaction(function () use ($invoice, $validated, $agreement, $request, $firstDescription, $oldFilePaymentIds) {
            $invoice->update([
                'agreement_id' => $agreement->id,
                'roomer_id' => $agreement->roomer_id,
                'date' => $validated['date'],
                'description' => $firstDescription,
                'currency' => $validated['currency'],
                'sale_condition' => $validated['sale_condition'],
                'payment_methods' => $validated['payment_methods'],
                'payment_method_other_description' => $validated['payment_method_other_description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by_user_id' => $request->user()->id,
            ]);

            $invoice->items()->delete();

            $keptFilePaymentIds = collect();

            foreach ($validated['items'] as $position => $itemInput) {
                $filePaymentId = null;

                if ($request->hasFile("items.{$position}.evidence_file")) {
                    $filePayment = $this->storeEvidenceFile($request->file("items.{$position}.evidence_file"), $agreement->id, $invoice->id);
                    $filePaymentId = $filePayment->id;
                } elseif (empty($itemInput['remove_evidence_file']) && !empty($itemInput['existing_file_payment_id']) && $oldFilePaymentIds->contains($itemInput['existing_file_payment_id'])) {
                    // Ni archivo nuevo ni remoción: el archivo que ya tenía la línea sobrevive,
                    // solo se reasigna a la línea nueva que la reemplaza.
                    $filePaymentId = $itemInput['existing_file_payment_id'];
                }

                if ($filePaymentId) {
                    $keptFilePaymentIds->push($filePaymentId);
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => $position + 1,
                    'file_payment_id' => $filePaymentId,
                    ...InvoiceItem::computeFromInput($itemInput),
                ]);
            }

            $invoice->recalculateTotalsFromItems();

            foreach ($oldFilePaymentIds->diff($keptFilePaymentIds) as $orphanId) {
                $orphan = FilePayment::find($orphanId);

                if ($orphan) {
                    $this->fileStorage->delete($orphan->bucket);
                    $orphan->delete();
                }
            }
        });

        $freshInvoice = $invoice->fresh(['items', 'agreement.property', 'roomer.user']);
        $this->applyConceptBalances($freshInvoice, $agreement);
        $this->notifyReceiptEvent($freshInvoice, 'updated');

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', 'Comprobante de pago actualizado correctamente.');
    }

    public function delete(Request $request, int $invoiceId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $invoice = Invoice::with(['electronicDetail', 'items.filePayment', 'agreement.property', 'roomer.user'])
            ->where('lessor_id', $lessor->id)
            ->findOrFail($invoiceId);

        if (!$invoice->canEditOrDeleteReceipt()) {
            return back()->withErrors('Este comprobante ya no se puede eliminar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        // Los file_payment no se borran solos en cascada (la FK está al revés: invoice_items
        // apunta a file_payment, no lo contrario), así que se limpian a mano antes de borrar
        // el comprobante para no dejar basura en R2 ni filas huérfanas.
        $this->deleteEvidenceFilesFor($invoice->items);

        // invoice_items se borran en cascada por FK (ver create_invoice_items_table); se
        // notifica después de borrar, usando los datos que ya están cargados en memoria.
        $invoice->delete();

        $this->notifyReceiptEvent($invoice, 'deleted');

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', 'Comprobante de pago eliminado correctamente.');
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

    /**
     * Descarga el XML firmado exactamente como se envió a Hacienda (queda guardado en
     * InvoiceElectronicDetail::xml_content al momento del envío, no es un archivo en disco).
     */
    public function downloadElectronicXml(Request $request, int $invoiceId)
    {
        $invoice = $this->resolveLessorInvoice($request, $invoiceId);

        if (!$invoice || !$invoice->electronicDetail || !$invoice->electronicDetail->xml_content) {
            return redirect()->route('admin.invoices.index')->withErrors('Todavía no hay un XML enviado para esta factura.');
        }

        $filename = 'factura-' . ($invoice->electronicDetail->hacienda_key ?: $invoice->id) . '.xml';

        return response($invoice->electronicDetail->xml_content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Descarga la respuesta cruda que Hacienda devolvió al recibir/consultar el comprobante
     * (queda guardada en InvoiceElectronicDetail::ptec_response al momento del envío/consulta).
     */
    public function downloadElectronicResponse(Request $request, int $invoiceId)
    {
        $invoice = $this->resolveLessorInvoice($request, $invoiceId);

        if (!$invoice || !$invoice->electronicDetail || !$invoice->electronicDetail->ptec_response) {
            return redirect()->route('admin.invoices.index')->withErrors('Todavía no hay una respuesta de Hacienda para esta factura.');
        }

        $filename = 'respuesta-hacienda-' . ($invoice->electronicDetail->hacienda_key ?: $invoice->id) . '.json';

        return response(
            json_encode($invoice->electronicDetail->ptec_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
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

    /**
     * Sube un archivo de evidencia de pago al bucket de R2 (files_invoice/{agreement}/{invoice}/)
     * y crea su registro en file_payment. Reutilizado por store() y update().
     */
    private function storeEvidenceFile(UploadedFile $file, int $agreementId, int $invoiceId): FilePayment
    {
        $path = $this->fileStorage->store($file, $agreementId, $invoiceId);

        return FilePayment::create([
            'name_file' => $file->getClientOriginalName(),
            'type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'weigth' => round($file->getSize() / 1024, 2),
            'bucket' => $path,
        ]);
    }

    /**
     * Borra de R2 y de la base de datos los file_payment de una lista de invoice_items ya
     * cargados (con su relación filePayment). Usado al eliminar un comprobante completo.
     */
    private function deleteEvidenceFilesFor(iterable $items): void
    {
        foreach ($items as $item) {
            if ($item->filePayment) {
                $this->fileStorage->delete($item->filePayment->bucket);
                $item->filePayment->delete();
            }
        }
    }

    /**
     * Guarda en cada línea (según su concepto) el saldo pendiente de ese concepto
     * inmediatamente después de este comprobante, usando TenantBalanceService con
     * $asOf = fecha del comprobante para que quede fiel a la vigencia del contrato en
     * ese momento. Los conceptos que no se rastrean como deuda (servicio, descuento,
     * reparación, otro) quedan en null. Solo aplica a comprobantes de pago simples.
     */
    private function applyConceptBalances(Invoice $invoice, Agreement $agreement): void
    {
        $breakdown = $this->tenantBalanceService->breakdownFor($agreement, Carbon::parse($invoice->date));

        $balanceByConcept = [
            'rent' => $breakdown['rent']['balance'],
            'deposit' => $breakdown['deposit']['balance'],
            'late_fee_rent' => $breakdown['late_fee_rent']['balance'],
            'late_fee_deposit' => $breakdown['late_fee_deposit']['balance'],
        ];

        foreach ($invoice->items as $item) {
            $item->update(['balance_pending' => $balanceByConcept[$item->concept] ?? null]);
        }
    }

    /**
     * Notifica por email al inquilino cuando se crea, edita o elimina un comprobante de
     * pago (factura simple). En creación/edición el correo trae el desglose completo de
     * líneas; en eliminación solo los datos básicos que se están perdiendo. Reutiliza
     * NotificationService::emailUsers() (App\Notifications\AppEventMail), que ya se usa
     * para el resto de correos transaccionales de la app.
     */
    private function notifyReceiptEvent(Invoice $invoice, string $action): void
    {
        $roomerUser = $invoice->roomer?->user;

        if (!$roomerUser) {
            return;
        }

        $contractLabel = trim(($invoice->agreement->contract_number ?? '') . ' - ' . ($invoice->agreement->property->name ?? ''), ' -');
        $dateLabel = optional($invoice->date)->format('d/m/Y') ?? '-';

        $subject = match ($action) {
            'created' => "Nuevo comprobante de pago #{$invoice->invoice_number}",
            'updated' => "Comprobante de pago #{$invoice->invoice_number} actualizado",
            'deleted' => "Comprobante de pago #{$invoice->invoice_number} eliminado",
        };

        if ($action === 'deleted') {
            $body = "Se eliminó el comprobante de pago N° {$invoice->invoice_number} del {$dateLabel}, "
                . "por {$invoice->currency} " . number_format((float) $invoice->total, 2)
                . ($contractLabel !== '' ? ", correspondiente al contrato {$contractLabel}." : '.') . "\n"
                . 'Si tienes dudas sobre este cambio, contacta a tu arrendador.';

            $this->notificationService->emailUsers([$roomerUser], $subject, $body);

            return;
        }

        $verb = $action === 'created' ? 'Se registró' : 'Se actualizó';

        $lines = [
            "{$verb} un comprobante de pago" . ($contractLabel !== '' ? " para tu contrato {$contractLabel}." : '.'),
            "Fecha: {$dateLabel}",
            "Número: {$invoice->invoice_number}",
            "Moneda: {$invoice->currency}",
            'Detalle:',
        ];

        foreach ($invoice->items as $item) {
            $conceptLabel = InvoiceItem::CONCEPT_OPTIONS[$item->concept] ?? 'Otro';
            if ($item->is_return) {
                $conceptLabel .= ' - retorno al inquilino';
            }
            $lines[] = "- [{$conceptLabel}] {$item->description}: {$invoice->currency} " . number_format((float) $item->line_total, 2);
        }

        $lines[] = "Total: {$invoice->currency} " . number_format((float) $invoice->total, 2);

        $paymentMethodLabels = collect($invoice->payment_methods ?? [])
            ->map(fn ($method) => Invoice::PAYMENT_METHOD_OPTIONS[$method] ?? $method)
            ->implode(', ');

        if ($paymentMethodLabels !== '') {
            $lines[] = "Métodos de pago: {$paymentMethodLabels}";
        }

        if (!empty($invoice->notes)) {
            $lines[] = "Notas: {$invoice->notes}";
        }

        $this->notificationService->emailUsers([$roomerUser], $subject, implode("\n", $lines));
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
            'payment_method_other_description' => [
                Rule::requiredIf(fn () => in_array('other', (array) $request->input('payment_methods', []), true)),
                'nullable', 'string', 'max:255',
            ],
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
            // Solo lo usa el comprobante de pago simple; las facturas electrónicas se
            // clasifican por CABYS, no por este concepto interno.
            'items.*.concept' => ['required_if:invoice_type,simple', 'nullable', Rule::in(array_keys(InvoiceItem::CONCEPT_OPTIONS))],
            'items.*.is_return' => ['nullable', 'boolean'],
            'items.*.evidence_file' => ['nullable', 'file', 'mimes:'.self::ALLOWED_EVIDENCE_MIMES, 'max:'.self::MAX_EVIDENCE_KB],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'items.*.transaction_type' => ['nullable', Rule::in(array_keys(Catalogs::transactionTypeOptions()))],
            'items.*.commercial_unit_of_measure' => ['nullable', 'string', 'max:50'],
            'items.*.item_type' => ['nullable', Rule::in(['service', 'goods'])],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_condition' => ['nullable', Rule::in(['gravado', 'exento', 'no_sujeto'])],
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
                'payment_method_other_description' => $validated['payment_method_other_description'] ?? null,
                'reference_code' => $validated['reference_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $position => $itemInput) {
                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'position' => $position + 1,
                    ...InvoiceItem::computeFromInput($itemInput),
                ]);

                if ($request->hasFile("items.{$position}.evidence_file")) {
                    $filePayment = $this->storeEvidenceFile($request->file("items.{$position}.evidence_file"), $agreement->id, $invoice->id);
                    $item->update(['file_payment_id' => $filePayment->id]);
                }
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
            $isCreditNote => 'Nota de crédito electrónica creada y enviada a Hacienda.',
            $documentType !== null => 'Factura electrónica creada y enviada a Hacienda.',
            default => 'Factura simple creada exitosamente.',
        };

        // Toda factura creada desde este formulario es electrónica: se envía a Hacienda de
        // inmediato en vez de dejarla pendiente de un clic aparte en "Enviar".
        if ($documentType) {
            try {
                SendElectronicInvoiceJob::dispatch($invoice->id);
            } catch (RuntimeException $exception) {
                // Con cola "sync" el job corre en la misma petición y puede relanzar la
                // excepción; el detalle del error ya quedó guardado en la factura por el job.
                return redirect()
                    ->route('admin.invoices.index')
                    ->with('success', 'Factura creada, pero Hacienda rechazó el envío: ' . $exception->getMessage());
            }
        } else {
            // Solo los comprobantes de pago simples calculan saldo pendiente por línea y
            // notifican al inquilino por email; las facturas electrónicas ya tienen su
            // propio seguimiento vía Hacienda.
            $freshInvoice = $invoice->fresh(['items', 'agreement.property', 'roomer.user']);
            $this->applyConceptBalances($freshInvoice, $agreement);
            $this->notifyReceiptEvent($freshInvoice, 'created');
        }

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', $message);
    }
}
