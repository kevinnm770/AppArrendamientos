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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();

            // Denormalizado para facilitar filtros por arrendador y unicidad por arrendador si deseas
            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();
            $table->foreignId('roomer_id')->constrained('roomers')->cascadeOnDelete();

            $table->string('invoice_number');
            $table->date('date'); // fecha emisión
            $table->date('due_date')->nullable(); // recomendado

            $table->text('description'); // obligatorio

            $table->decimal('subtotal', 12, 2);

            // Porcentajes (0-100)
            $table->decimal('tax_percent', 5, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();

            $table->decimal('late_fee_total', 12, 2)->nullable();

            $table->enum('status', ['draft', 'sent', 'confirmed', 'paid', 'overdue', 'void'])->default('draft');

            // Inmutabilidad tras confirmación
            $table->dateTime('tenant_confirmed_at')->nullable();
            $table->dateTime('locked_at')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unicidad razonable: número por arrendador
            $table->unique(['lessor_id', 'invoice_number']);

            $table->index(['agreement_id', 'status']);
            $table->index(['roomer_id', 'status']);
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
