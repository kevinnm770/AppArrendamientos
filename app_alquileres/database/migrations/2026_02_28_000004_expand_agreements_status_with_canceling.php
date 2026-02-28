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
        DB::statement("ALTER TABLE agreements MODIFY status ENUM('sent', 'accepted', 'finished', 'cancelled', 'canceling') NOT NULL DEFAULT 'sent'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE agreements SET status = 'accepted' WHERE status = 'canceling'");
        DB::statement("ALTER TABLE agreements MODIFY status ENUM('sent', 'accepted', 'finished', 'cancelled') NOT NULL DEFAULT 'sent'");
    }
};
