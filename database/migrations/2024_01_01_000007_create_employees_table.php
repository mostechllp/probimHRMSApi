<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('avatar', 225)->nullable();
            $table->string('user_id');                     // FK reference stored as string (links to users)
            $table->string('employee_id')->unique();       // e.g. EMP202609040758
            $table->date('dob')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('probation_start_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('notice_period_start_date')->nullable();
            $table->date('last_working_day')->nullable();
            $table->date('resignation_date')->nullable();
            $table->date('relieving_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status', 100)->nullable();
            $table->string('nationality', 225)->nullable();
            $table->text('special_days')->nullable();

            // Passport
            $table->string('passport_full_name')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('passport_issued_from')->nullable();
            $table->date('passport_issued_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->text('address')->nullable();
            $table->string('passport_1st_page')->nullable();
            $table->string('passport_2nd_page')->nullable();
            $table->string('passport_outer_page')->nullable();
            $table->string('passport_id_page')->nullable();

            // Visa
            $table->enum('visa_type', ['company_visa', 'family_visa', 'other_visa'])->nullable();
            $table->string('visa_number')->nullable();
            $table->date('visa_issued_date')->nullable();
            $table->date('visa_expiry_date')->nullable();
            $table->string('visa_page')->nullable();

            // Labor Card
            $table->string('labor_number')->nullable();
            $table->date('labor_issued_date')->nullable();
            $table->date('labor_expiry_date')->nullable();
            $table->string('labor_card')->nullable();

            // Emirates ID
            $table->string('eid_number')->nullable();
            $table->date('eid_issued_date')->nullable();
            $table->date('eid_expiry_date')->nullable();
            $table->string('eid_1st_page')->nullable();
            $table->string('eid_2nd_page')->nullable();

            // Other Documents
            $table->string('dependents')->nullable();
            $table->string('educational_1st_page')->nullable();
            $table->string('educational_2nd_page')->nullable();
            $table->string('home_country_id_proof')->nullable();
            $table->json('additional_documents')->nullable();

            // Contact
            $table->string('company_mobile_number')->nullable();
            $table->string('personal_number')->nullable();
            $table->string('other_number')->nullable();
            $table->string('relative_number')->nullable();
            $table->string('home_country_number')->nullable();
            $table->string('company_email')->nullable();
            $table->string('personal_email');

            // Professional
            $table->boolean('is_skilled')->default(false);
            $table->string('experience_level')->nullable();
            $table->text('key_skills')->nullable();
            $table->string('highest_education')->nullable();
            $table->string('currency')->nullable();
            $table->string('payment_cycle')->nullable();

            $table->integer('created_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
