<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hacienda permite varios medios de pago por comprobante (MedioPago es una lista en
     * el XML), así que payment_method (un solo valor) se reemplaza por payment_methods
     * (lista JSON).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('payment_methods')->nullable()->after('payment_method');
        });

        DB::table('invoices')->whereNotNull('payment_method')->orderBy('id')->each(function ($invoice) {
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['payment_methods' => json_encode([$invoice->payment_method])]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('sale_condition');
        });

        DB::table('invoices')->whereNotNull('payment_methods')->orderBy('id')->each(function ($invoice) {
            $methods = json_decode((string) $invoice->payment_methods, true) ?: [];

            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['payment_method' => $methods[0] ?? null]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
    }
};
