<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El Receptor de la factura electrónica no llevaba Ubicacion en el XML porque el inquilino
 * no tenía dónde guardar su dirección fiscal. Mismos códigos que lessors (Provincia 1 dígito,
 * Cantón 2 dígitos, Distrito 2 dígitos) para reusar el mismo catálogo cr_provinces/cr_cantons/
 * cr_districts y los mismos selects encadenados del perfil de arrendador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roomers', function (Blueprint $table) {
            $table->string('province', 2)->nullable()->after('phone');
            $table->string('canton', 2)->nullable()->after('province');
            $table->string('district', 2)->nullable()->after('canton');
            $table->string('barrio', 50)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('roomers', function (Blueprint $table) {
            $table->dropColumn(['province', 'canton', 'district', 'barrio']);
        });
    }
};
