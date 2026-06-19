<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Company;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'active');
        $perPage = $request->get('per_page', 15);

        $query = Employee::with(['company', 'department', 'designation']);

        if ($status === 'inactive') {
            $query = $query->onlyInactive();
        }

        $employees = $query->get();

        return view('employees.index', compact('employees', 'status'));
    }

    public function create()
    {
        $companies = Company::where('organization_id','1')->get();
        $departments = Department::all();
        $designations = Designation::orderBy('id', 'desc')->get();
        $organizations = Organization::all();
        $employee = new Employee();
        return view('employees.create', compact('companies', 'departments', 'organizations', 'employee', 'designations'));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        $documentFields = [
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

                $newPath = 'documents/' . $fileName;

                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $newPath);
                    $data[$field] = $newPath;
                }
            }
        }


        $names = $request->special_days_name;
        $dates = $request->special_days_date;

        $specialDays = [];

        if ($names) {
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

        $data['organization_id'] = 1;
        $data['password'] = Hash::make('Thesay@ae');

        Employee::create($data);

        return redirect()->back()->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        $companies = Company::all();
        $departments = Department::all();
        $designations = Designation::orderBy('id', 'desc')->get();
        $organizations = Organization::all();
        return view('employees.edit', compact('employee', 'companies', 'departments', 'organizations', 'designations'));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        $documentFields = [
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

        // foreach ($documentFields as $field) {
        //     if ($request->hasFile($field)) {
        //         // Delete old file if exists
        //         if ($employee->$field) {
        //             Storage::disk('public')->delete($employee->$field);
        //         }
        //         $data[$field] = $request->file($field)->store('documents', 'public');
        //     }
        // }
        foreach ($documentFields as $field) {
            if (!empty($data[$field]) && strpos($data[$field], 'temp/') === 0) {
                $tempPath = $data[$field];
                $fileName = basename($tempPath);

                $newPath = 'documents/' . $fileName;

                if (Storage::disk('public')->exists($tempPath)) {
                    Storage::disk('public')->move($tempPath, $newPath);
                    $data[$field] = $newPath;
                }
            }
        }

        $names = $request->special_days_name;
        $dates = $request->special_days_date;

        $specialDays = [];

        if ($names) {
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

        $data['organization_id'] = 1;

        $employee->update($data);

        return redirect()->back()->with('success', 'Employee updated successfully.');
    }

    public function updateStatus(Request $request, Employee $employee)
    {
        $request->validate([
            'status' => 'required|in:active,inactive'
        ]);

        $employee->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

    public function uploadTempDocument(Request $request)
    {
        if ($request->hasFile('file')) {

            $path = $request->file('file')->store('temp', 'public');

            return response()->json([
                'success' => true,
                'path' => $path
            ], 200);
        } else {
            return response()->json([
                'success' => false
            ], 400);
        }
    }

    public function preview(Request $request)
    {
        $path = $request->get('url');
        $label = $request->get('label');

        $url = asset('storage/' . $path);

        return view('employees.preview', compact('url', 'label'));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }
}
