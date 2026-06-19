<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $employee_id = $request->get('employee_id');
        $perPage = $request->get('per_page', 15);

        $query = LeaveRequest::with(['employee.user', 'leaveType', 'approver'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($employee_id) {
            $query->where('employee_id', $employee_id);
        }

        $leaveRequests = $query->paginate($perPage);

        return $this->success($leaveRequests);
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->success($leaveRequest->load(['employee.user', 'leaveType', 'approver']));
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_remark' => 'nullable|string'
        ]);

        $leaveRequest->update([
            'status' => $request->status,
            'admin_remark' => $request->admin_remark,
            'approved_by' => auth('api')->id()
        ]);

        return $this->success($leaveRequest->load(['employee.user', 'leaveType', 'approver']), "Leave request {$request->status} successfully.");
    }
}
