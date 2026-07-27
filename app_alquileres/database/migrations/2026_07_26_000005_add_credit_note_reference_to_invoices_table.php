<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para Nota de Crédito Electrónica: qué factura corrige/anula y por qué
     * (catálogo de Hacienda "Código de referencia").
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('reference_invoice_id')->nullable()->after('agreement_id')
                ->constrained('invoices')->nullOnDelete();
            $table->string('credit_note_reason_code', 2)->nullable()->after('reference_invoice_id');
            $table->string('credit_note_reason_text')->nullable()->after('credit_note_reason_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_invoice_id');
            $table->dropColumn(['credit_note_reason_code', 'credit_note_reason_text']);
        });
    }
};
