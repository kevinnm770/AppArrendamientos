<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La morosidad del alquiler y la del depósito se calculan por separado (bases y
     * políticas distintas, ver TenantBalanceService); el concepto "late_fee" único no
     * dejaba saber cuál de las dos saldaba un pago, así que se descompone en dos.
     * No hay filas existentes con concept = 'late_fee' al momento de esta migración
     * (columna agregada el mismo día), así que no hace falta reclasificar datos.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE invoice_items MODIFY concept ENUM('rent', 'service', 'deposit', 'discount', 'late_fee_rent', 'late_fee_deposit', 'repair', 'other') NULL");

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('balance_pending', 12, 2)->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('balance_pending');
        });

        DB::statement("UPDATE invoice_items SET concept = 'late_fee' WHERE concept IN ('late_fee_rent', 'late_fee_deposit')");
        DB::statement("ALTER TABLE invoice_items MODIFY concept ENUM('rent', 'service', 'deposit', 'discount', 'late_fee', 'repair', 'other') NULL");
    }
};
