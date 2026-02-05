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
        Schema::create('eventslog', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('entity_type'); // 'Agreement', 'Invoice', 'Property', etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('action'); // agreement_sent, invoice_locked, invoice_edit_denied, etc.
            $table->json('data')->nullable();

            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventslog');
    }
};
