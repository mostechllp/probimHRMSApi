<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\User;
use App\Models\EmployeeSalaryPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmployeeOnboardingApiController extends ApiController
{
    /**
     * List all employees currently in the onboarding process.
     */
    public function index(): JsonResponse
    {
        $employees = Employee::whereHas('user', function ($query) {
            $query->where('status', 'pending_onboarding');
        })
            ->with([
                'user.department',
                'user.designation',
                'salaryPackages.salaryComponents',
                'bankDetails',
            ])
            ->get();

        return $this->success($employees, 'Onboarding records fetched successfully');
    }

    /**
     * Show the current onboarding details for a single employee, by employee ID.
     * Reflects everything saved so far via saveDetails, saveSalary, and saveBanks.
     */
    public function show($id): JsonResponse
    {
        $employee = Employee::whereHas('user', function ($query) {
            $query->where('status', 'pending_onboarding');
        })
            ->with([
                'user.department',
                'user.designation',
                'salaryPackages.salaryComponents',
                'bankDetails',
            ])
            ->find($id);

        if (!$employee) {
            return $this->error('Onboarding employee not found', 404);
        }

        return $this->success($employee, 'Onboarding details fetched successfully');
    }

    /**
     * Edit the basic details of a single onboarding employee, by employee ID.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $employee = Employee::whereHas('user', function ($query) {
            $query->where('status', 'pending_onboarding');
        })->find($id);

        if (!$employee) {
            return $this->error('Onboarding employee not found', 404);
        }

        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'personal_email' => [
                'nullable',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('employees', 'personal_email')->whereNull('deleted_at')->ignore($employee->id),
            ],
            'personal_number' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'joining_date' => 'nullable|date',
            'experience_level' => 'nullable|string|max:255',
            'key_skills' => 'nullable|string',
            'highest_education' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|exists:departments,id',
            'designation_id' => 'nullable|integer|exists:designations,id',
            'special_days' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employeeData = $request->only([
                'first_name',
                'last_name',
                'personal_email',
                'personal_number',
                'nationality',
                'address',
                'joining_date',
                'experience_level',
                'key_skills',
                'highest_education',
            ]);

            if ($request->has('special_days')) {
                $employeeData['special_days'] = $request->special_days;
            }

            $employee->update($employeeData);

            if ($employee->user) {
                $userData = [];

                if ($request->has('department_id')) {
                    $userData['department_id'] = $request->department_id;
                }

                if ($request->has('designation_id')) {
                    $userData['designation_id'] = $request->designation_id;
                }

                if (!empty($userData)) {
                    $employee->user->update($userData);
                }
            }
        });

        return $this->success($employee->fresh()->load('user'), 'Onboarding details updated successfully');
    }

    /**
     * Delete an onboarding employee (and their linked user account), by employee ID.
     */
    public function destroy($id): JsonResponse
    {
        $employee = Employee::whereHas('user', function ($query) {
            $query->where('status', 'pending_onboarding');
        })->find($id);

        if (!$employee) {
            return $this->error('Onboarding employee not found', 404);
        }

        DB::transaction(function () use ($employee) {
            $user = $employee->user;

            $employee->delete();

            if ($user) {
                $user->delete();
            }
        });

        return $this->success(null, 'Onboarding employee deleted successfully');
    }

    /**
     * Save basic employee details
     */
    // public function saveDetails(Request $request): JsonResponse
    // {
    //     $user_id = $request->user_id;
    //     $employee = null;

    //     if ($user_id) {
    //         $employee = Employee::where('id', $user_id)->first();

    //         if (!$employee) {
    //             return $this->error('Employee not found with the provided ID', 404);
    //         }
    //     } else {
    //         $employee = new Employee();
    //     }

    //     $request->validate(
    //         [
    //             'first_name' => 'nullable|string|max:255',
    //             'last_name' => 'nullable|string|max:255',
    //             'personal_email' => 'nullable|email|unique:employees,personal_email|max:255',
    //             'personal_number' => 'required|string|max:255',
    //             'nationality' => 'nullable|string|max:255',
    //             'address' => 'nullable|string',
    //             'joining_date' => 'nullable|date',
    //             'experience_level' => 'nullable|string|max:255',
    //             'key_skills' => 'nullable|string',
    //             'role_id' => 'required',
    //             'type' => 'required',
    //             'highest_education' => 'nullable|string|max:255',
    //             'department_id' => 'nullable|integer|exists:departments,id',
    //             'designation_id' => 'nullable|integer|exists:designations,id',
    //             'special_days' => 'nullable|array',
    //         ],
    //         [
    //             'first_name.required' => 'Please enter the first name.',
    //             'last_name.required' => 'Please enter the last name.',
    //             'personal_email.required' => 'Please enter the personal email address.',
    //             'personal_email.email' => 'Please enter a valid email address.',
    //             'personal_email.unique' => 'This personal email is already registered.',
    //             'personal_number.required' => 'Please enter the personal phone number.',
    //             'joining_date.date' => 'Please enter a valid joining date.',
    //             'role_id.required' => 'Please select a role.',
    //             'type.required' => 'Please select the employee type.',
    //             'department_id.exists' => 'The selected department does not exist.',
    //             'designation_id.exists' => 'The selected designation does not exist.',
    //             'special_days.array' => 'Special days must be provided as a valid list.',
    //         ]
    //     );

    //     DB::transaction(function () use ($request, &$employee) {
    //         $employeeData = $request->only([
    //             'first_name',
    //             'last_name',
    //             'personal_email',
    //             'personal_number',
    //             'nationality',
    //             'address',
    //             'joining_date',
    //             'experience_level',
    //             'key_skills',
    //             'highest_education'
    //         ]);

    //         if ($request->has('special_days')) {
    //             $employeeData['special_days'] = $request->special_days;
    //         }

    //         if (!$employee->exists) {
    //             // Generate a temporary user email if not provided
    //             $userEmail = $request->personal_email ?? 'temp_' . \Illuminate\Support\Str::random(8) . '@example.com';

    //             // Create a User record
    //             $user = \App\Models\User::create([
    //                 'username' => $userEmail,
    //                 'email' => $userEmail,
    //                 'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(10)),
    //                 'organization_id' => $request->organization_id ?? 1,
    //                 'company_id' => $request->company_id ?? null,
    //                 'type' => 'employee',
    //                 'role_id' => $request->role_id ?? null,
    //                 'status' => 'onboarding',
    //             ]);

    //             $employeeData['user_id'] = $user->id;
    //             $employeeData['employee_id'] = 'EMP-' . strtoupper(\Illuminate\Support\Str::random(6));
    //             $employeeData['company_email'] = $userEmail;

    //             if (empty($employeeData['personal_email'])) {
    //                 $employeeData['personal_email'] = $userEmail;
    //             }

    //             if (empty($employeeData['first_name'])) {
    //                 $employeeData['first_name'] = 'Draft';
    //             }

    //             $employee->fill($employeeData);
    //             $employee->save();
    //         } else {
    //             $employee->update($employeeData);
    //         }

    //         if ($employee->user) {
    //             $userData = [];
    //             if ($request->has('department_id'))
    //                 $userData['department_id'] = $request->department_id;
    //             if ($request->has('designation_id'))
    //                 $userData['designation_id'] = $request->designation_id;

    //             if (!empty($userData)) {
    //                 $employee->user->update($userData);
    //             }
    //         }
    //     });

    //     return $this->success($employee->fresh()->load('user'), 'Employee details saved successfully');
    // }

    public function saveDetails(Request $request): JsonResponse
    {
        $userId = $request->user_id;
        $employee = null;

        /*
    |--------------------------------------------------------------------------
    | Find employee for update
    |--------------------------------------------------------------------------
    */
        if ($userId) {

            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return $this->error(
                    'Employee not found with the provided user ID',
                    404
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
        $request->validate(
            [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',

                'personal_email' => [
                    'nullable',
                    'email',
                    'max:255',
                    \Illuminate\Validation\Rule::unique(
                        'employees',
                        'personal_email'
                    )->whereNull('deleted_at')->ignore($employee?->id),
                ],

                'personal_number' => 'required|string|max:255',
                'nationality' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'joining_date' => 'nullable|date',
                'experience_level' => 'nullable|string|max:255',
                'key_skills' => 'nullable|string',
                'role_id' => 'required',
                'type' => 'required',
                'highest_education' => 'nullable|string|max:255',
                'department_id' => 'nullable|integer|exists:departments,id',
                'designation_id' => 'nullable|integer|exists:designations,id',
                'special_days' => 'nullable|array',
            ],
            [
                'first_name.required' => 'Please enter the first name.',
                'last_name.required' => 'Please enter the last name.',
                'personal_email.email' => 'Please enter a valid email address.',
                'personal_email.unique' => 'This personal email is already registered.',
                'personal_number.required' => 'Please enter the personal phone number.',
                'joining_date.date' => 'Please enter a valid joining date.',
                'role_id.required' => 'Please select a role.',
                'type.required' => 'Please select the employee type.',
                'department_id.exists' => 'The selected department does not exist.',
                'designation_id.exists' => 'The selected designation does not exist.',
                'special_days.array' => 'Special days must be provided as a valid list.',
            ]
        );

        DB::transaction(function () use ($request, &$employee, $userId) {

            $employeeData = $request->only([
                'first_name',
                'last_name',
                'personal_email',
                'personal_number',
                'nationality',
                'address',
                'joining_date',
                'experience_level',
                'key_skills',
                'highest_education',
            ]);

            if ($request->has('special_days')) {
                $employeeData['special_days'] = $request->special_days;
            }

            /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        | user_id is NOT provided
        |--------------------------------------------------------------------------
        */
            if (!$userId) {

                $userEmail = $request->personal_email
                    ?? 'temp_' . \Illuminate\Support\Str::random(8) . '@example.com';

                $password = \Illuminate\Support\Str::random(10);

                $user = \App\Models\User::create([
                    'username' => $userEmail,
                    'email' => $userEmail,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'organization_id' => $request->organization_id ?? 1,
                    'company_id' => $request->company_id ?? null,
                    'type' => 'employee',
                    'role_id' => $request->role_id ?? null,
                    'status' => 'pending_onboarding',
                ]);

                $employeeData['user_id'] = $user->id;

                $employeeData['employee_id'] =
                    'EMP-' . strtoupper(\Illuminate\Support\Str::random(6));

                $employeeData['company_email'] = $userEmail;

                if (empty($employeeData['personal_email'])) {
                    $employeeData['personal_email'] = $userEmail;
                }

                if (empty($employeeData['first_name'])) {
                    $employeeData['first_name'] = 'Draft';
                }

                $employee = Employee::create($employeeData);
            }

            /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        | user_id IS provided
        |--------------------------------------------------------------------------
        */ else {

                $employee->update($employeeData);

                /*
             * Update User details also
             */
                if ($employee->user) {

                    $userData = [];

                    if ($request->has('role_id')) {
                        $userData['role_id'] = $request->role_id;
                    }

                    if ($request->has('department_id')) {
                        $userData['department_id'] = $request->department_id;
                    }

                    if ($request->has('designation_id')) {
                        $userData['designation_id'] = $request->designation_id;
                    }

                    if ($request->has('type')) {
                        $userData['type'] = $request->type;
                    }

                    if (!empty($userData)) {
                        $employee->user->update($userData);
                    }
                }
            }
        });

        return $this->success(
            $employee->fresh()->load('user'),
            $userId
                ? 'Employee details updated successfully'
                : 'Employee details saved successfully'
        );
    }

    public function getSalaryPackages($id): JsonResponse
    {

        $packages = EmployeeSalaryPackage::where('employee_id', $id)->get();

        return $this->success($packages, 'Salary packages fetched successfully');
    }

    /**
     * Save salary structure details
     */
    public function saveSalary(Request $request): JsonResponse
    {
        $user_id = $request->user_id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->first();

        if (!$employee) {
            return $this->error('Employee not found with the provided ID', 404);
        }

        $request->validate([
            'payment_cycle' => 'required|string|max:255',
            'packages' => 'required|array',
            'packages.*.name' => 'required|string|max:255',
            'packages.*.currency' => 'required|string|max:255',
            'packages.*.is_active' => 'required|boolean',
            'packages.*.salary_components' => 'nullable|array',
            'packages.*.salary_components.*.component_name' => 'required|string|max:255',
            'packages.*.salary_components.*.value' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employee->update([
                'payment_cycle' => $request->payment_cycle,
            ]);

            // Clear old components
            $employee->salaryComponents()->delete();

            if ($request->has('packages') && is_array($request->packages)) {
                foreach ($request->packages as $pkgKey => $packageData) {
                    $package = EmployeeSalaryPackage::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'name' => $packageData['name'],
                            'currency' => $packageData['currency'] ?? 'AED',
                        ],
                        [
                            'is_active' => $packageData['is_active'] ?? true
                        ]
                    );


                    if (isset($packageData['salary_components']) && is_array($packageData['salary_components'])) {
                        foreach ($packageData['salary_components'] as $component) {
                            $employee->salaryComponents()->create([
                                'employee_salary_package_id' => $package->id,
                                'component_name' => $component['component_name'],
                                'value' => $component['value']
                            ]);
                        }
                    }
                }
            }
        });

        // Fetch the updated packages through components
        $packageIds = $employee->salaryComponents()->pluck('employee_salary_package_id')->unique();
        $packages = EmployeeSalaryPackage::whereIn('id', $packageIds)
            ->with([
                'salaryComponents' => function ($query) use ($employee) {
                    $query->where('employee_id', $employee->id);
                }
            ])->get();

        $packagesResponse = [];
        $packageIndex = 1;
        foreach ($packages as $package) {
            $components = $package->salaryComponents->map(function ($comp) {
                return [
                    'id' => $comp->id,
                    'component_name' => $comp->component_name,
                    'value' => (float) $comp->value,
                ];
            })->toArray();

            $totalMonthlySalary = collect($components)->sum('value');

            $key = "package" . $packageIndex;
            $packagesResponse[$key] = [
                'id' => $package->id,
                'name' => $package->name,
                'currency' => $package->currency,
                'is_active' => $package->is_active,
                'total_monthly_salary' => $totalMonthlySalary,
                'salary_components' => $components,
            ];
            $packageIndex++;
        }

        $responseData = [
            'employee_id' => $employee->id,
            'packages' => $packagesResponse,
            'payment_cycle' => $employee->payment_cycle,
        ];

        return $this->success($responseData, 'Salary packages saved successfully');
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

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->first();

        if (!$employee) {
            return $this->error('Employee not found with the provided ID', 404);
        }

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

        $employee = Employee::where('user_id', $user_id)->orWhere('id', $user_id)->first();

        if (!$employee) {
            return $this->error('Employee not found with the provided ID', 404);
        }

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

        return $this->success($employee->fresh()->load('user', 'salaryComponents.package', 'bankDetails'), 'Onboarding completed successfully');
    }
}
