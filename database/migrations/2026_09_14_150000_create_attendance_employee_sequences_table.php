<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_employee_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_empid');
        });

        DB::table('attendance_employee_sequences')->insert([
            'id' => 1,
            'next_empid' => max(1, ((int) DB::table('employees')->max('empid')) + 1),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_employee_sequences');
    }
};
