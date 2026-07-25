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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('receiver_user_id')->nullable()->after('sender_user_id')
                ->constrained('users')->restrictOnDelete();

            $table->foreignUuid('file_id')->nullable()->after('body')
                ->constrained('files_messages')->nullOnDelete();

            $table->dateTime('edited_at')->nullable()->after('read_at');
            $table->dateTime('deleted_at')->nullable()->after('edited_at');

            $table->index('receiver_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receiver_user_id');
            $table->dropConstrainedForeignId('file_id');
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }
};
