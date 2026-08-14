<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reporting_manager_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', [
                'draft',
                'pending_visa',
                'pending_checklist',
                'pending_assets',
                'pending_interview',
                'pending_settlement',
                'pending_letters',
                'pending_final',
                'completed'
            ])->default('draft');
            $table->date('last_working_day')->nullable();
            $table->date('resignation_date')->nullable();
            $table->string('separation_type')->nullable();
            $table->integer('notice_period_days')->nullable();
            $table->date('notice_start_date')->nullable();
            $table->string('visa_sponsorship')->nullable();
            $table->string('nationality')->nullable();
            $table->text('reason_for_leaving')->nullable();
            $table->timestamps();
        });

        Schema::create('offboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_id')->constrained('offboardings')->cascadeOnDelete();
            $table->integer('category_id')->nullable();
            $table->string('task_name');
            $table->enum('status', ['pending', 'completed', 'not_applicable'])->default('pending');
            $table->string('responsible_role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('offboarding_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_id')->constrained('offboardings')->cascadeOnDelete();
            $table->string('interviewer')->nullable();
            $table->date('interview_date')->nullable();
            $table->string('interview_mode')->nullable();
            $table->string('overall_satisfaction')->nullable();
            $table->string('primary_reason')->nullable();
            $table->string('work_life_rating')->nullable();
            $table->string('manager_relationship_rating')->nullable();
            $table->text('enjoyed_most')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->boolean('would_recommend')->default(false);
            $table->text('additional_comments')->nullable();
            $table->timestamps();
        });

        Schema::create('offboarding_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_id')->constrained('offboardings')->cascadeOnDelete();
            $table->string('letter_type');
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('offboarding_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_id')->constrained('offboardings')->cascadeOnDelete();
            $table->decimal('total_payable', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('net_payable', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('offboarding_id')->nullable()->constrained('offboardings')->nullOnDelete();
            $table->string('asset_name');
            $table->string('asset_code')->nullable();
            $table->date('issued_on')->nullable();
            $table->string('status')->default('Issued');
            $table->string('condition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
        Schema::dropIfExists('offboarding_settlements');
        Schema::dropIfExists('offboarding_letters');
        Schema::dropIfExists('offboarding_interviews');
        Schema::dropIfExists('offboarding_checklists');
        Schema::dropIfExists('offboardings');
    }
};
