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
        if (!Schema::hasTable('invoice_electronic_details')) {
            return;
        }

        Schema::table('invoice_electronic_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_electronic_details', 'hacienda_key')) {
                $table->string('hacienda_key', 50)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'hacienda_consecutive')) {
                $table->string('hacienda_consecutive', 50)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'emisor_nit')) {
                $table->string('emisor_nit', 20)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'emisor_name')) {
                $table->string('emisor_name', 255)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'receptor_nit')) {
                $table->string('receptor_nit', 20)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'receptor_name')) {
                $table->string('receptor_name', 255)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'electronic_status')) {
                $table->enum('electronic_status', ['pending', 'sent', 'accepted', 'rejected'])->default('pending');
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'xml_content')) {
                $table->longText('xml_content')->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'xml_hash')) {
                $table->string('xml_hash', 100)->nullable();
            }

            if (!Schema::hasColumn('invoice_electronic_details', 'ptec_response')) {
                $table->json('ptec_response')->nullable();
            }
        });

        if (Schema::hasColumn('invoice_electronic_details', 'electronic_key')) {
            DB::statement('UPDATE invoice_electronic_details SET hacienda_key = COALESCE(hacienda_key, electronic_key)');
        }

        if (Schema::hasColumn('invoice_electronic_details', 'consecutive_number')) {
            DB::statement('UPDATE invoice_electronic_details SET hacienda_consecutive = COALESCE(hacienda_consecutive, consecutive_number)');
        }

        if (Schema::hasColumn('invoice_electronic_details', 'hacienda_status')) {
            DB::statement('UPDATE invoice_electronic_details SET electronic_status = COALESCE(electronic_status, hacienda_status, "pending")');
        }

        if (Schema::hasColumn('invoice_electronic_details', 'sent_to_hacienda_at')) {
            DB::statement('UPDATE invoice_electronic_details SET sent_at = COALESCE(sent_at, sent_to_hacienda_at)');
        }

        if (Schema::hasColumn('invoice_electronic_details', 'hacienda_response_at')) {
            DB::statement("UPDATE invoice_electronic_details SET accepted_at = COALESCE(accepted_at, CASE WHEN electronic_status = 'accepted' THEN hacienda_response_at END)");
            DB::statement("UPDATE invoice_electronic_details SET rejected_at = COALESCE(rejected_at, CASE WHEN electronic_status = 'rejected' THEN hacienda_response_at END)");
        }

        if (Schema::hasColumn('invoice_electronic_details', 'hacienda_response')) {
            DB::statement('UPDATE invoice_electronic_details SET ptec_response = COALESCE(ptec_response, hacienda_response)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migración correctiva sin rollback destructivo.
    }
};
