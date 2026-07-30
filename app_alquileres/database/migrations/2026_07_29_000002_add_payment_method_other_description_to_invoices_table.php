<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descripción libre para cuando se marca "Otros" en métodos de pago — el XSD real de
 * Hacienda tiene un campo dedicado <MedioPagoOtros> (hermano de TipoMedioPago dentro de
 * MedioPago) para esto, verificado contra el PDF oficial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method_other_description')->nullable()->after('payment_methods');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_method_other_description');
        });
    }
};
