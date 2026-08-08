<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public const STATUS_OPTIONS = [
        'draft' => 'Borrador',
        'sent' => 'Enviada',
        'confirmed' => 'Confirmada',
        'paid' => 'Pagada',
        'overdue' => 'Vencida',
        'void' => 'Anulada',
    ];

    // Catálogo real "Condición de venta" (Nota 5, Anexos y Estructuras v4.4 de Hacienda).
    // Se excluyen a propósito los códigos 09 y 11 ("Pago de servicios prestado al Estado" /
    // "Pago de venta a crédito en IVA hasta 90 días"): el propio anexo aclara que esos dos
    // solo aplican al cancelar con un Recibo Electrónico de Pago, documento que esta app no
    // emite (solo Factura Electrónica y Nota de Crédito).
    public const SALE_CONDITION_OPTIONS = [
        'cash' => 'Contado',
        'credit' => 'Crédito',
        'consignment' => 'Consignación',
        'layaway' => 'Apartado',
        'lease_purchase_option' => 'Arrendamiento con opción de compra',
        'lease_finance_function' => 'Arrendamiento en función financiera',
        'third_party_collection' => 'Cobro a favor de un tercero',
        'state_services' => 'Servicios prestados al Estado',
        'credit_90_days' => 'Venta a crédito en IVA hasta 90 días (artículo 27, LIVA)',
        'non_nationalized_goods' => 'Venta de mercancía no nacionalizada',
        'used_goods_non_taxpayer' => 'Venta de bienes usados a no contribuyente',
        'operating_lease' => 'Arrendamiento operativo',
        'finance_lease' => 'Arrendamiento financiero',
        'service' => 'Cobro de servicio',
        'other' => 'Otros',
    ];

    public const PAYMENT_METHOD_OPTIONS = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'check' => 'Cheque',
        'transfer' => 'Transferencia - depósito bancario',
        'collection' => 'Recaudado por terceros',
        'sinpe_movil' => 'SINPE Móvil',
        'digital_platform' => 'Plataforma digital',
        'other' => 'Otros',
    ];

    protected $fillable = [
        'agreement_id',
        'reference_invoice_id',
        'credit_note_reason_code',
        'credit_note_reason_text',
        'lessor_id',
        'roomer_id',
        'invoice_number',
        'date',
        'issued_at',
        'due_date',
        'description',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_percent',
        'discount_percent',
        'discount_total',
        'tax_total',
        'late_fee_total',
        'total',
        'sale_condition',
        'payment_methods',
        'payment_method_other_description',
        'reference_code',
        'notes',
        'status',
        'tenant_confirmed_at',
        'locked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'issued_at' => 'datetime',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'late_fee_total' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'payment_methods' => 'array',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function lessor()
    {
        return $this->belongsTo(Lessor::class);
    }

    public function roomer()
    {
        return $this->belongsTo(Roomer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function electronicDetail()
    {
        return $this->hasOne(InvoiceElectronicDetail::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * Comprobante(s) de pago que liquidan esta factura, total o parcialmente por tractos
     * (vínculo opcional, ver payment_receipts.invoice_id). El saldo pendiente real —no
     * solo si tiene o no algún comprobante— se calcula en
     * AgreementController::unpaidElectronicInvoices() sumando sus totales.
     */
    public function paymentReceipts()
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    /**
     * Aplicaciones de saldo a favor registradas directamente contra esta factura (opcional,
     * al crearla) — no son un cargo de la factura ni viajan en el XML a Hacienda (ver
     * InvoiceController::store()), pero sí cuentan como "pagado" junto con paymentReceipts()
     * para saber si la factura queda saldada.
     */
    public function creditApplications()
    {
        return $this->hasMany(CreditBalanceMovement::class)->where('type', 'applied');
    }

    /**
     * Factura original que esta Nota de Crédito corrige/anula (solo aplica cuando el
     * documento electrónico es de tipo "03").
     */
    public function referenceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reference_invoice_id');
    }

    /**
     * Recalcula subtotal/descuento/impuesto/total agregados a partir de las líneas
     * ya persistidas, para que el comprobante XML (Fase 4) y esta tabla nunca diverjan.
     */
    public function recalculateTotalsFromItems(): void
    {
        $items = $this->items()->get();

        $subtotal = (float) $items->sum(fn (InvoiceItem $item) => (float) $item->subtotal + (float) $item->discount_total);
        $discountTotal = (float) $items->sum('discount_total');
        $taxTotal = (float) $items->sum('tax_total');
        // late_fee_total ya está representado como su propia línea dentro de $items
        // (ver InvoiceController::store); no se vuelve a sumar aquí para no duplicarlo.
        $total = (float) $items->sum('line_total');

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'discount_percent' => $subtotal > 0 ? round($discountTotal / $subtotal * 100, 2) : 0,
            'tax_total' => round($taxTotal, 2),
            'tax_percent' => ($subtotal - $discountTotal) > 0 ? round($taxTotal / ($subtotal - $discountTotal) * 100, 2) : 0,
            'total' => round($total, 2),
        ])->save();
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->where('due_date', '>=', now()->subYears(5));
    }

    public function canBeSentToHacienda(): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        if (!$this->electronicDetail) {
            return false;
        }

        return !empty($this->electronicDetail->hacienda_key)
            && !empty($this->electronicDetail->hacienda_consecutive);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    // Ventana durante la cual un comprobante de pago (factura simple, sin detalle
    // electrónico) se puede editar o eliminar desde el propio sistema.
    public const RECEIPT_EDIT_WINDOW_HOURS = 24;

    public function isSimpleReceipt(): bool
    {
        return $this->electronicDetail === null;
    }

    public function canEditOrDeleteReceipt(): bool
    {
        if (!$this->isSimpleReceipt() || !$this->created_at) {
            return false;
        }

        return now()->lt($this->created_at->copy()->addHours(self::RECEIPT_EDIT_WINDOW_HOURS));
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function saleConditionOptions(): array
    {
        return self::SALE_CONDITION_OPTIONS;
    }

    public static function paymentMethodOptions(): array
    {
        return self::PAYMENT_METHOD_OPTIONS;
    }
}
