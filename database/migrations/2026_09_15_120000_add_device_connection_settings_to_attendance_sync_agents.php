<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->string('device_ip', 45)->nullable()->after('device_identifier');
            $table->unsignedSmallInteger('device_port')->default(4370)->after('device_ip');
            $table->string('device_timezone', 64)->default('Asia/Karachi')->after('device_port');
            $table->unsignedSmallInteger('device_timeout')->default(5)->after('device_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->dropColumn(['device_ip', 'device_port', 'device_timezone', 'device_timeout']);
        });
    }
};
