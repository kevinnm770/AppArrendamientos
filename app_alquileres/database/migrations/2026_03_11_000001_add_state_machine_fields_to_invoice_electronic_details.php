<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_electronic_details', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('electronic_status');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'status_checked_at')) {
                $table->timestamp('status_checked_at')->nullable()->after('rejected_at');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'error_at')) {
                $table->timestamp('error_at')->nullable()->after('status_checked_at');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'last_transition_message')) {
                $table->text('last_transition_message')->nullable()->after('error_at');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'transition_log')) {
                $table->json('transition_log')->nullable()->after('last_transition_message');
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE invoice_electronic_details MODIFY electronic_status ENUM('pending','queued','sent','accepted','rejected','error') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            foreach (['transition_log', 'last_transition_message', 'error_at', 'status_checked_at', 'queued_at'] as $column) {
                if (Schema::hasColumn('invoice_electronic_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoice_electronic_details MODIFY electronic_status ENUM('pending','sent','accepted','rejected') NOT NULL DEFAULT 'pending'");
        }
    }
};
