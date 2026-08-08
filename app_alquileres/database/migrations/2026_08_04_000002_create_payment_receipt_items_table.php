<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas del comprobante de pago. Sin campos de Hacienda (cabys/impuestos): el
     * formulario de comprobante simple nunca los usó (ver invoice_items, donde esos
     * campos siempre quedaban vacíos para este flujo).
     */
    public function up(): void
    {
        Schema::create('payment_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_receipt_id')->constrained('payment_receipts')->cascadeOnDelete();

            $table->enum('concept', ['rent', 'service', 'deposit', 'discount', 'late_fee_rent', 'late_fee_deposit', 'repair', 'other']);
            $table->boolean('is_return')->default(false);
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->decimal('balance_pending', 12, 2)->nullable();

            $table->foreignUuid('file_payment_id')->nullable()->constrained('file_payment')->nullOnDelete();

            $table->unsignedSmallInteger('position')->default(1);

            $table->timestamps();

            $table->index(['payment_receipt_id', 'position']);
            $table->index('concept');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipt_items');
    }
};
