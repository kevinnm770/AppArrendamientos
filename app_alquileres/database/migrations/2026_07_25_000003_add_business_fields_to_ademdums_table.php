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
        Schema::table('ademdums', function (Blueprint $table) {
            $table->dropColumn(['update_start_date_agreement', 'update_end_date_agreement']);

            $table->enum('frequency_pay', ['monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual'])->nullable()->after('end_at');
            $table->unsignedTinyInteger('payment_date')->nullable()->after('frequency_pay');
            $table->unsignedTinyInteger('payment_month')->nullable()->after('payment_date');
            $table->unsignedSmallInteger('deadline_pay')->nullable()->after('payment_month');
            $table->decimal('amount', 12, 2)->nullable()->after('deadline_pay');
            $table->enum('currency', ['CRC', 'USD'])->nullable()->after('amount');
            $table->decimal('deposit', 12, 2)->nullable()->after('currency');

            $table->enum('type_sanction', ['percent', 'amount_fix'])->nullable()->after('deposit');
            $table->decimal('surcharge_delay', 5, 2)->nullable()->after('type_sanction');
            $table->decimal('amount_delay', 12, 2)->nullable()->after('surcharge_delay');
            $table->enum('frequency_sanction', ['daily', 'weekly', 'monthly'])->nullable()->after('amount_delay');
            $table->enum('base', ['original_amount', 'balance'])->nullable()->after('frequency_sanction');
            $table->unsignedSmallInteger('max_days')->nullable()->after('base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ademdums', function (Blueprint $table) {
            $table->dropColumn([
                'frequency_pay',
                'payment_date',
                'payment_month',
                'deadline_pay',
                'amount',
                'currency',
                'deposit',
                'type_sanction',
                'surcharge_delay',
                'amount_delay',
                'frequency_sanction',
                'base',
                'max_days',
            ]);

            $table->dateTime('update_start_date_agreement')->nullable();
            $table->dateTime('update_end_date_agreement')->nullable();
        });
    }
};
