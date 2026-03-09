<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_electronic_details', 'request_id')) {
                $table->string('request_id', 120)->nullable()->after('xml_hash');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'error_code')) {
                $table->string('error_code', 100)->nullable()->after('request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_electronic_details', 'error_code')) {
                $table->dropColumn('error_code');
            }

            if (Schema::hasColumn('invoice_electronic_details', 'request_id')) {
                $table->dropColumn('request_id');
            }
        });
    }
};
