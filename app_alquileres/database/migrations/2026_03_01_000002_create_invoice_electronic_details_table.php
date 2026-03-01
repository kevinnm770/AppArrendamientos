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
        Schema::create('invoice_electronic_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->unique()
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Columnas CORRECTAS
            $table->string('hacienda_key', 50)->nullable();
            $table->string('hacienda_consecutive', 50)->nullable();
            $table->string('emisor_nit', 20);
            $table->string('emisor_name', 255);
            $table->string('receptor_nit', 20);
            $table->string('receptor_name', 255);

            // ✅ Esta es la columna correcta (NO hacienda_status)
            $table->enum('electronic_status', [
                'pending', 'sent', 'accepted', 'rejected'
            ])->default('pending');

            // ✅ Este es el timestamp correcto (NO sent_to_hacienda_at)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->longText('xml_content')->nullable();
            $table->string('xml_hash', 100)->nullable();
            $table->json('ptec_response')->nullable();

            $table->timestamps();

            // ✅ ÍNDICES CON COLUMNAS CORRECTAS
            $table->index('hacienda_key', 'ied_hacienda_key_idx');
            $table->unique('hacienda_consecutive', 'ied_consecutive_unique');
            $table->index('emisor_nit', 'ied_emisor_nit_idx');
            $table->index('receptor_nit', 'ied_receptor_nit_idx');
            $table->index('electronic_status', 'ied_electronic_status_idx');

            // ✅ Índice compuesto con columnas correctas
            $table->index(['electronic_status', 'sent_at'], 'ied_status_sent_idx');
            $table->index(['emisor_nit', 'receptor_nit'], 'ied_nits_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_electronic_details');
    }
};
