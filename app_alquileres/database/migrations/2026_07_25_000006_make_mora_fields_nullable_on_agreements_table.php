<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE agreements MODIFY frequency_sanction ENUM('daily', 'weekly', 'monthly') NULL");
        DB::statement("ALTER TABLE agreements MODIFY max_days_unlimited TINYINT(1) NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE agreements MODIFY max_days_unlimited TINYINT(1) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE agreements MODIFY frequency_sanction ENUM('daily', 'weekly', 'monthly') NOT NULL");
    }
};
