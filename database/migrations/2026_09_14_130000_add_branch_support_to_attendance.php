<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'Main Branch',
            'code' => 'main',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });

        DB::table('attendance_sync_agents')->whereNull('branch_id')->update(['branch_id' => $branchId]);
        DB::table('attendances')->whereNull('branch_id')->update(['branch_id' => $branchId]);

        Schema::table('attendances', function (Blueprint $table) {
            // MySQL may use the old (empid, date) unique index to support the
            // empid foreign key. Give that FK its own index before replacing
            // the uniqueness rule with the branch-aware key.
            $table->index('empid', 'attendances_empid_index');
            $table->dropUnique(['empid', 'date']);
            $table->unique(['branch_id', 'empid', 'date'], 'attendances_branch_employee_date_unique');
            $table->index(['branch_id', 'date'], 'attendances_branch_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_branch_employee_date_unique');
            $table->dropIndex('attendances_branch_date_index');
            $table->unique(['empid', 'date']);
            $table->dropIndex('attendances_empid_index');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('attendance_sync_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
