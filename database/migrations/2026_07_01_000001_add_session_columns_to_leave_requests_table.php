<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // session for start date: 'morning' | 'afternoon'
            $table->enum('session1', ['morning', 'afternoon'])->nullable()->after('end_date');
            // session for end date: 'morning' | 'afternoon'
            $table->enum('session2', ['morning', 'afternoon'])->nullable()->after('session1');
        });

        // Change duration_days from integer to decimal to support half-day (0.5) values
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('duration_days', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['session1', 'session2']);
            $table->integer('duration_days')->nullable()->change();
        });
    }
};
