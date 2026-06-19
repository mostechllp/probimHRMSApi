<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('userid');
            $table->date('log_date')->nullable();
            $table->dateTime('punch_in')->nullable();
            $table->dateTime('punch_out')->nullable();
            $table->integer('status')->nullable();
            $table->string('device_id')->nullable();
            $table->enum('log_status', ['in', 'out'])->nullable();
            $table->enum('attendance_status', ['present', 'absent', 'late', 'early_out', 'half_day', 'wfh'])->nullable();
            $table->decimal('punch_in_latitude', 10, 8)->nullable();
            $table->decimal('punch_in_longitude', 11, 8)->nullable();
            $table->text('punch_in_address')->nullable();
            $table->decimal('punch_out_latitude', 10, 8)->nullable();
            $table->decimal('punch_out_longitude', 11, 8)->nullable();
            $table->text('punch_out_address')->nullable();
            $table->text('timezone')->nullable();
            $table->integer('working_hours')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type');
            $table->date('request_date');
            $table->time('request_time');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('attendance_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('file_path');
            $table->integer('total_records')->default(0);
            $table->integer('processed_records')->default(0);
            $table->integer('progress')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('created_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->string('day');
            $table->boolean('is_enabled')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->integer('created_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_requests');
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('attendance_uploads');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('attendance_logs');
    }
};
