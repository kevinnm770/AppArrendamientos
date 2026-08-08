<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobante de pago: evidencia de dinero ya recibido, independiente de la
     * Factura electrónica (tabla invoices). Numeración propia (receipt_number),
     * no comparte secuencia con los consecutivos de Hacienda.
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();
            $table->foreignId('roomer_id')->constrained('roomers')->cascadeOnDelete();

            $table->string('receipt_number');
            $table->date('date');

            $table->string('currency', 3)->default('CRC');
            $table->json('payment_methods');
            $table->string('payment_method_other_description')->nullable();

            $table->string('reference_code')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('total', 12, 2)->default(0);

            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['lessor_id', 'receipt_number']);
            $table->index(['agreement_id', 'date']);
            $table->index(['roomer_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
