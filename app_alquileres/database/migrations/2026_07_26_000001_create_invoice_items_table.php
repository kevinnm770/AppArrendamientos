<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->string('cabys_code', 13)->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit_of_measure', 20)->default('Unid');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->string('tax_code', 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('position')->default(1);

            $table->timestamps();

            $table->index(['invoice_id', 'position']);
            $table->index('cabys_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
