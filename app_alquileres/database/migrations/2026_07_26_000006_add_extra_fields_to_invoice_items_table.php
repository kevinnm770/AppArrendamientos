<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos opcionales adicionales del catálogo v4.4: código comercial propio del
     * arrendador (CodigoComercial), unidad de medida "comercial" en texto libre
     * (UnidadMedidaComercial), y si la línea es un servicio o una mercancía (afecta a qué
     * total de ResumenFactura se suma: TotalServ* vs TotalMerc*).
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('commercial_code_type', 2)->nullable()->after('cabys_code');
            $table->string('commercial_code', 50)->nullable()->after('commercial_code_type');
            $table->string('commercial_unit_of_measure', 50)->nullable()->after('unit_of_measure');
            $table->enum('item_type', ['service', 'goods'])->default('service')->after('commercial_unit_of_measure');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['commercial_code_type', 'commercial_code', 'commercial_unit_of_measure', 'item_type']);
        });
    }
};
