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
        Schema::table('invoices', function (Blueprint $table) {
            $table->dateTime('issued_at')->nullable()->after('date');
            $table->string('currency', 3)->default('CRC')->after('description');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency');

            $table->decimal('discount_total', 12, 2)->default(0)->after('discount_percent');
            $table->decimal('tax_total', 12, 2)->default(0)->after('discount_total');
            $table->decimal('total', 12, 2)->default(0)->after('late_fee_total');

            $table->enum('sale_condition', [
                'cash',
                'credit',
                'consignment',
                'layaway',
                'service',
            ])->default('cash')->after('total');

            $table->enum('payment_method', [
                'cash',
                'card',
                'transfer',
                'check',
                'collection',
                'other',
            ])->default('transfer')->after('sale_condition');

            $table->string('reference_code')->nullable()->after('payment_method');
            $table->text('notes')->nullable()->after('reference_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'issued_at',
                'currency',
                'exchange_rate',
                'discount_total',
                'tax_total',
                'total',
                'sale_condition',
                'payment_method',
                'reference_code',
                'notes',
            ]);
        });
    }
};
