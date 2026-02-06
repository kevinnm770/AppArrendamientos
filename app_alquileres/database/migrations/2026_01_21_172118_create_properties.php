<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Ubicación
            $table->string('location_text');
            $table->enum('location_province', ['Cartago', 'San José', 'Alajuela', 'Heredia', 'Puntarenas', 'Limón', 'Guanacaste']);
            $table->string('location_canton');
            $table->string('location_district');

            // Servicio
            $table->enum('service_type', ['event', 'home', 'lodging']);

            // Conteos (enteros)
            $table->unsignedSmallInteger('rooms')->default(0);
            $table->unsignedSmallInteger('living_rooms')->default(0);
            $table->unsignedSmallInteger('kitchens')->default(0);
            $table->unsignedSmallInteger('bathrooms')->default(0);
            $table->unsignedSmallInteger('yards')->default(0);
            $table->unsignedSmallInteger('garages_capacity')->default(0); // vehículos

            // Listas opcionales
            $table->json('included_objects')->nullable();
            $table->json('materials')->nullable();

            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');

            $table->timestamps();

            $table->index(['lessor_id', 'service_type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
