<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
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

    /**
     * Admin: create a leave request on behalf of an employee.
     * Payload expected:
     *   employee_id, leave_type_id, start_date, end_date, reason,
     *   session1 (morning|afternoon), session2 (morning|afternoon),
     *   claim_salary (optional), year (optional, defaults to current year)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon', // session for start date
            'session2' => 'nullable|in:morning,afternoon', // session for end date
            'year' => 'nullable|integer',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaveType = LeaveType::find($request->leave_type_id);

        // -- Duration calculation based on session1 / session2 ------------------
        // session1 = session for start_date: 'morning' (from morning = full) | 'afternoon' (from afternoon = half)
        // session2 = session for end_date:   'morning' (until morning = half) | 'afternoon' (until afternoon = full)
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->input('session1', 'morning');   // default: full start day
        $session2 = $request->input('session2', 'afternoon'); // default: full end day

        $startContrib = ($session1 === 'morning') ? 1.0 : 0.5;
        $endContrib = ($session2 === 'afternoon') ? 1.0 : 0.5;

        $totalDays = $start->diffInDays($end);

        if ($totalDays === 0) {
            // Single-day leave
            if ($session1 === 'morning' && $session2 === 'afternoon') {
                $durationDays = 1.0; // full day
            } else {
                $durationDays = 0.5; // half day (morning only or afternoon only)
            }
        } else {
            // Multi-day leave: start contribution + middle full days + end contribution
            $middleDays = $totalDays - 1;
            $durationDays = $startContrib + $middleDays + $endContrib;
        }

        // Balance check
        $currentYear = $request->input('year', date('Y'));
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        if (!$allocation) {
            return $this->error("No leave allocation found for this leave type in {$currentYear}. Please contact HR.", 422);
        }

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. Employee has only {$remainingBalance} duration - {$durationDays} days remaining.", 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $request->input('session1', 'morning'),
            'session2' => $request->input('session2', 'afternoon'),
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return $this->success(
            $leave->load(['employee.user', 'leaveType']),
            'Leave request created successfully.',
            201
        );
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
