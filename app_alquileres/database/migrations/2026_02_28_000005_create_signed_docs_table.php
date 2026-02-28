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
        Schema::create('signedDocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->nullable()->unique()->constrained('agreements')->cascadeOnDelete();
            $table->foreignId('ademdum_id')->nullable()->unique()->constrained('ademdums')->cascadeOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('compressed_size_bytes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signedDocs');
    }
};
