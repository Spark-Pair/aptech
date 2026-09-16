<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->dropUnique('attendance_sync_agents_token_hash_unique');
            $table->index('token_hash', 'attendance_sync_agents_token_hash_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->dropIndex('attendance_sync_agents_token_hash_index');
            $table->unique('token_hash');
        });
    }
};
