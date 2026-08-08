<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo opcional a la factura electrónica que este comprobante liquida (si el
     * arrendador la marcó como "ya pagada" al crearla, o si el usuario la eligió al
     * registrar el comprobante). No es único: nada impide varios comprobantes parciales
     * contra la misma factura. Una factura sin ningún comprobante vinculado se considera
     * "no pagada" (ver AgreementController::unpaidElectronicInvoices()).
     */
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('agreement_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
