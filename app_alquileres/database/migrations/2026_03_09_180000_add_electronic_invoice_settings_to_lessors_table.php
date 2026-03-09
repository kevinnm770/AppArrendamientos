<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            if (!Schema::hasColumn('lessors', 'identification_type')) {
                $table->string('identification_type', 10)->default('fisico')->after('id_number');
            }

            if (!Schema::hasColumn('lessors', 'commercial_name')) {
                $table->string('commercial_name')->nullable()->after('legal_name');
            }

            if (!Schema::hasColumn('lessors', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('lessors', 'province')) {
                $table->string('province', 2)->nullable()->after('address');
            }

            if (!Schema::hasColumn('lessors', 'canton')) {
                $table->string('canton', 2)->nullable()->after('province');
            }

            if (!Schema::hasColumn('lessors', 'district')) {
                $table->string('district', 2)->nullable()->after('canton');
            }

            if (!Schema::hasColumn('lessors', 'barrio')) {
                $table->string('barrio', 2)->nullable()->after('district');
            }

            if (!Schema::hasColumn('lessors', 'other_signs')) {
                $table->string('other_signs')->nullable()->after('barrio');
            }

            if (!Schema::hasColumn('lessors', 'economic_activity_code')) {
                $table->string('economic_activity_code', 6)->nullable()->after('other_signs');
            }

            if (!Schema::hasColumn('lessors', 'certificate_code')) {
                $table->string('certificate_code', 120)->nullable()->after('economic_activity_code');
            }

            if (!Schema::hasColumn('lessors', 'certificate_pin')) {
                $table->string('certificate_pin', 255)->nullable()->after('certificate_code');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_username')) {
                $table->string('hacienda_username', 120)->nullable()->after('certificate_pin');
            }

            if (!Schema::hasColumn('lessors', 'hacienda_password')) {
                $table->string('hacienda_password', 255)->nullable()->after('hacienda_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lessors', function (Blueprint $table) {
            $columns = [
                'identification_type',
                'commercial_name',
                'email',
                'province',
                'canton',
                'district',
                'barrio',
                'other_signs',
                'economic_activity_code',
                'certificate_code',
                'certificate_pin',
                'hacienda_username',
                'hacienda_password',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('lessors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
