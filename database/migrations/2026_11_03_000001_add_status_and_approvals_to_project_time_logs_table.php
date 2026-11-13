<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('project_time_logs', 'status')) {
                // nullable so we can distinguish legacy rows; default applied to new rows only
                $table->string('status', 30)->nullable()->default('pending')->after('time_taken_minutes');
            }
            if (!Schema::hasColumn('project_time_logs', 'description')) {
                $table->text('description')->nullable()->after('status');
            }
            if (!Schema::hasColumn('project_time_logs', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('project_time_logs', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('project_time_logs', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
        });

        // Backfill: all rows that existed before this migration were already
        // accepted by the system — mark them as 'approved'.
        DB::table('project_time_logs')
            ->whereNull('status')
            ->update(['status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            if (Schema::hasColumn('project_time_logs', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('project_time_logs', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('project_time_logs', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('project_time_logs', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('project_time_logs', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

