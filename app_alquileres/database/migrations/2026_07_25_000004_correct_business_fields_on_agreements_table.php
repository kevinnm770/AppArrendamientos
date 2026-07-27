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
        DB::statement("ALTER TABLE agreements MODIFY end_at DATETIME NOT NULL");
        DB::statement("ALTER TABLE agreements MODIFY deposit DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE agreements MODIFY type_sanction ENUM('none', 'percent', 'amount_fix') NOT NULL");
        DB::statement("ALTER TABLE agreements MODIFY max_days SMALLINT UNSIGNED NULL");

        Schema::table('agreements', function (Blueprint $table) {
            $table->boolean('max_days_unlimited')->default(false)->after('base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('max_days_unlimited');
        });

        DB::statement("ALTER TABLE agreements MODIFY max_days SMALLINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE agreements MODIFY type_sanction ENUM('percent', 'amount_fix') NOT NULL");
        DB::statement("ALTER TABLE agreements MODIFY deposit DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE agreements MODIFY end_at DATETIME NULL");
    }
};
