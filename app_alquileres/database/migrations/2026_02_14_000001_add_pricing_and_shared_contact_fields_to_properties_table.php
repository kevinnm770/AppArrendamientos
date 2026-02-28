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
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->after('materials');
            $table->enum('price_mode', ['perHour', 'perDay', 'perMonth'])->default('perMonth')->after('price');
            $table->boolean('isSharedPhone')->default(false)->after('price_mode');
            $table->boolean('isSharedEmail')->default(false)->after('isSharedPhone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['price', 'price_mode', 'isSharedPhone', 'isSharedEmail']);
        });
    }
};
