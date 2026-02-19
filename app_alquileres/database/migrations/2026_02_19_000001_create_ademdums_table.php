<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ademdums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->unique()->constrained('agreements')->cascadeOnDelete();

            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->longText('terms');

            $table->enum('status', ['sent', 'accepted', 'finished', 'cancelled'])->default('sent');
            $table->dateTime('tenant_confirmed_at')->nullable();
            $table->dateTime('locked_at')->nullable();

            $table->timestamps();

            $table->index(['agreement_id', 'status']);
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ademdums');
    }
};
