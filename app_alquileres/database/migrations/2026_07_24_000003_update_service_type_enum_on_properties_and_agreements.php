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
        DB::statement("UPDATE properties SET service_type = 'commercial' WHERE service_type IN ('event', 'lodging')");
        DB::statement("ALTER TABLE properties MODIFY service_type ENUM('home', 'commercial') NOT NULL");

        DB::statement("UPDATE agreements SET service_type = 'commercial' WHERE service_type IN ('event', 'lodging')");
        DB::statement("ALTER TABLE agreements MODIFY service_type ENUM('home', 'commercial') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE properties MODIFY service_type ENUM('event', 'home', 'lodging') NOT NULL");
        DB::statement("ALTER TABLE agreements MODIFY service_type ENUM('event', 'home', 'lodging') NOT NULL");
    }
};
