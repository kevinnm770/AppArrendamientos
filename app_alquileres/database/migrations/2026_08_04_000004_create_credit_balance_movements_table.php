<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger de saldo a favor por contrato: "generated" (sobrepago, nota de crédito, o
     * ajuste manual) y "applied" (consumo contra un concepto pendiente). Son filas
     * inmutables (sin updated_by/edición) para que la auditoría sea confiable: para
     * corregir un movimiento se postea otro, no se edita el existente.
     */
    public function up(): void
    {
        Schema::create('credit_balance_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();
            $table->foreignId('roomer_id')->constrained('roomers')->cascadeOnDelete();

            $table->enum('type', ['generated', 'applied']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CRC');

            $table->enum('source', ['overpayment', 'credit_note', 'manual']);
            $table->string('applied_to_concept')->nullable();

            $table->foreignId('payment_receipt_id')->nullable()->constrained('payment_receipts')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->text('reason')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['agreement_id', 'type']);
            $table->index(['roomer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_balance_movements');
    }
};
