<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->integer('pay_period_month')->nullable();
            $table->integer('pay_period_year')->nullable();
            $table->float('gross_salary')->nullable();
            $table->float('overtime')->nullable();
            $table->float('deductions')->nullable();
            $table->float('net_pay')->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->default('draft'); // draft, completed
            $table->integer('current_step')->default(1);
            $table->json('data')->nullable(); // Stores all the wizard data
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
