<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roomers', function (Blueprint $table) {
            $table->string('identification_type', 10)->default('fisico')->after('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('roomers', function (Blueprint $table) {
            $table->dropColumn('identification_type');
        });
    }
};
