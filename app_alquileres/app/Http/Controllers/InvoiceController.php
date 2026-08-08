<?php

namespace App\Http\Controllers;

use App\Jobs\SendElectronicInvoiceJob;
use App\Jobs\SyncElectronicInvoiceStatusJob;
use App\Models\Agreement;
use App\Models\CreditBalanceMovement;
use App\Models\FilePayment;
use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Models\InvoiceItem;
use App\Models\Lessor;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptItem;
use App\Services\CostaRicaElectronicInvoiceService;
use App\Services\Hacienda\Catalogs;
use App\Services\Hacienda\ClaveGenerator;
use App\Services\Hacienda\InvoiceXmlBuilder;
use App\Services\InvoicePaymentFileStorageService;
use App\Services\PaymentReceiptService;
use App\Services\TenantBalanceService;
use Carbon\Carbon;
use DOMDocument;
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
        private readonly InvoicePaymentFileStorageService $fileStorage,
        private readonly PaymentReceiptService $paymentReceiptService,
        private readonly TenantBalanceService $tenantBalanceService
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
            'conceptOptions' => InvoiceItem::conceptOptions(),
            'appliableConceptOptions' => CreditBalanceMovement::appliableConceptOptions(),
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
     * Sube un archivo de evidencia de pago al bucket de R2 (files_invoice/{agreement}/{record}/)
     * y crea su registro en file_payment. Reutilizado por store() tanto para líneas de la
     * factura como para líneas del comprobante de pago que se crea junto con ella.
     */
    private function storeEvidenceFile(UploadedFile $file, int $agreementId, int $recordId): FilePayment
    {
        $path = $this->fileStorage->store($file, $agreementId, $recordId);

        return FilePayment::create([
            'name_file' => $file->getClientOriginalName(),
            'type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'weigth' => round($file->getSize() / 1024, 2),
            'bucket' => $path,
        ]);
    }

    /**
     * Suma cuánto de las líneas "Aplicar saldo a favor" del comprobante de pago (dentro del
     * modal de "ya pagó") se pide consumir, y confirma que no exceda lo disponible a la
     * fecha del comprobante. Igual que PaymentReceiptController::checkAvailableCredit().
     */
    private function checkAvailableCredit(array $receiptData, Agreement $agreement): ?string
    {
        $requested = collect($receiptData['items'] ?? [])
            ->filter(fn ($item) => !empty($item['is_credit_application']))
            ->sum(fn ($item) => (float) ($item['unit_price'] ?? 0));

        if ($requested <= 0) {
            return null;
        }

        $available = $this->tenantBalanceService
            ->breakdownFor($agreement, Carbon::parse($receiptData['date']))['credit_balance']['available'];

        if ($requested > $available) {
            return 'El monto de saldo a favor aplicado ('.number_format($requested, 2).') excede el disponible ('.number_format($available, 2).').';
        }

        return null;
    }

    /**
     * Reglas compartidas por store() (guardado real) y previewXml() (vista previa antes de
     * confirmar) — deben ser exactamente las mismas para que lo que el usuario revisa en el
     * modal de confirmación sea fiel a lo que realmente se va a guardar y enviar.
     */
    private function validateInvoicePayload(Request $request, Lessor $lessor): array
    {
        return $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'document_type' => ['required', Rule::in(['01', '03'])],
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
            'create_payment_receipt' => ['nullable', 'boolean'],
            // Datos del comprobante de pago que se crea junto con la factura cuando se marca
            // "ya pagó" — capturados en su propio modal (precargado desde las líneas de la
            // factura, pero editable), nunca derivados a ciegas de items[] en el servidor.
            'payment_receipt' => ['nullable', 'array', 'required_if:create_payment_receipt,1'],
            'payment_receipt.date' => ['nullable', 'date', 'required_if:create_payment_receipt,1'],
            'payment_receipt.currency' => ['nullable', Rule::in(['CRC', 'USD']), 'required_if:create_payment_receipt,1'],
            'payment_receipt.payment_methods' => ['nullable', 'array', 'min:1', 'required_if:create_payment_receipt,1'],
            'payment_receipt.payment_methods.*' => [Rule::in(array_keys(Invoice::paymentMethodOptions()))],
            'payment_receipt.payment_method_other_description' => [
                Rule::requiredIf(fn () => in_array('other', (array) $request->input('payment_receipt.payment_methods', []), true)),
                'nullable', 'string', 'max:255',
            ],
            'payment_receipt.notes' => ['nullable', 'string'],
            'payment_receipt.items' => ['nullable', 'array', 'min:1', 'required_if:create_payment_receipt,1'],
            'payment_receipt.items.*.description' => ['required_with:payment_receipt.items', 'string', 'max:500'],
            'payment_receipt.items.*.concept' => ['required_with:payment_receipt.items', Rule::in(array_keys(InvoiceItem::CONCEPT_OPTIONS))],
            'payment_receipt.items.*.is_return' => ['nullable', 'boolean'],
            'payment_receipt.items.*.is_credit_application' => ['nullable', 'boolean'],
            'payment_receipt.items.*.unit_price' => ['required_with:payment_receipt.items', 'numeric', 'min:0'],
            'payment_receipt.items.*.evidence_file' => ['nullable', 'file', 'mimes:'.self::ALLOWED_EVIDENCE_MIMES, 'max:'.self::MAX_EVIDENCE_KB],
            'items' => ['required', 'array', 'min:1'],
            // Hacienda lo exige en el XSD real; una línea sin CABYS produce rechazo.
            'items.*.cabys_code' => ['required', 'string', 'max:13'],
            'items.*.commercial_code_type' => ['nullable', Rule::in(['01', '02', '03', '04'])],
            'items.*.commercial_code' => ['nullable', 'required_with:items.*.commercial_code_type', 'string', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.is_return' => ['nullable', 'boolean'],
            // No es un campo de Hacienda (no entra al XML, ver InvoiceXmlBuilder), pero es de
            // uso interno obligatorio: lo lee TenantBalanceService::sumByConcept() para
            // calcular saldos, vía el concepto que cada línea hereda en PaymentReceiptItem.
            'items.*.concept' => ['required', Rule::in(array_keys(InvoiceItem::CONCEPT_OPTIONS))],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'items.*.transaction_type' => ['nullable', Rule::in(array_keys(Catalogs::transactionTypeOptions()))],
            'items.*.commercial_unit_of_measure' => ['nullable', 'string', 'max:50'],
            'items.*.item_type' => ['nullable', Rule::in(['service', 'goods'])],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_condition' => ['nullable', Rule::in(['gravado', 'exento', 'no_sujeto'])],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'items.*.cabys_code.required' => 'Cada línea de una factura electrónica necesita un código CABYS (búscalo en el modal de la línea).',
        ]);
    }

    /**
     * Arma (sin guardar nada) el XML de negocio tal como quedaría si se confirma el
     * guardado, para que el modal de "¿Confirmas guardar y enviar?" pueda mostrarlo. Usa
     * modelos en memoria (Invoice/InvoiceItem sin persistir) y un consecutivo de solo
     * lectura (ClaveGenerator::peekNextConsecutivo(), sin lock) — no reserva numeración de
     * Hacienda ni toca la base de datos; el consecutivo y la clave definitivos se asignan
     * recién al guardar de verdad (ver store() → CostaRicaElectronicInvoiceService).
     */
    public function previewXml(Request $request, InvoiceXmlBuilder $xmlBuilder)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $this->validateInvoicePayload($request, $lessor);

        $agreement = Agreement::with('roomer.user')
            ->where('lessor_id', $lessor->id)
            ->findOrFail((int) $validated['agreement_id']);

        $documentType = $validated['document_type'];
        $isCreditNote = $documentType === '03';

        $referenceInvoice = null;

        if ($isCreditNote) {
            $referenceInvoice = Invoice::with('electronicDetail')
                ->where('lessor_id', $lessor->id)
                ->findOrFail((int) $validated['reference_invoice_id']);
        }

        $lateFeeTotal = (float) ($validated['late_fee_total'] ?? 0);
        $issuedAt = now()->setDateFrom($validated['date']);

        $previewItems = collect();
        $position = 1;

        foreach ($validated['items'] as $itemInput) {
            $previewItems->push(InvoiceItem::make([...InvoiceItem::computeFromInput($itemInput), 'position' => $position++]));
        }

        if ($lateFeeTotal > 0) {
            $previewItems->push(InvoiceItem::make([...InvoiceItem::computeFromInput([
                'description' => 'Interés moratorio / recargo por mora',
                'concept' => 'late_fee_rent',
                'quantity' => 1,
                'unit_price' => $lateFeeTotal,
                'tax_rate' => 0,
                'tax_code' => null,
            ]), 'position' => $position++]));
        }

        // Mismo cálculo que Invoice::recalculateTotalsFromItems(), pero sobre las líneas en
        // memoria: esa función real consulta $this->items()->get() en base de datos, que
        // para una factura todavía no guardada no devolvería nada.
        $subtotal = (float) $previewItems->sum(fn (InvoiceItem $item) => (float) $item->subtotal + (float) $item->discount_total);
        $discountTotal = (float) $previewItems->sum('discount_total');
        $taxTotal = (float) $previewItems->sum('tax_total');
        $total = (float) $previewItems->sum('line_total');

        $invoice = Invoice::make([
            'agreement_id' => $agreement->id,
            'reference_invoice_id' => $referenceInvoice?->id,
            'credit_note_reason_code' => $isCreditNote ? ($validated['credit_note_reason_code'] ?? null) : null,
            'credit_note_reason_text' => $isCreditNote ? ($validated['credit_note_reason_text'] ?? null) : null,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'date' => $validated['date'],
            'issued_at' => $issuedAt,
            'due_date' => $validated['due_date'] ?? null,
            'description' => $validated['items'][0]['description'],
            'currency' => $validated['currency'],
            'exchange_rate' => $validated['currency'] === 'CRC' ? 1 : ($validated['exchange_rate'] ?? null),
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
            'late_fee_total' => $lateFeeTotal,
            'sale_condition' => $validated['sale_condition'],
            'payment_methods' => $validated['payment_methods'],
            'payment_method_other_description' => $validated['payment_method_other_description'] ?? null,
            'reference_code' => $validated['reference_code'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
        ]);

        $invoice->setRelation('lessor', $lessor);
        $invoice->setRelation('roomer', $agreement->roomer);
        $invoice->setRelation('items', $previewItems);
        $invoice->setRelation('electronicDetail', new InvoiceElectronicDetail(['document_type' => $documentType]));

        if ($referenceInvoice) {
            $invoice->setRelation('referenceInvoice', $referenceInvoice);
        }

        $sucursal = (string) config('services.cr_einvoice.branch', '001');
        $terminal = (string) config('services.cr_einvoice.terminal', '00001');
        $previewConsecutivo = $this->claveGenerator->peekNextConsecutivo($lessor->id, $sucursal, $terminal, $documentType);
        $previewClave = $this->claveGenerator->clave((string) $lessor->id_number, $previewConsecutivo, $issuedAt);

        try {
            $xml = $xmlBuilder->build($invoice, $previewClave, $previewConsecutivo);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Solo para lectura humana en el modal; el XML real que se firma y envía a Hacienda
        // no lleva este formateo (ver InvoiceXmlBuilder::build(), formatOutput = false).
        $formatted = new DOMDocument('1.0', 'UTF-8');
        $formatted->preserveWhiteSpace = false;
        $formatted->formatOutput = true;
        $formatted->loadXML($xml);

        return response()->json([
            'xml' => $formatted->saveXML(),
            'preview_consecutivo' => $previewConsecutivo,
        ]);
    }

    /**
     * Crea una factura electrónica (Factura 01) o una Nota de Crédito electrónica (03).
     * Los comprobantes de pago simples se crean desde PaymentReceiptController.
     */
    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $this->validateInvoicePayload($request, $lessor);

        $agreement = Agreement::with('roomer')
            ->where('lessor_id', $lessor->id)
            ->findOrFail((int) $validated['agreement_id']);

        $documentType = $validated['document_type'];
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

        if (!empty($validated['create_payment_receipt']) && !$isCreditNote) {
            if ($error = $this->checkAvailableCredit($validated['payment_receipt'], $agreement)) {
                return back()->withErrors(['payment_receipt' => $error])->withInput();
            }
        }

        $lateFeeTotal = (float) ($validated['late_fee_total'] ?? 0);
        $issuedAt = now()->setDateFrom($validated['date']);
        $firstDescription = $validated['items'][0]['description'];

        $invoice = DB::transaction(function () use ($request, $validated, $agreement, $lessor, $lateFeeTotal, $issuedAt, $firstDescription, $documentType, $isCreditNote) {
            $invoice = Invoice::create([
                'agreement_id' => $agreement->id,
                'reference_invoice_id' => $isCreditNote ? $validated['reference_invoice_id'] : null,
                'credit_note_reason_code' => $isCreditNote ? $validated['credit_note_reason_code'] : null,
                'credit_note_reason_text' => $isCreditNote ? $validated['credit_note_reason_text'] : null,
                'lessor_id' => $lessor->id,
                'roomer_id' => $agreement->roomer_id,
                'invoice_number' => $this->nextInvoiceNumber($lessor, $documentType),
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
                        'concept' => 'late_fee_rent',
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

        $message = $isCreditNote
            ? 'Nota de crédito electrónica creada y enviada a Hacienda.'
            : 'Factura electrónica creada y enviada a Hacienda.';

        try {
            SendElectronicInvoiceJob::dispatch($invoice->id);
        } catch (RuntimeException $exception) {
            // Con cola "sync" el job corre en la misma petición y puede relanzar la
            // excepción; el detalle del error ya quedó guardado en la factura por el job.
            return redirect()
                ->route('admin.invoices.index')
                ->with('success', 'Factura creada, pero Hacienda rechazó el envío: ' . $exception->getMessage());
        }

        // "Ya fue pagada": genera un comprobante de pago vinculado a esta factura, con los
        // datos capturados en el modal de "ya pagó" (precargado desde las líneas de la
        // factura, pero editable) — ver payment_receipt.* en validateInvoicePayload(). No
        // aplica a Notas de Crédito (no representan un cobro nuevo que se pueda "pagar").
        if (!empty($validated['create_payment_receipt']) && !$isCreditNote) {
            $receiptData = $validated['payment_receipt'];

            $receipt = DB::transaction(function () use ($request, $receiptData, $agreement, $lessor, $invoice) {
                $receipt = PaymentReceipt::create([
                    'agreement_id' => $agreement->id,
                    'invoice_id' => $invoice->id,
                    'lessor_id' => $lessor->id,
                    'roomer_id' => $agreement->roomer_id,
                    'receipt_number' => $this->paymentReceiptService->nextReceiptNumber($lessor),
                    'date' => $receiptData['date'],
                    'currency' => $receiptData['currency'],
                    'payment_methods' => $receiptData['payment_methods'],
                    'payment_method_other_description' => $receiptData['payment_method_other_description'] ?? null,
                    'notes' => $receiptData['notes'] ?? null,
                    'total' => 0,
                    'created_by_user_id' => $request->user()->id,
                    'updated_by_user_id' => $request->user()->id,
                ]);

                foreach ($receiptData['items'] as $position => $itemInput) {
                    $item = PaymentReceiptItem::create([
                        'payment_receipt_id' => $receipt->id,
                        'position' => $position + 1,
                        ...PaymentReceiptItem::computeFromInput($itemInput),
                    ]);

                    if ($request->hasFile("payment_receipt.items.{$position}.evidence_file")) {
                        $filePayment = $this->storeEvidenceFile($request->file("payment_receipt.items.{$position}.evidence_file"), $agreement->id, $receipt->id);
                        $item->update(['file_payment_id' => $filePayment->id]);
                    }

                    if (!empty($itemInput['is_credit_application'])) {
                        CreditBalanceMovement::create([
                            'agreement_id' => $agreement->id,
                            'lessor_id' => $lessor->id,
                            'roomer_id' => $agreement->roomer_id,
                            'type' => 'applied',
                            'amount' => (float) ($itemInput['unit_price'] ?? 0),
                            'currency' => $receiptData['currency'],
                            'source' => 'manual',
                            'applied_to_concept' => $itemInput['concept'] ?? null,
                            'payment_receipt_id' => $receipt->id,
                            'created_by_user_id' => $request->user()->id,
                        ]);
                    }
                }

                $receipt->recalculateTotalFromItems();

                return $receipt;
            });

            $freshReceipt = $receipt->fresh(['items', 'agreement.property', 'roomer.user']);
            $this->paymentReceiptService->applyConceptBalances($freshReceipt, $agreement);
            $this->paymentReceiptService->notifyReceiptEvent($freshReceipt, 'created');

            $message .= ' Se generó además el comprobante de pago '.$receipt->receipt_number.'.';
        }

        return redirect()
            ->route('admin.invoices.index')
            ->with('success', $message);
    }
}
