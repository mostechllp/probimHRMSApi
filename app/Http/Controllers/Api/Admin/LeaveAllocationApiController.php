<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveAllocationApiController extends ApiController
{
    /**
     * Display a listing of employee leave balances.
     */
    public function index(): JsonResponse
    {
        $leaveTypes = LeaveType::where('status', true)->get();

        $employees = Employee::with(['user.designation', 'user.department', 'user.company', 'leaveAllocations' => function ($q) {
            $q->where('year', date('Y'));
        }])->get();

        $employeeData = $employees->map(function ($employee) use ($leaveTypes) {
            $allocations = [];
            foreach ($leaveTypes as $type) {
                $allocation = $employee->leaveAllocations->firstWhere('leave_type_id', $type->id);
                $allocations[] = [
                    'leave_type_id' => $type->id,
                    'leave_type_name' => $type->name,
                    'allocated_days' => $allocation ? (int) $allocation->allocated_days : 0,
                ];
            }

            return [
                'employee_id' => $employee->id,
                'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'designation' => $employee->user->designation->name ?? 'N/A',
                'department' => $employee->user->department->name ?? 'N/A',
                'company' => $employee->user->company->name ?? 'N/A',
                'avatar' => $employee->avatar_url ?? null,
                'allocations' => $allocations,
            ];
        });

        return $this->success([
            'leave_types' => $leaveTypes,
            'employees' => $employeeData
        ]);
    }

    /**
     * Get allocations for a specific employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'user.department',
            'user.designation'
        ]);

        $leaveTypes = LeaveType::where('status', true)->get();

        $allocations = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', date('Y'))
            ->get()
            ->keyBy('leave_type_id');

        $totalAllocated = $allocations->sum('allocated_days');

        $totalLeavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $totalLeaveBalance = $totalAllocated - $totalLeavesTaken;

        $leaveTypesBalance = $leaveTypes->map(function ($leaveType) use ($employee, $allocations) {

            $allocated = $allocations[$leaveType->id]->allocated_days ?? 0;

            $taken = LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->sum('duration_days');

            return [
                'leave_type_id' => $leaveType->id,
                'leave_type' => $leaveType->name,
                'allocated' => $allocated,
                'taken' => $taken,
                'balance' => $allocated - $taken,
            ];
        });

        return $this->success([
            'employee' => $employee,
            'leaveTypesBalance' => $leaveTypesBalance,
            'leave_balance' => $totalLeaveBalance,
            'leaves_taken' => $totalLeavesTaken,
            'leaves_allocated' => $totalAllocated
        ]);
    }

    /**
     * Update/Store leave allocations for an employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'required|integer|min:0',
        ]);

        $results = [];
        foreach ($request->allocations as $leaveTypeId => $days) {
            $results[] = LeaveAllocation::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveTypeId,
                    'year' => date('Y'),
                ],
                [
                    'allocated_days' => $days,
                ]
            );
        }

        return $this->success($results, 'Leave allocations updated successfully.');
    }
}
