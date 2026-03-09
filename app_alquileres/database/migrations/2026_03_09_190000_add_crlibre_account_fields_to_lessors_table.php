<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            if (!Schema::hasColumn('lessors', 'crlibre_username')) {
                $table->string('crlibre_username', 120)->nullable()->after('economic_activity_code');
            }

            if (!Schema::hasColumn('lessors', 'crlibre_password')) {
                $table->text('crlibre_password')->nullable()->after('crlibre_username');
            }

            if (!Schema::hasColumn('lessors', 'crlibre_session_key')) {
                $table->text('crlibre_session_key')->nullable()->after('crlibre_password');
            }

            if (!Schema::hasColumn('lessors', 'crlibre_session_obtained_at')) {
                $table->timestamp('crlibre_session_obtained_at')->nullable()->after('crlibre_session_key');
            }

            if (!Schema::hasColumn('lessors', 'certificate_uploaded_at')) {
                $table->timestamp('certificate_uploaded_at')->nullable()->after('certificate_code');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_access_token')) {
                $table->text('hacienda_access_token')->nullable()->after('hacienda_password');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_refresh_token')) {
                $table->text('hacienda_refresh_token')->nullable()->after('hacienda_access_token');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_token_expires_at')) {
                $table->timestamp('hacienda_token_expires_at')->nullable()->after('hacienda_refresh_token');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_refresh_expires_at')) {
                $table->timestamp('hacienda_refresh_expires_at')->nullable()->after('hacienda_token_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            $columns = [
                'crlibre_username',
                'crlibre_password',
                'crlibre_session_key',
                'crlibre_session_obtained_at',
                'certificate_uploaded_at',
                'hacienda_access_token',
                'hacienda_refresh_token',
                'hacienda_token_expires_at',
                'hacienda_refresh_expires_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('lessors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
