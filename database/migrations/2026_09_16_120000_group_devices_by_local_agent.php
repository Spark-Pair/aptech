<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->string('local_agent_key', 120)->nullable()->after('branch_id')->index();
        });

        DB::table('attendance_sync_agents')->orderBy('id')->get(['id'])->each(function ($row) {
            DB::table('attendance_sync_agents')->where('id', $row->id)->update([
                'local_agent_key' => 'agent-'.$row->id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->dropIndex(['local_agent_key']);
            $table->dropColumn('local_agent_key');
        });
    }
};
