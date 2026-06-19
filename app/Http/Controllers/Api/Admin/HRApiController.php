<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Designation;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HRApiController extends ApiController
{
    // Designations
    public function indexDesignations(): JsonResponse
    {
        return $this->success(Designation::all());
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255', 'default_punch_access' => 'boolean']);
        $designation = Designation::create(['name' => $request->name, 'default_punch_access' => $request->boolean('default_punch_access')]);
        return $this->success($designation, 'Designation created successfully', 201);
    }

    public function updateDesignation(Request $request, Designation $designation): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255', 'default_punch_access' => 'boolean']);
        $designation->update(['name' => $request->name, 'default_punch_access' => $request->boolean('default_punch_access')]);
        return $this->success($designation, 'Designation updated successfully');
    }

    public function showDesignation(Designation $designation): JsonResponse
    {
        return $this->success($designation);
    }

    public function destroyDesignation(Designation $designation): JsonResponse
    {
        $designation->delete();
        return $this->success(null, 'Designation deleted successfully');
    }

    // Departments
    public function indexDepartments(): JsonResponse
    {
        return $this->success(Department::all());
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $department = Department::create(['name' => $request->name]);
        return $this->success($department, 'Department created successfully', 201);
    }

    public function showDepartment(Department $department): JsonResponse
    {
        return $this->success($department);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $department->update(['name' => $request->name]);
        return $this->success($department, 'Department updated successfully');
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        $department->delete();
        return $this->success(null, 'Department deleted successfully');
    }
}
