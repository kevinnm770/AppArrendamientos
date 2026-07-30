<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ademdum extends Model
{
    protected $fillable = [
        'agreement_id',
        'start_at',
        'end_at',
        'frequency_pay',
        'payment_date',
        'payment_month',
        'deadline_pay',
        'amount',
        'currency',
        'deposit',
        'deadline_deposit',
        'type_sanction',
        'surcharge_delay',
        'amount_delay',
        'frequency_sanction',
        'base',
        'max_days_unlimited',
        'max_days',
        'type_sanction_deposit',
        'surcharge_delay_deposit',
        'amount_delay_deposit',
        'frequency_sanction_deposit',
        'base_deposit',
        'max_days_unlimited_deposit',
        'max_days_deposit',
        'status',
        'tenant_confirmed_at',
        'locked_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
        'deposit' => 'decimal:2',
        'deadline_deposit' => 'date',
        'surcharge_delay' => 'decimal:2',
        'amount_delay' => 'decimal:2',
        'max_days_unlimited' => 'boolean',
        'surcharge_delay_deposit' => 'decimal:2',
        'amount_delay_deposit' => 'decimal:2',
        'max_days_unlimited_deposit' => 'boolean',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function signedDoc()
    {
        return $this->hasOne(SignedDoc::class);
    }

    /**
     * Campos de negocio que este adendum sobrescribe explícitamente.
     * Los campos de pago son independientes (no nulos = modificados);
     * los de morosidad se consideran todos modificados en bloque si type_sanction no es nulo.
     */
    public function overriddenFields(): array
    {
        $independent = array_values(array_filter(
            Agreement::INDEPENDENT_FIELDS,
            fn (string $field) => $this->{$field} !== null
        ));

        $mora = $this->type_sanction !== null ? Agreement::MORA_FIELDS : [];
        $moraDeposit = $this->type_sanction_deposit !== null ? Agreement::DEPOSIT_MORA_FIELDS : [];

        return [...$independent, ...$mora, ...$moraDeposit];
    }
}
