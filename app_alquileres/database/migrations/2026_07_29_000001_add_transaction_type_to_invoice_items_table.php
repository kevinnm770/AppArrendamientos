<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campo "Tipo de Transacciones" (Nota 22, Anexos y Estructuras v4.4 de Hacienda,
 * verificado contra el PDF oficial) — se ubica en LineaDetalle entre UnidadMedida y
 * UnidadMedidaComercial. Es opcional ("se verificará cuando corresponda"), distinto del
 * campo interno item_type (servicio/mercancía) que ya se usa para el desglose del resumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('transaction_type', 2)->nullable()->after('unit_of_measure');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('transaction_type');
        });
    }
};
