<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_sync_agent_id')->constrained()->cascadeOnDelete();
            $table->string('batch_id', 100);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('updated_days')->default(0);
            $table->timestamps();

            $table->unique(['attendance_sync_agent_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sync_batches');
    }
};
