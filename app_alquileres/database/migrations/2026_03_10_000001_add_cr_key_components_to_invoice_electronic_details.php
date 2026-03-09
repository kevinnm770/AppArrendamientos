<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_electronic_details', 'sucursal')) {
                $table->string('sucursal', 3)->nullable()->after('hacienda_consecutive');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'terminal')) {
                $table->string('terminal', 5)->nullable()->after('sucursal');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'document_type')) {
                $table->string('document_type', 2)->nullable()->after('terminal');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'internal_number')) {
                $table->string('internal_number', 10)->nullable()->after('document_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            foreach (['internal_number', 'document_type', 'terminal', 'sucursal'] as $column) {
                if (Schema::hasColumn('invoice_electronic_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
