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
        Schema::create('notification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notify_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->longText('body');
            $table->string('link')->nullable();
            $table->enum('status', ['sent', 'read'])->default('sent');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['notify_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification');
    }
};
