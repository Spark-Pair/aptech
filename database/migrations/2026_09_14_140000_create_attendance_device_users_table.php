<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_sync_agent_id')->constrained('attendance_sync_agents')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->string('device_user_id', 64);
            $table->string('device_name')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['attendance_sync_agent_id', 'device_user_id'], 'attendance_device_user_agent_user_unique');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_users');
    }
};
