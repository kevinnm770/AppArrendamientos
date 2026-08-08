<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca una línea como el reflejo, dentro del comprobante, de una aplicación de saldo
     * a favor (registrada aparte en credit_balance_movements, ver payment_receipt_id en esa
     * tabla). Es solo para que el comprobante y el correo al inquilino muestren el
     * desglose completo; TenantBalanceService excluye estas líneas de su propia suma de
     * "pagado en efectivo" para no contar el mismo dinero dos veces (una vez aquí, otra
     * vía el movimiento de saldo a favor).
     */
    public function up(): void
    {
        Schema::table('payment_receipt_items', function (Blueprint $table) {
            $table->boolean('is_credit_application')->default(false)->after('is_return');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipt_items', function (Blueprint $table) {
            $table->dropColumn('is_credit_application');
        });
    }
};
