<?php

namespace App\Http\Requests;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $dates = [
            'dob',
            'joining_date',
            'passport_issued_date',
            'passport_expiry_date',
            'visa_issued_date',
            'visa_expiry_date',
            'labor_issued_date',
            'labor_expiry_date',
            'eid_issued_date',
            'eid_expiry_date',
            'probation_start_date',
            'probation_end_date',
            'confirmation_date',
            'contract_start_date',
            'contract_end_date',
            'notice_period_start_date',
            'last_working_day',
            'resignation_date',
            'relieving_date'
        ];

        foreach ($dates as $field) {
            if ($this->filled($field)) {
                try {
                    $this->merge([
                        $field => Carbon::createFromFormat('d-m-Y', $this->$field)->format('Y-m-d')
                    ]);
                } catch (\Exception $e) {
                    // ignore invalid format
                }
            }
        }

        if ($this->has('additional_documents') && is_array($this->additional_documents)) {
            $docs = $this->additional_documents;
            foreach ($docs as $key => $doc) {
                if (isset($doc['expiry_date']) && $doc['expiry_date']) {
                    try {
                        $docs[$key]['expiry_date'] = Carbon::createFromFormat('d-m-Y', $doc['expiry_date'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        // ignore invalid format
                    }
                }
            }
            $this->merge([
                'additional_documents' => $docs
            ]);
        }

        // Convert special days array
        if ($this->has('special_days_date')) {

            $convertedDates = [];

            foreach ($this->special_days_date as $date) {

                if ($date) {
                    try {
                        $convertedDates[] = Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $convertedDates[] = $date;
                    }
                }
            }

            $this->merge([
                'special_days_date' => $convertedDates
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'designation_id' => 'required|exists:designations,id',
            'department_id' => 'required|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
            'dob' => 'nullable|date',
            'joining_date' => 'nullable|date',
            'probation_start_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'confirmation_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'notice_period_start_date' => 'nullable|date',
            'last_working_day' => 'nullable|date',
            'resignation_date' => 'nullable|date',
            'relieving_date' => 'nullable|date',
            'gender' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string|max:255',
            'special_days_name.*' => 'nullable|string|max:255',
            'special_days_date.*' => 'nullable|date',

            // Passport
            'passport_full_name' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            'passport_issued_from' => 'nullable|string|max:255',
            'passport_issued_date' => 'nullable|date',
            'passport_expiry_date' => 'nullable|date',
            'place_of_birth' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',

            // ── Document Fields (paths from uploadTemp API) ──
            'avatar' => 'nullable|string|starts_with:temp/',
            'passport_1st_page' => 'nullable|string|starts_with:temp/',
            'passport_2nd_page' => 'nullable|string|starts_with:temp/',
            'passport_outer_page' => 'nullable|string|starts_with:temp/',
            'passport_id_page' => 'nullable|string|starts_with:temp/',
            'visa_page' => 'nullable|string|starts_with:temp/',
            'labor_card' => 'nullable|string|starts_with:temp/',
            'eid_1st_page' => 'nullable|string|starts_with:temp/',
            'eid_2nd_page' => 'nullable|string|starts_with:temp/',
            'educational_1st_page' => 'nullable|string|starts_with:temp/',
            'educational_2nd_page' => 'nullable|string|starts_with:temp/',
            'home_country_id_proof' => 'nullable|string|starts_with:temp/',

            // Details
            'visa_type' => 'nullable|in:company_visa,family_visa,other_visa',
            'is_skilled' => 'nullable|boolean',
            'additional_documents' => 'nullable|array',
            'additional_documents.*.document_name' => 'required|string|max:255',
            'additional_documents.*.expiry_date' => 'nullable|date',
            'visa_number' => 'nullable|string|max:255',
            'visa_issued_date' => 'nullable|date',
            'visa_expiry_date' => 'nullable|date',
            'labor_number' => 'nullable|string|max:255',
            'labor_issued_date' => 'nullable|date',
            'labor_expiry_date' => 'nullable|date',
            'eid_number' => 'nullable|string|max:255',
            'eid_issued_date' => 'nullable|date',
            'eid_expiry_date' => 'nullable|date',
            'dependents' => 'nullable|string|max:255',
            'experience_level' => 'nullable|string|max:255',
            'key_skills' => 'nullable|string',
            'highest_education' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:255',
            'payment_cycle' => 'nullable|string|max:255',
            'company_mobile_number' => 'nullable|string|max:255',
            'personal_number' => 'nullable|string|max:255',
            'other_number' => 'nullable|string|max:255',
            'relative_number' => 'nullable|string|max:255',
            'home_country_number' => 'nullable|string|max:255',
            'company_email' => [
                'nullable',
                'email',
                Rule::unique('employees', 'company_email')
                    ->whereNull('deleted_at')
                    ->ignore($this->employee)
            ],
            'personal_email' => [
                'required',
                'email',
                Rule::unique('employees', 'personal_email')
                    ->whereNull('deleted_at')
                    ->ignore($this->employee)
            ],
            'status' => 'nullable|in:active,inactive,onboarding,offboarding,pending_onboarding',
            'total_leaves_allocated' => 'nullable|integer|min:0',
            'username' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($this->user?->id)
            ],
            'type' => 'required|in:admin,employee,hr,team_lead,manager',
            'role_id' => 'required',
        ];
    }

}
