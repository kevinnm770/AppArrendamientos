<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clasifica cada línea para poder calcular saldos pendientes por concepto
     * (alquiler, depósito, morosidad, etc.). Nullable porque las líneas de facturas
     * electrónicas (create.blade.php) no lo usan, solo el comprobante de pago simple.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->enum('concept', ['rent', 'service', 'deposit', 'discount', 'late_fee', 'repair', 'other'])
                ->nullable()
                ->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('concept');
        });
    }
};
