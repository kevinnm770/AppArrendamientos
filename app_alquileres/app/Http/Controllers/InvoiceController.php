<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Invoice;
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

        $invoices = Invoice::with(['agreement.property', 'roomer', 'electronicDetail'])
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

    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
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
            'activity_code' => ['nullable', 'string', 'max:6'],
            'economic_activity' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'size:2'],
            'situation' => ['required', 'string', 'size:1'],
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

        $invoice = Invoice::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'invoice_number' => $validated['invoice_number'],
            'date' => $validated['date'],
            'issued_at' => now(),
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['description'],
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['exchange_rate'] ?? null,
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

        $invoice->electronicDetail()->create([
            'activity_code' => $validated['activity_code'] ?? null,
            'economic_activity' => $validated['economic_activity'] ?? null,
            'electronic_key' => Invoice::generateElectronicKey(),
            'consecutive_number' => Invoice::generateConsecutiveNumber($lessor->id, (int) $invoice->id),
            'document_type' => $validated['document_type'],
            'situation' => $validated['situation'],
            'hacienda_status' => 'pending',
        ]);

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', 'Factura creada con datos de factura electrónica de Costa Rica.');
    }
}
