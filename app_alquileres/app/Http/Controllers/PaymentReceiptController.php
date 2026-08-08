<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\CreditBalanceMovement;
use App\Models\FilePayment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lessor;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptItem;
use App\Services\InvoicePaymentFileStorageService;
use App\Services\PaymentReceiptService;
use App\Services\TenantBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentReceiptController extends Controller
{
    private const ALLOWED_EVIDENCE_MIMES = 'pdf,png,jpg,jpeg,webp';
    private const MAX_EVIDENCE_KB = 15360;

    public function __construct(
        private readonly PaymentReceiptService $receiptService,
        private readonly TenantBalanceService $tenantBalanceService,
        private readonly InvoicePaymentFileStorageService $fileStorage
    ) {
    }

    /**
     * Solo la tabla de comprobantes registrados, con filtros y paginación (la creación
     * vive en su propia vista, ver create()).
     */
    public function index(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $filters = $request->only(['search', 'agreement_id', 'date_from', 'date_to']);

        $receipts = PaymentReceipt::with(['agreement.property', 'roomer.user', 'invoice', 'items.filePayment'])
            ->where('lessor_id', $lessor->id)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('roomer', fn ($roomerQuery) => $roomerQuery->where('legal_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['agreement_id'] ?? null, fn ($query, $agreementId) => $query->where('agreement_id', $agreementId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $agreementsForFilter = Agreement::with('property')
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('start_at')
            ->get();

        return view('admin.payment-receipts.index', [
            'receipts' => $receipts,
            'agreementsForFilter' => $agreementsForFilter,
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'appliableConceptOptions' => CreditBalanceMovement::appliableConceptOptions(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.payment-receipts.create', [
            'lessor' => $lessor,
            'agreements' => $agreements,
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'appliableConceptOptions' => CreditBalanceMovement::appliableConceptOptions(),
            'nextReceiptNumberPreview' => $this->receiptService->nextReceiptNumber($lessor),
        ]);
    }

    /**
     * Reusa la misma vista de creación en modo edición — solo disponible dentro de la
     * ventana de PaymentReceipt::canEditOrDelete() (24 horas desde su creación).
     */
    public function edit(Request $request, int $receiptId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $receipt = PaymentReceipt::with(['items.filePayment'])
            ->where('lessor_id', $lessor->id)
            ->findOrFail($receiptId);

        if (!$receipt->canEditOrDelete()) {
            return redirect()
                ->route('admin.payment-receipts.index')
                ->withErrors('Este comprobante ya no se puede editar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.payment-receipts.create', [
            'lessor' => $lessor,
            'agreements' => $agreements,
            'paymentMethodOptions' => Invoice::paymentMethodOptions(),
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'appliableConceptOptions' => CreditBalanceMovement::appliableConceptOptions(),
            'nextReceiptNumberPreview' => $receipt->receipt_number,
            'receipt' => $receipt,
        ]);
    }

    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $this->validateReceipt($request, $lessor);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);
        $linkedInvoice = $this->resolveLinkedInvoice($validated['invoice_id'] ?? null, $lessor, $agreement);

        if ($error = $this->checkAvailableCredit($validated, $agreement)) {
            return back()->withErrors(['amount' => $error])->withInput();
        }

        if ($error = $this->checkInvoiceRemainingBalance($linkedInvoice, $validated)) {
            return back()->withErrors(['invoice_id' => $error])->withInput();
        }

        $firstDescription = $validated['items'][0]['description'];

        $receipt = DB::transaction(function () use ($request, $validated, $agreement, $lessor, $linkedInvoice, $firstDescription) {
            $receipt = PaymentReceipt::create([
                'agreement_id' => $agreement->id,
                'invoice_id' => $linkedInvoice?->id,
                'lessor_id' => $lessor->id,
                'roomer_id' => $agreement->roomer_id,
                'receipt_number' => $this->receiptService->nextReceiptNumber($lessor),
                'date' => $validated['date'],
                'currency' => $validated['currency'],
                'payment_methods' => $validated['payment_methods'],
                'payment_method_other_description' => $validated['payment_method_other_description'] ?? null,
                'reference_code' => $validated['reference_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'total' => 0,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $position => $itemInput) {
                $item = PaymentReceiptItem::create([
                    'payment_receipt_id' => $receipt->id,
                    'position' => $position + 1,
                    ...PaymentReceiptItem::computeFromInput($itemInput),
                ]);

                if ($request->hasFile("items.{$position}.evidence_file")) {
                    $filePayment = $this->storeEvidenceFile($request->file("items.{$position}.evidence_file"), $agreement->id, $receipt->id);
                    $item->update(['file_payment_id' => $filePayment->id]);
                }

                $this->recordCreditApplicationIfNeeded($itemInput, $agreement, $lessor, $receipt, $request->user()->id);
            }

            $receipt->recalculateTotalFromItems();

            return $receipt;
        });

        $freshReceipt = $receipt->fresh(['items', 'agreement.property', 'roomer.user']);
        $this->receiptService->applyConceptBalances($freshReceipt, $agreement);
        $this->receiptService->notifyReceiptEvent($freshReceipt, 'created');

        return redirect()
            ->route('admin.payment-receipts.index')
            ->with('success', 'Comprobante de pago creado exitosamente.');
    }

    public function update(Request $request, int $receiptId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $receipt = PaymentReceipt::with('items')
            ->where('lessor_id', $lessor->id)
            ->findOrFail($receiptId);

        if (!$receipt->canEditOrDelete()) {
            return redirect()
                ->route('admin.payment-receipts.index')
                ->withErrors('Este comprobante ya no se puede editar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        $validated = $this->validateReceipt($request, $lessor);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);
        $linkedInvoice = $this->resolveLinkedInvoice($validated['invoice_id'] ?? null, $lessor, $agreement);

        if ($error = $this->checkAvailableCredit($validated, $agreement, $receipt->id)) {
            return back()->withErrors(['amount' => $error])->withInput();
        }

        if ($error = $this->checkInvoiceRemainingBalance($linkedInvoice, $validated, $receipt->id)) {
            return back()->withErrors(['invoice_id' => $error])->withInput();
        }

        $firstDescription = $validated['items'][0]['description'];

        // Se captura antes de tocar nada: como update() borra todas las líneas viejas y
        // crea nuevas (no hay id estable de línea entre un submit y otro), cualquier
        // file_payment que quedó sin ninguna línea nueva apuntándole al final se limpia
        // de R2 y de la BD (cubre reemplazo, remoción explícita, y borrar la línea entera).
        $oldFilePaymentIds = $receipt->items->pluck('file_payment_id')->filter()->values();

        DB::transaction(function () use ($receipt, $validated, $agreement, $lessor, $linkedInvoice, $request, $firstDescription, $oldFilePaymentIds) {
            $receipt->update([
                'agreement_id' => $agreement->id,
                'invoice_id' => $linkedInvoice?->id,
                'roomer_id' => $agreement->roomer_id,
                'date' => $validated['date'],
                'currency' => $validated['currency'],
                'payment_methods' => $validated['payment_methods'],
                'payment_method_other_description' => $validated['payment_method_other_description'] ?? null,
                'reference_code' => $validated['reference_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by_user_id' => $request->user()->id,
            ]);

            $receipt->items()->delete();

            // Los movimientos de saldo a favor que este mismo comprobante había generado se
            // reemplazan por los que traiga el submit actual (igual que las líneas).
            CreditBalanceMovement::where('payment_receipt_id', $receipt->id)->delete();

            $keptFilePaymentIds = collect();

            foreach ($validated['items'] as $position => $itemInput) {
                $filePaymentId = null;

                if ($request->hasFile("items.{$position}.evidence_file")) {
                    $filePayment = $this->storeEvidenceFile($request->file("items.{$position}.evidence_file"), $agreement->id, $receipt->id);
                    $filePaymentId = $filePayment->id;
                } elseif (empty($itemInput['remove_evidence_file']) && !empty($itemInput['existing_file_payment_id']) && $oldFilePaymentIds->contains($itemInput['existing_file_payment_id'])) {
                    // Ni archivo nuevo ni remoción: el archivo que ya tenía la línea sobrevive,
                    // solo se reasigna a la línea nueva que la reemplaza.
                    $filePaymentId = $itemInput['existing_file_payment_id'];
                }

                if ($filePaymentId) {
                    $keptFilePaymentIds->push($filePaymentId);
                }

                PaymentReceiptItem::create([
                    'payment_receipt_id' => $receipt->id,
                    'position' => $position + 1,
                    'file_payment_id' => $filePaymentId,
                    ...PaymentReceiptItem::computeFromInput($itemInput),
                ]);

                $this->recordCreditApplicationIfNeeded($itemInput, $agreement, $lessor, $receipt, $request->user()->id);
            }

            $receipt->recalculateTotalFromItems();

            foreach ($oldFilePaymentIds->diff($keptFilePaymentIds) as $orphanId) {
                $orphan = FilePayment::find($orphanId);

                if ($orphan) {
                    $this->fileStorage->delete($orphan->bucket);
                    $orphan->delete();
                }
            }
        });

        $freshReceipt = $receipt->fresh(['items', 'agreement.property', 'roomer.user']);
        $this->receiptService->applyConceptBalances($freshReceipt, $agreement);
        $this->receiptService->notifyReceiptEvent($freshReceipt, 'updated');

        return redirect()
            ->route('admin.payment-receipts.index')
            ->with('success', 'Comprobante de pago actualizado correctamente.');
    }

    public function delete(Request $request, int $receiptId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $receipt = PaymentReceipt::with(['items.filePayment', 'agreement.property', 'roomer.user'])
            ->where('lessor_id', $lessor->id)
            ->findOrFail($receiptId);

        if (!$receipt->canEditOrDelete()) {
            return back()->withErrors('Este comprobante ya no se puede eliminar: solo se permite dentro de las 24 horas posteriores a su creación.');
        }

        // Los file_payment no se borran solos en cascada (la FK está al revés: los items
        // apuntan a file_payment, no lo contrario), así que se limpian a mano antes de
        // borrar el comprobante para no dejar basura en R2 ni filas huérfanas.
        $this->deleteEvidenceFilesFor($receipt->items);

        // El saldo a favor que este comprobante haya aplicado se libera: si no, quedaría
        // consumido para siempre aunque el comprobante que lo aplicó ya no exista.
        CreditBalanceMovement::where('payment_receipt_id', $receipt->id)->delete();

        // Los items se borran en cascada por FK; se notifica después de borrar, usando los
        // datos que ya están cargados en memoria.
        $receipt->delete();

        $this->receiptService->notifyReceiptEvent($receipt, 'deleted');

        return redirect()
            ->route('admin.payment-receipts.index')
            ->with('success', 'Comprobante de pago eliminado correctamente.');
    }

    private function validateReceipt(Request $request, Lessor $lessor): array
    {
        return $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('lessor_id', $lessor->id)],
            'date' => ['required', 'date'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => [Rule::in(array_keys(Invoice::paymentMethodOptions()))],
            'payment_method_other_description' => [
                Rule::requiredIf(fn () => in_array('other', (array) $request->input('payment_methods', []), true)),
                'nullable', 'string', 'max:255',
            ],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.concept' => ['required', Rule::in(array_keys(InvoiceItem::CONCEPT_OPTIONS))],
            'items.*.is_return' => ['nullable', 'boolean'],
            'items.*.is_credit_application' => ['nullable', 'boolean'],
            'items.*.evidence_file' => ['nullable', 'file', 'mimes:'.self::ALLOWED_EVIDENCE_MIMES, 'max:'.self::MAX_EVIDENCE_KB],
            'items.*.remove_evidence_file' => ['nullable', 'boolean'],
            'items.*.existing_file_payment_id' => ['nullable', 'string'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Si se eligió una factura electrónica para vincular, confirma que pertenezca al
     * mismo contrato del comprobante (defensa en profundidad: la vista solo ofrece
     * facturas del contrato elegido, pero esto evita un submit manual). Una factura puede
     * tener varios comprobantes vinculados —pago por tractos—, así que aquí no se valida
     * el monto: eso lo hace checkInvoiceRemainingBalance() más abajo.
     */
    private function resolveLinkedInvoice(?int $invoiceId, Lessor $lessor, Agreement $agreement): ?Invoice
    {
        if (!$invoiceId) {
            return null;
        }

        $invoice = Invoice::where('lessor_id', $lessor->id)->findOrFail($invoiceId);

        abort_if((int) $invoice->agreement_id !== $agreement->id, 422, 'La factura elegida debe pertenecer al mismo contrato seleccionado.');

        return $invoice;
    }

    /**
     * Si el comprobante está vinculado a una factura, confirma que su total (más lo que
     * ya sumaban otros comprobantes vinculados a esa misma factura, excluyendo —al
     * editar— este mismo) no exceda el total facturado. Permite pagar una factura por
     * tractos con varios comprobantes, pero nunca de más.
     */
    private function checkInvoiceRemainingBalance(?Invoice $invoice, array $validated, ?int $excludeReceiptId = null): ?string
    {
        if (!$invoice) {
            return null;
        }

        $proposedTotal = collect($validated['items'])
            ->sum(fn ($item) => PaymentReceiptItem::computeFromInput($item)['line_total']);

        $alreadyLinkedTotal = (float) $invoice->paymentReceipts()
            ->when($excludeReceiptId, fn ($query) => $query->where('id', '!=', $excludeReceiptId))
            ->sum('total');

        // La factura también puede haber quedado parcialmente saldada con saldo a favor
        // aplicado directamente al crearla (ver InvoiceController::store()) — cuenta igual
        // que un comprobante para no dejar "sobrepagar" la factura.
        $alreadyAppliedCredit = (float) $invoice->creditApplications()->sum('amount');

        $remaining = round((float) $invoice->total - $alreadyLinkedTotal - $alreadyAppliedCredit, 2);

        if (round($proposedTotal, 2) > $remaining + 0.01) {
            return 'El total de este comprobante ('.number_format($proposedTotal, 2).') excede el saldo pendiente de la factura vinculada ('.number_format($remaining, 2).').';
        }

        return null;
    }

    /**
     * Suma cuánto de las líneas de este submit aplica saldo a favor, y confirma que no
     * exceda lo disponible a la fecha del comprobante (excluyendo, al editar, lo que este
     * mismo comprobante ya tenía aplicado). Devuelve un mensaje de error, o null si está bien.
     */
    private function checkAvailableCredit(array $validated, Agreement $agreement, ?int $excludeReceiptId = null): ?string
    {
        $requested = collect($validated['items'])
            ->filter(fn ($item) => !empty($item['is_credit_application']))
            ->sum(fn ($item) => (float) ($item['unit_price'] ?? 0));

        if ($requested <= 0) {
            return null;
        }

        $available = $this->tenantBalanceService
            ->breakdownFor($agreement, Carbon::parse($validated['date']), $excludeReceiptId)['credit_balance']['available'];

        if ($requested > $available) {
            return 'El monto de saldo a favor aplicado ('.number_format($requested, 2).') excede el disponible ('.number_format($available, 2).').';
        }

        return null;
    }

    private function recordCreditApplicationIfNeeded(array $itemInput, Agreement $agreement, Lessor $lessor, PaymentReceipt $receipt, int $userId): void
    {
        if (empty($itemInput['is_credit_application'])) {
            return;
        }

        CreditBalanceMovement::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'type' => 'applied',
            'amount' => (float) ($itemInput['unit_price'] ?? 0),
            'currency' => $receipt->currency,
            'source' => 'manual',
            'applied_to_concept' => $itemInput['concept'] ?? null,
            'payment_receipt_id' => $receipt->id,
            'created_by_user_id' => $userId,
        ]);
    }

    private function storeEvidenceFile(UploadedFile $file, int $agreementId, int $receiptId): FilePayment
    {
        $path = $this->fileStorage->store($file, $agreementId, $receiptId);

        return FilePayment::create([
            'name_file' => $file->getClientOriginalName(),
            'type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'weigth' => round($file->getSize() / 1024, 2),
            'bucket' => $path,
        ]);
    }

    private function deleteEvidenceFilesFor(iterable $items): void
    {
        foreach ($items as $item) {
            if ($item->filePayment) {
                $this->fileStorage->delete($item->filePayment->bucket);
                $item->filePayment->delete();
            }
        }
    }
}
