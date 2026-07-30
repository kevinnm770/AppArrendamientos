<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La columna 'barrio' se creó como varchar(2) cuando se pensaba usar un código corto,
 * pero el XSD real de Hacienda lo define como texto libre (5-50 caracteres) — la
 * validación del formulario ya lo trata así, así que la columna debe ensancharse para
 * evitar el truncamiento (error real: "Data too long for column 'barrio'").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            $table->string('barrio', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            $table->string('barrio', 2)->nullable()->change();
        });
    }
};
