<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ademdums', function (Blueprint $table) {
            $table->dateTime('update_start_date_agreement')->nullable()->after('end_at');
            $table->dateTime('update_end_date_agreement')->nullable()->after('update_start_date_agreement');
            $table->dateTime('cancelled_at')->nullable()->after('locked_at');
            $table->string('cancelled_by')->nullable()->after('cancelled_at');

            $table->index(['agreement_id', 'update_start_date_agreement', 'update_end_date_agreement'], 'ademdums_agreement_update_dates_idx');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ademdums MODIFY status ENUM('sent', 'accepted', 'finished', 'cancelled', 'canceling') NOT NULL DEFAULT 'sent'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE ademdums MODIFY status ENUM('sent', 'accepted', 'finished', 'cancelled') NOT NULL DEFAULT 'sent'");
        }

        Schema::table('ademdums', function (Blueprint $table) {
            $table->dropIndex('ademdums_agreement_update_dates_idx');
            $table->dropColumn([
                'update_start_date_agreement',
                'update_end_date_agreement',
                'cancelled_at',
                'cancelled_by',
            ]);
        });
    }
};
