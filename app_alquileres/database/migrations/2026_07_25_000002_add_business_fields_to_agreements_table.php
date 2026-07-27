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
            $table->string('contract_number')->unique()->after('id');

            $table->enum('frequency_pay', ['monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual'])->after('service_type');
            $table->unsignedTinyInteger('payment_date')->after('frequency_pay');
            $table->unsignedTinyInteger('payment_month')->nullable()->after('payment_date');
            $table->unsignedSmallInteger('deadline_pay')->after('payment_month');
            $table->decimal('amount', 12, 2)->after('deadline_pay');
            $table->enum('currency', ['CRC', 'USD'])->default('CRC')->after('amount');
            $table->decimal('deposit', 12, 2)->nullable()->after('currency');

            $table->enum('type_sanction', ['percent', 'amount_fix'])->after('deposit');
            $table->decimal('surcharge_delay', 5, 2)->nullable()->after('type_sanction');
            $table->decimal('amount_delay', 12, 2)->nullable()->after('surcharge_delay');
            $table->enum('frequency_sanction', ['daily', 'weekly', 'monthly'])->after('amount_delay');
            $table->enum('base', ['original_amount', 'balance'])->nullable()->after('frequency_sanction');
            $table->unsignedSmallInteger('max_days')->after('base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'contract_number',
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
        });
    }
};
