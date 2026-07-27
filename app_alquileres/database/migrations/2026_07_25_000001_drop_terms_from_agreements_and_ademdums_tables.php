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
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('terms');
        });

        Schema::table('ademdums', function (Blueprint $table) {
            $table->dropColumn('terms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->longText('terms')->nullable();
        });

        Schema::table('ademdums', function (Blueprint $table) {
            $table->longText('terms')->nullable();
        });
    }
};
