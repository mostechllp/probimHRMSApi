<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveTypeApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $leaveTypes = LeaveType::latest()->get();
        return $this->success($leaveTypes);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name',
            'status' => 'boolean'
        ]);

        $leaveType = LeaveType::create([
            'name' => $request->name,
            'status' => $request->boolean('status', true),
        ]);

        return $this->success($leaveType, 'Leave type created successfully', 201);
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name,' . $leaveType->id,
            'status' => 'boolean'
        ]);

        $leaveType->update([
            'name' => $request->name,
            'status' => $request->boolean('status', $leaveType->status),
        ]);

        return $this->success($leaveType, 'Leave type updated successfully');
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->leaveRequests()->count() > 0) {
            return $this->error('Cannot delete leave type that has associated requests.', 422);
        }

        $leaveType->delete();
        return $this->success(null, 'Leave type deleted successfully');
    }

    public function updateStatus(Request $request, LeaveType $leaveType): JsonResponse
    {
        $request->validate([
            'status' => 'required|boolean'
        ]);

        $leaveType->update(['status' => $request->status]);
        return $this->success($leaveType, 'Status updated successfully');
    }
}
