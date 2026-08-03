<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca una línea como devolución de dinero al inquilino (p. ej. reembolso de
     * depósito o de un pago de más). InvoiceItem::computeFromInput() la trata igual que
     * "discount": guarda el monto en negativo, así que TenantBalanceService la resta
     * sola de lo "pagado" del concepto correspondiente sin lógica extra.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('is_return')->default(false)->after('concept');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('is_return');
        });
    }
};
