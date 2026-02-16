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
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            // Relación principal arrendador-arrendatario
            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();
            $table->foreignId('roomer_id')->constrained('roomers')->cascadeOnDelete();

            $table->enum('service_type', ['event', 'home', 'lodging']);

            // Fechas (date o datetime según tu lógica; aquí uso datetime para cubrir eventos/hospedaje)
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();

            $table->longText('terms'); // texto del contrato

            $table->enum('status', ['sent', 'accepted', 'finished', 'cancelled'])->default('sent');

            // Inmutabilidad tras aceptación
            $table->dateTime('tenant_confirmed_at')->nullable();
            $table->dateTime('locked_at')->nullable();

            // Datos de cancelación por mutuo acuerdo
            $table->text('canceled_by')->nullable();
            $table->dateTime('canceled_date')->nullable();

            // Auditoría mínima de quién lo creó/modificó
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['lessor_id', 'roomer_id']);
            $table->index(['property_id', 'status']);
            $table->index('start_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
