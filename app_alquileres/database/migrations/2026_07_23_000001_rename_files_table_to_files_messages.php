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
        Schema::rename('files', 'files_messages');

        Schema::table('files_messages', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('bucket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files_messages', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });

        Schema::rename('files_messages', 'files');
    }
};
