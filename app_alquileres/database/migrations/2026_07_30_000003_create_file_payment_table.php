<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidencia de pago adjunta por línea de comprobante (ver invoice_items.file_payment_id).
     * Mismo esquema que "files_messages" (adjuntos de chat) — mismo patrón ya usado en el proyecto.
     */
    public function up(): void
    {
        Schema::create('file_payment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_file');
            $table->string('type', 20);
            $table->decimal('weigth', 10, 2);
            $table->string('bucket');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_payment');
    }
};
