<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Agreement extends Model
{
    public const FREQUENCY_PAY_OPTIONS = [
        'monthly' => 'Mensual',
        'bimonthly' => 'Bimestral',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
    ];

    public const CURRENCY_OPTIONS = [
        'CRC' => 'Colones (₡)',
        'USD' => 'Dólares estadounidenses ($)',
    ];

    public const TYPE_SANCTION_OPTIONS = [
        'none' => 'Sin sanción',
        'percent' => 'Porcentaje',
        'amount_fix' => 'Monto fijo',
    ];

    public const FREQUENCY_SANCTION_OPTIONS = [
        'daily' => 'Diaria',
        'weekly' => 'Semanal',
        'monthly' => 'Mensual',
    ];

    public const BASE_OPTIONS = [
        'original_amount' => 'Monto original',
        'balance' => 'Saldo pendiente',
    ];

    public const MONTHS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Campos de pago que un adendum puede sobrescribir de forma independiente y dispersa
     * (NULL = no lo modifica ese adendum en particular, hereda el valor vigente campo por campo).
     */
    public const INDEPENDENT_FIELDS = [
        'frequency_pay',
        'payment_date',
        'payment_month',
        'deadline_pay',
        'amount',
        'currency',
        'deposit',
    ];

    /**
     * Campos de la política de morosidad: se sobrescriben como un solo bloque
     * (si un adendum modifica la política, todos estos campos vienen de ese mismo adendum,
     * incluso si alguno queda en NULL porque no aplica para el tipo de sanción elegido).
     */
    public const MORA_FIELDS = [
        'type_sanction',
        'surcharge_delay',
        'amount_delay',
        'base',
        'frequency_sanction',
        'max_days_unlimited',
        'max_days',
    ];

    public const BUSINESS_FIELDS = [
        ...self::INDEPENDENT_FIELDS,
        ...self::MORA_FIELDS,
    ];

    protected $fillable = [
        'contract_number',
        'property_id',
        'lessor_id',
        'roomer_id',
        'service_type',
        'start_at',
        'end_at',
        'frequency_pay',
        'payment_date',
        'payment_month',
        'deadline_pay',
        'amount',
        'currency',
        'deposit',
        'type_sanction',
        'surcharge_delay',
        'amount_delay',
        'frequency_sanction',
        'base',
        'max_days_unlimited',
        'max_days',
        'status',
        'canceled_by',
        'canceled_date',
        'tenant_confirmed_at',
        'locked_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'canceled_date' => 'datetime',
        'tenant_confirmed_at' => 'datetime',
        'locked_at' => 'datetime',
        'amount' => 'decimal:2',
        'deposit' => 'decimal:2',
        'surcharge_delay' => 'decimal:2',
        'amount_delay' => 'decimal:2',
        'max_days_unlimited' => 'boolean',
    ];

    public function currencySymbol(): string
    {
        return match ($this->currency) {
            'USD' => '$',
            default => '₡',
        };
    }

    public function lessor()
    {
        return $this->belongsTo(Lessor::class);
    }

    public function roomer()
    {
        return $this->belongsTo(Roomer::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function ademdums()
    {
        return $this->hasMany(Ademdum::class)
                    ->orderBy('created_at', 'desc');
    }

    public function latestAdemdum()
    {
        return $this->hasOne(Ademdum::class)->latestOfMany('created_at');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)
                    ->orderBy('date', 'desc');
    }

    public function signedDoc()
    {
        return $this->hasOne(SignedDoc::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Resuelve los términos de negocio vigentes en una fecha dada: para cada campo,
     * toma el valor del adendum aceptado/canceling más reciente cuyo periodo cubra
     * esa fecha y que lo haya sobrescrito (no nulo); si ninguno aplica, usa el valor
     * base del contrato.
     */
    public function effectiveTerms(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::now();

        $activeAdemdums = $this->ademdums()
            ->whereIn('status', ['accepted', 'canceling'])
            ->where('start_at', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $date);
            })
            ->get();

        $resolved = [];

        foreach (self::INDEPENDENT_FIELDS as $field) {
            $value = null;

            foreach ($activeAdemdums as $ademdum) {
                if ($ademdum->{$field} !== null) {
                    $value = $ademdum->{$field};
                    break;
                }
            }

            $resolved[$field] = $value ?? $this->{$field};
        }

        // La política de morosidad se sobrescribe como un solo bloque: el adendum
        // vigente que la haya tocado (type_sanction no nulo) gana para TODOS sus campos,
        // incluso los que queden en NULL por no aplicar al tipo de sanción elegido.
        $moraSource = null;

        foreach ($activeAdemdums as $ademdum) {
            if ($ademdum->type_sanction !== null) {
                $moraSource = $ademdum;
                break;
            }
        }

        $moraSource = $moraSource ?? $this;

        foreach (self::MORA_FIELDS as $field) {
            $resolved[$field] = $moraSource->{$field};
        }

        return $resolved;
    }

    /**
     * Valida las combinaciones cruzadas de la política de morosidad que un simple
     * required_if no puede expresar (dependen de dos campos a la vez). Asume que
     * $input['type_sanction'] está presente; el llamador decide si debe invocarse.
     */
    public static function validateMoraPolicyInput(array $input): ?string
    {
        $typeSanction = $input['type_sanction'];

        if ($typeSanction === 'none') {
            return null;
        }

        if (empty($input['frequency_sanction'])) {
            return 'Debes indicar la frecuencia de aplicación del recargo.';
        }

        $maxDaysUnlimited = (bool) ($input['max_days_unlimited'] ?? false);

        if (!$maxDaysUnlimited && !isset($input['max_days'])) {
            return 'Debes indicar los días máximos de acumulación o marcar la opción "Indefinido".';
        }

        if ($typeSanction === 'percent' && (!isset($input['surcharge_delay']) || empty($input['base']))) {
            return 'Debes indicar el porcentaje de recargo y la base de cálculo.';
        }

        if ($typeSanction === 'amount_fix' && !isset($input['amount_delay'])) {
            return 'Debes indicar el monto fijo de recargo.';
        }

        return null;
    }

    /**
     * Construye los valores finales de la política de morosidad a partir de un input
     * ya validado (asume $input['type_sanction'] presente).
     */
    public static function moraPolicyValuesFromInput(array $input): array
    {
        $typeSanction = $input['type_sanction'];

        if ($typeSanction === 'none') {
            return [
                'type_sanction' => 'none',
                'surcharge_delay' => null,
                'amount_delay' => null,
                'base' => null,
                'frequency_sanction' => null,
                'max_days_unlimited' => null,
                'max_days' => null,
            ];
        }

        $maxDaysUnlimited = (bool) ($input['max_days_unlimited'] ?? false);

        return [
            'type_sanction' => $typeSanction,
            'surcharge_delay' => $typeSanction === 'percent' ? ($input['surcharge_delay'] ?? 0) : null,
            'amount_delay' => $typeSanction === 'amount_fix' ? ($input['amount_delay'] ?? 0) : null,
            'base' => $typeSanction === 'percent' ? $input['base'] : null,
            'frequency_sanction' => $input['frequency_sanction'],
            'max_days_unlimited' => $maxDaysUnlimited,
            'max_days' => $maxDaysUnlimited ? null : (int) ($input['max_days'] ?? 0),
        ];
    }
}
