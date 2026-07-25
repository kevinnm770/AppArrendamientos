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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email');
        });

        DB::table('users')->whereIn('id', function ($query) {
            $query->select('user_id')->from('lessors');
        })->update(['role' => 'lessor']);

        DB::table('users')->whereIn('id', function ($query) {
            $query->select('user_id')->from('roomers');
        })->update(['role' => 'roomer']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'role']);
            $table->unique('email');
            $table->dropColumn('role');
        });
    }
};
