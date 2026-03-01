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
        Schema::table('notification', function (Blueprint $table) {
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium')->after('body');
            $table->longText('body')->nullable()->change();
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
            $table->longText('body')->nullable(false)->change();
        });
    }
};
