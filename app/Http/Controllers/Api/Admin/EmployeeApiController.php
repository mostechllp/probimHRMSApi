<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use App\Models\EmployeeSalaryPackage;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\UserRegistrationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmployeeApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'active');
        $perPage = $request->get('per_page', 100);

        $query = Employee::with(['user.company', 'user.organization', 'user.department', 'user.designation', 'salaryPackages.salaryComponents', 'bankDetails'])
            ->whereHas('user', function ($q) {
                $q->whereNotIn('type', ['admin']);
            });

        if ($status === 'inactive') {
            $query = $query->onlyInactive();
        }

        $employees = $query->paginate($perPage);

        return $this->success($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data = $this->handleDocuments($data);
        $data = $this->handleSpecialDays($request, $data);

        $userEmail = $data['company_email'] ?? $data['personal_email'];

        // 1. Create User
        $randomPassword = Str::random(10);
        $user = User::create([
            'username' => $userEmail,
            'email' => $userEmail,
            'password' => Hash::make($randomPassword),
            'organization_id' => $data['organization_id'] ?? 1,
            'company_id' => $data['company_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'type' => $data['type'] ?? 'employee',
            'status' => $data['status'] ?? 'active',
        ]);

        // Assign Role
        $roleId = $data['role_id'] ?? null;
        $role = Role::where('id', $roleId)->first();
        if ($role) {
            $user->update(['role_id' => $role->id]);
        }

        // 2. Create Employee linked to User
        $data['user_id'] = $user->id;

        // Remove fields that are now on User table
        unset($data['organization_id'], $data['company_id'], $data['department_id'], $data['designation_id'], $data['status'], $data['type']);

        $employee = Employee::create($data);

        foreach ([
            ['name' => 'Package 1 - Home Country / WFH', 'currency' => 'INR'],
            ['name' => 'Package 2 - Dubai Onsite', 'currency' => 'AED'],
        ] as $package) {
            EmployeeSalaryPackage::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'name' => $package['name'],
                    'currency' => $package['currency'],
                ],
                [
                    'is_active' => $packageData['is_active'] ?? true,
                ]
            );
        }

        // Send Email to company email (priority) or personal email
        $recipient = $employee->company_email ?: $employee->personal_email;
        if ($recipient) {
            try {
                Mail::to($recipient)->send(new UserRegistrationMail($user, $randomPassword, $employee));
            } catch (\Exception $e) {
                // Log error or handle it, but don't fail the registration
                \Log::error('Failed to send registration email: ' . $e->getMessage());
            }
        }

        // Load relations for response
        $employee->load(['user.company', 'user.department', 'user.designation']);

        return $this->success($employee, 'Employee and User created successfully', 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load(['user.company', 'user.organization', 'user.department', 'user.designation', 'salaryPackages.salaryComponents', 'bankDetails']);
        return $this->success($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();
        $data = $this->handleDocuments($data);
        $data = $this->handleSpecialDays($request, $data);
        $userEmail = $data['company_email'] ?? $data['personal_email'];

        $emailChanged = false;
        $randomPassword = null;

        if (array_key_exists('company_email', $data) && $data['company_email'] !== $employee->company_email) {
            $emailChanged = true;
            $randomPassword = Str::random(10);
            $data['password'] = $randomPassword;
        }

        // Update User part if User exists
        if ($employee->user) {
            $userData = [];
            $userData['username'] = $userEmail;
            if (isset($data['company_email']))
                $userData['email'] = $userEmail;
            if (!empty($data['password']))
                $userData['password'] = Hash::make($data['password']);
            if (array_key_exists('organization_id', $data))
                $userData['organization_id'] = $data['organization_id'];
            if (array_key_exists('company_id', $data))
                $userData['company_id'] = $data['company_id'];
            if (array_key_exists('department_id', $data))
                $userData['department_id'] = $data['department_id'];
            if (array_key_exists('designation_id', $data))
                $userData['designation_id'] = $data['designation_id'];
            if (array_key_exists('type', $data))
                $userData['type'] = $data['type'] ?? $employee->user->type;
            if (array_key_exists('status', $data))
                $userData['status'] = $data['status'];

            if (!empty($userData)) {
                $employee->user->update($userData);
            }

            // Update role if provided
            if (isset($data['role_id'])) {
                $role = \App\Models\Role::where('id', $data['role_id'])->first();
                if ($role) {
                    $employee->user->update(['role_id' => $role->id]);
                }
            }
        }

        // Remove fields that belong to User
        unset($data['organization_id'], $data['company_id'], $data['department_id'], $data['designation_id'], $data['password'], $data['username'], $data['type']);

        $employee->update($data);

        if ($emailChanged) {
            try {
                Mail::to($data['company_email'])->send(new \App\Mail\UserEmailUpdatedMail($employee->user, $randomPassword, $employee));
            } catch (\Exception $e) {
                Log::error('Failed to send email on company email update: ' . $e->getMessage());
            }
        }

        $employee->load(['user.company', 'user.department', 'user.designation']);

        return $this->success($employee, 'Employee updated successfully');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        if ($employee->user) {
            $employee->user->delete();
        }
        $employee->delete();
        return $this->success(null, 'User deleted successfully');
    }

    public function updateStatus(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive'
        ]);

        // Update status in user table
        $employee->user->update([
            'status' => $request->status
        ]);

        return $this->success($employee->load('user'), 'Status updated successfully');
    }

    public function uploadTemp(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        try {
            $file = $request->file('file');

            if (!$file->isValid()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid file upload'
                ], 400);
            }

            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('temp')) {
                Storage::disk('public')->makeDirectory('temp');
            }

            $path = Storage::disk('public')->putFileAs(
                'temp',       // folder
                $file,        // file
                $fileName     // filename
            );

            if (!$path) {
                return response()->json([
                    'status' => false,
                    'message' => 'File storage failed'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'path' => $path,
                'url' => Storage::disk('public')->url($path)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function handleDocuments(array $data): array
    {
        $documentFields = [
            'avatar',
            'passport_1st_page',
            'passport_2nd_page',
            'passport_outer_page',
            'passport_id_page',
            'visa_page',
            'labor_card',
            'eid_1st_page',
            'eid_2nd_page',
            'educational_1st_page',
            'educational_2nd_page',
            'home_country_id_proof'
        ];

        foreach ($documentFields as $field) {
            if (!empty($data[$field]) && strpos($data[$field], 'temp/') === 0) {
                $tempPath = $data[$field];
                $fileName = basename($tempPath);

                $dir = ($field === 'avatar') ? 'avatars' : 'documents';
                $newPath = $dir . '/' . $fileName;

                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $newPath);
                    $data[$field] = $newPath;
                }
            }
        }
        return $data;
    }

    private function handleSpecialDays(Request $request, array $data): array
    {
        $names = $request->special_days_name;
        $dates = $request->special_days_date;
        $specialDays = [];

        if ($names && is_array($names)) {
            foreach ($names as $key => $name) {
                if ($name) {
                    $specialDays[] = [
                        'name' => $name,
                        'date' => $dates[$key] ?? null
                    ];
                }
            }
        }
        $data['special_days'] = !empty($specialDays) ? $specialDays : null;
        return $data;
    }
}
