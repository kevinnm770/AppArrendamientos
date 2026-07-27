<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabys_codes', function (Blueprint $table) {
            $table->string('code', 13)->primary();
            $table->string('description', 500);
            $table->decimal('tax_rate', 5, 2)->default(13);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabys_codes');
    }
};
