<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ademdums MODIFY end_at DATETIME NOT NULL");
        DB::statement("ALTER TABLE ademdums MODIFY type_sanction ENUM('none', 'percent', 'amount_fix') NULL");

        Schema::table('ademdums', function (Blueprint $table) {
            $table->boolean('max_days_unlimited')->nullable()->after('base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ademdums', function (Blueprint $table) {
            $table->dropColumn('max_days_unlimited');
        });

        DB::statement("ALTER TABLE ademdums MODIFY type_sanction ENUM('percent', 'amount_fix') NULL");
        DB::statement("ALTER TABLE ademdums MODIFY end_at DATETIME NULL");
    }
};
