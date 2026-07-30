<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de ubicaciones de Costa Rica (provincia/cantón/distrito) para los selects
     * encadenados del perfil del arrendador. Los códigos coinciden con los que exige el XSD
     * de Hacienda (Provincia 1 dígito, Cantón 2 dígitos, Distrito 2 dígitos).
     *
     * Solo se siembran las 7 provincias (universalmente conocidas y estables). Cantones y
     * distritos quedan vacíos a propósito — deben cargarse con el catálogo oficial real vía
     * `php artisan locations:import`, para no arriesgar datos geográficos incorrectos.
     */
    public function up(): void
    {
        Schema::create('cr_provinces', function (Blueprint $table) {
            $table->string('code', 1)->primary();
            $table->string('name', 50);
        });

        Schema::create('cr_cantons', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 1);
            $table->string('code', 2);
            $table->string('name', 80);

            $table->foreign('province_code')->references('code')->on('cr_provinces')->cascadeOnDelete();
            $table->unique(['province_code', 'code']);
        });

        Schema::create('cr_districts', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 1);
            $table->string('canton_code', 2);
            $table->string('code', 2);
            $table->string('name', 80);

            $table->foreign(['province_code', 'canton_code'])
                ->references(['province_code', 'code'])
                ->on('cr_cantons')
                ->cascadeOnDelete();
            $table->unique(['province_code', 'canton_code', 'code']);
        });

        DB::table('cr_provinces')->insert([
            ['code' => '1', 'name' => 'San José'],
            ['code' => '2', 'name' => 'Alajuela'],
            ['code' => '3', 'name' => 'Cartago'],
            ['code' => '4', 'name' => 'Heredia'],
            ['code' => '5', 'name' => 'Guanacaste'],
            ['code' => '6', 'name' => 'Puntarenas'],
            ['code' => '7', 'name' => 'Limón'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cr_districts');
        Schema::dropIfExists('cr_cantons');
        Schema::dropIfExists('cr_provinces');
    }
};
