<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds probation_end_date and contract_end_date columns to the employees table.
     * These are used by the hr:check-probation-contract scheduler command to
     * send proactive 30-day email alerts to HR/admin users.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Employment – Probation & Contract
            $table->date('probation_end_date')->nullable()->after('joining_date');
            $table->date('contract_end_date')->nullable()->after('probation_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['probation_end_date', 'contract_end_date']);
        });
    }
};
