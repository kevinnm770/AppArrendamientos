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
        Schema::table('agreements', function (Blueprint $table) {
            $table->date('deadline_deposit')->nullable()->after('deposit');

            $table->enum('type_sanction_deposit', ['none', 'percent', 'amount_fix'])->default('none')->after('deadline_deposit');
            $table->decimal('surcharge_delay_deposit', 5, 2)->nullable()->after('type_sanction_deposit');
            $table->decimal('amount_delay_deposit', 12, 2)->nullable()->after('surcharge_delay_deposit');
            $table->enum('frequency_sanction_deposit', ['daily', 'weekly', 'monthly'])->nullable()->after('amount_delay_deposit');
            $table->enum('base_deposit', ['original_amount', 'balance'])->nullable()->after('frequency_sanction_deposit');
            $table->boolean('max_days_unlimited_deposit')->nullable()->after('base_deposit');
            $table->unsignedSmallInteger('max_days_deposit')->nullable()->after('max_days_unlimited_deposit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'deadline_deposit',
                'type_sanction_deposit',
                'surcharge_delay_deposit',
                'amount_delay_deposit',
                'frequency_sanction_deposit',
                'base_deposit',
                'max_days_unlimited_deposit',
                'max_days_deposit',
            ]);
        });
    }
};
