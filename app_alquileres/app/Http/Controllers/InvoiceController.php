<?php

namespace App\Http\Controllers;

use App\Jobs\SendElectronicInvoiceJob;
use App\Jobs\SyncElectronicInvoiceStatusJob;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Services\CostaRicaElectronicInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request, CostaRicaElectronicInvoiceService $electronicInvoiceService)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $invoices = Invoice::with(['agreement.property', 'roomer.user', 'electronicDetail'])
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $agreements = Agreement::with(['roomer', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'agreements' => $agreements,
            'statusOptions' => Invoice::statusOptions(),
            'saleConditionOptions' => Invoice::saleConditionOptions(),
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'haciendaStatusOptions' => $electronicInvoiceService->haciendaStatusOptions(),
            'providers' => $electronicInvoiceService->providers(),
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

        SendElectronicInvoiceJob::dispatch($invoice->id);

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

        SendElectronicInvoiceJob::dispatch($invoice->id, true);

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

    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'invoice_type' => ['required', Rule::in(['electronic', 'simple'])],
            'invoice_number' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'description' => ['required', 'string'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'late_fee_total' => ['nullable', 'numeric', 'min:0'],
            'sale_condition' => ['required', Rule::in(array_keys(Invoice::saleConditionOptions()))],
            'payment_method' => ['required', Rule::in(array_keys(Invoice::paymentMethodOptions()))],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $agreement = Agreement::with('roomer')
            ->where('lessor_id', $lessor->id)
            ->findOrFail((int) $validated['agreement_id']);

        $subtotal = (float) $validated['subtotal'];
        $taxPercent = (float) ($validated['tax_percent'] ?? 0);
        $discountPercent = (float) ($validated['discount_percent'] ?? 0);
        $lateFeeTotal = (float) ($validated['late_fee_total'] ?? 0);

        $discountTotal = round($subtotal * ($discountPercent / 100), 2);
        $taxTotal = round(($subtotal - $discountTotal) * ($taxPercent / 100), 2);
        $total = round($subtotal - $discountTotal + $taxTotal + $lateFeeTotal, 2);

        $issuedAt = now()->setDateFrom($validated['date']);

        $invoice = Invoice::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'invoice_number' => $validated['invoice_number'],
            'date' => $validated['date'],
            'issued_at' => $issuedAt,
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['description'],
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['currency'] === 'CRC' ? 1 : ($validated['exchange_rate'] ?? null),
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'late_fee_total' => $lateFeeTotal,
            'total' => $total,
            'sale_condition' => $validated['sale_condition'],
            'payment_method' => $validated['payment_method'],
            'reference_code' => $validated['reference_code'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        if ($validated['invoice_type'] === 'electronic') {
            $invoice->electronicDetail()->create([
                'hacienda_key' => null,
                'hacienda_consecutive' => null,
                'sucursal' => (string) config('services.cr_einvoice.branch', '001'),
                'terminal' => (string) config('services.cr_einvoice.terminal', '00001'),
                'document_type' => (string) config('services.cr_einvoice.document_type', '01'),
                'internal_number' => str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT),
                'emisor_nit' => (string) ($lessor->id_number ?? ''),
                'emisor_name' => (string) ($lessor->legal_name ?? ''),
                'receptor_nit' => (string) ($agreement->roomer?->id_number ?? ''),
                'receptor_name' => (string) ($agreement->roomer?->legal_name ?? ''),
                'electronic_status' => InvoiceElectronicDetail::STATE_PENDING,
                'last_transition_message' => 'Factura electrónica creada y pendiente de registro en CRLibre.',
                'transition_log' => [[
                    'from' => null,
                    'to' => InvoiceElectronicDetail::STATE_PENDING,
                    'message' => 'Factura electrónica creada y pendiente de registro en CRLibre.',
                    'at' => now()->toIso8601String(),
                ]],
            ]);
        }

        $message = $validated['invoice_type'] === 'electronic'
            ? 'Factura electrónica creada. La clave oficial se solicitará a CRLibre al momento del envío.'
            : 'Factura simple creada exitosamente.';

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', $message);
    }
}
