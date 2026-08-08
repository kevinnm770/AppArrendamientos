<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo fuera de lo establecido por el contrato (reparación, penalización, etc.).
     * "status" solo distingue pendiente/cancelado: si ya quedó cubierto o no ("pagado")
     * se deriva siempre del saldo (TenantBalanceService), nunca se guarda a mano — en
     * este mismo proyecto Invoice.status=paid nunca se llegó a usar por guardarlo así.
     */
    public function up(): void
    {
        Schema::create('additional_charges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->foreignId('lessor_id')->constrained('lessors')->cascadeOnDelete();
            $table->foreignId('roomer_id')->constrained('roomers')->cascadeOnDelete();

            $table->enum('concept', ['repair', 'other']);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CRC');
            $table->date('charge_date');

            $table->enum('status', ['pending', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['agreement_id', 'status']);
            $table->index(['roomer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_charges');
    }
};
