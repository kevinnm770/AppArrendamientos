<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costa Rica distingue tres condiciones de IVA por línea: Gravado, Exento y No Sujeto
     * (esta última es una categoría legal distinta, no una tarifa de 0% — rechazo real de
     * Hacienda al reportar una línea "No Sujeta" dentro del total de "Exentos").
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->enum('tax_condition', ['gravado', 'exento', 'no_sujeto'])->default('gravado')->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_condition');
        });
    }
};
