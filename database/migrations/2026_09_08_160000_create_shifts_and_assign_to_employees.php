<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('department')->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) { $table->dropConstrainedForeignId('shift_id'); });
        Schema::dropIfExists('shifts');
    }
};
