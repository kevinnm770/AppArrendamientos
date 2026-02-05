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
        Schema::create('notificationslog', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('channel', ['email', 'sms', 'push', 'in_app'])->default('in_app');
            $table->string('template_key');
            $table->json('payload')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('template_key');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificationslog');
    }
};
