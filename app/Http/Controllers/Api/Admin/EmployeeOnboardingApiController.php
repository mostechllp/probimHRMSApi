<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmployeeOnboardingApiController extends ApiController
{
    /**
     * Save basic employee details
     */
    public function saveDetails(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $employee = null;

        if ($user_id) {
            $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->firstOrFail();
        } else {
            $employee = new Employee();
        }

        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'personal_email' => 'nullable|email|max:255',
            'personal_number' => 'required|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'joining_date' => 'nullable|date',
            'experience_level' => 'nullable|string|max:255',
            'key_skills' => 'nullable|string',
            'highest_education' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|exists:departments,id',
            'designation_id' => 'nullable|integer|exists:designations,id',
            'special_days' => 'nullable|array'
        ]);

        DB::transaction(function () use ($request, &$employee) {
            $employeeData = $request->only([
                'first_name', 'last_name', 'personal_email', 'personal_number', 
                'nationality', 'address', 'joining_date', 'experience_level', 
                'key_skills', 'highest_education'
            ]);

            if ($request->has('special_days')) {
                $employeeData['special_days'] = $request->special_days;
            }

            if (!$employee->exists) {
                // Generate a temporary user email if not provided
                $userEmail = $request->personal_email ?? 'temp_' . \Illuminate\Support\Str::random(8) . '@example.com';
                
                // Create a User record
                $user = \App\Models\User::create([
                    'username' => $userEmail,
                    'email' => $userEmail,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(10)),
                    'organization_id' => $request->organization_id ?? 1,
                    'company_id' => $request->company_id ?? null,
                    'type' => 'employee',
                    'status' => 'onboarding',
                ]);

                $employeeData['user_id'] = $user->id;
                $employeeData['employee_id'] = 'EMP-' . strtoupper(\Illuminate\Support\Str::random(6));
                $employeeData['company_email'] = $userEmail;

                if (empty($employeeData['personal_email'])) {
                    $employeeData['personal_email'] = $userEmail;
                }
                
                if (empty($employeeData['first_name'])) {
                    $employeeData['first_name'] = 'Draft';
                }

                $employee->fill($employeeData);
                $employee->save();
            } else {
                $employee->update($employeeData);
            }

            if ($employee->user) {
                $userData = [];
                if ($request->has('department_id')) $userData['department_id'] = $request->department_id;
                if ($request->has('designation_id')) $userData['designation_id'] = $request->designation_id;
                
                if (!empty($userData)) {
                    $employee->user->update($userData);
                }
            }
        });

        return $this->success($employee->fresh()->load('user'), 'Employee details saved successfully');
    }

    /**
     * Save salary structure details
     */
    public function saveSalary(Request $request): JsonResponse
    {
        $user_id = $request->user_id;

        if (!$user_id) {
            return $this->error('User ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->firstOrFail();

        $request->validate([
            'currency' => 'required|string|max:255',
            'payment_cycle' => 'required|string|max:255',
            'salary_components' => 'nullable|array',
            'salary_components.*.component_name' => 'required|string|max:255',
            'salary_components.*.value' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employee->update([
                'currency' => $request->currency,
                'payment_cycle' => $request->payment_cycle,
            ]);

            // Clear old components and insert new ones
            $employee->salaryComponents()->delete();

            if ($request->has('salary_components') && is_array($request->salary_components)) {
                foreach ($request->salary_components as $component) {
                    $employee->salaryComponents()->create([
                        'component_name' => $component['component_name'],
                        'value' => $component['value']
                    ]);
                }
            }
        });

        return $this->success($employee->fresh()->load('salaryComponents'), 'Salary details saved successfully');
    }

    /**
     * Save bank details
     */
    public function saveBanks(Request $request): JsonResponse
    {
        $user_id = $request->user_id;

        if (!$user_id) {
            return $this->error('User ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->firstOrFail();

        $request->validate([
            'bank_details' => 'nullable|array',
            'bank_details.*.bank_country' => 'required|string|max:255',
            'bank_details.*.bank_name' => 'required|string|max:255',
            'bank_details.*.account_number' => 'required|string|max:255',
            'bank_details.*.iban_number' => 'nullable|string|max:255',
            'bank_details.*.swift_code' => 'nullable|string|max:255',
            'bank_details.*.branch_name' => 'nullable|string|max:255',
            'bank_details.*.ifsc_code' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $employee) {
            // Clear old banks and insert new ones
            $employee->bankDetails()->delete();

            if ($request->has('bank_details') && is_array($request->bank_details)) {
                foreach ($request->bank_details as $bank) {
                    $employee->bankDetails()->create($bank);
                }
            }
        });

        return $this->success($employee->fresh()->load('bankDetails'), 'Bank details saved successfully');
    }

    /**
     * Complete Onboarding
     */
    public function complete(Request $request): JsonResponse
    {
        $user_id = $request->user_id;

        if (!$user_id) {
            return $this->error('User ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->firstOrFail();

        if ($employee->user) {
            $randomPassword = \Illuminate\Support\Str::random(10);
            
            $employee->user->update([
                'status' => 'onboarding',
                'password' => \Illuminate\Support\Facades\Hash::make($randomPassword)
            ]);

            $recipient = $employee->company_email ?: $employee->personal_email;
            if ($recipient) {
                try {
                    \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\UserRegistrationMail($employee->user, $randomPassword, $employee));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send registration email: ' . $e->getMessage());
                }
            }
        }

        return $this->success($employee->fresh()->load('user', 'salaryComponents', 'bankDetails'), 'Onboarding completed successfully');
    }
}
