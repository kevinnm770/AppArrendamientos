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
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('activity_code', 6)->nullable();
            $table->string('economic_activity', 255)->nullable();

            $table->string('electronic_key', 50)->unique();
            $table->string('consecutive_number', 20);
            $table->string('document_type', 5)->default('01');
            $table->string('situation', 1)->default('1');

            $table->string('xml_name')->nullable();
            $table->longText('xml_signed')->nullable();

            $table->enum('hacienda_status', ['pending', 'accepted', 'rejected', 'error'])
                ->default('pending');
            $table->dateTime('sent_to_hacienda_at')->nullable();
            $table->dateTime('hacienda_response_at')->nullable();
            $table->longText('hacienda_response')->nullable();
            $table->string('hacienda_track_id')->nullable();
            $table->text('hacienda_message')->nullable();

            $table->string('email_status')->nullable();
            $table->dateTime('sent_to_client_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();

            $table->timestamps();

            $table->unique('invoice_id');
            $table->index(['hacienda_status', 'sent_to_hacienda_at']);
            $table->index('consecutive_number');
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
