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

        $query = LeaveRequest::with([
            'employee.user',
            'leaveType',
            'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name'
        ])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($employee_id) {
            $query->where('employee_id', $employee_id);
        }

        $leaveRequests = $query->paginate($perPage);

        $leaveRequests->getCollection()->transform(function ($leaveRequest) {

            $leaveRequest->applied_by = [
                'user_id' => $leaveRequest->appliedBy?->id,
                'employee_name' => $leaveRequest->appliedBy?->employee?->first_name . ' ' . $leaveRequest->appliedBy?->employee?->last_name,
                'role' => $leaveRequest->appliedBy?->role,
            ];

            $leaveRequest->unsetRelation('appliedBy');

            if (!empty($leaveRequest->document)) {
                $leaveRequest->document = asset('storage/' . ltrim($leaveRequest->document, '/'));
            }

            return $leaveRequest;
        });

        return $this->success($leaveRequests);
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->load(['employee.user', 'leaveType', 'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name', 'appliedBy']);

        $leaveRequest->applied_by = [
            'user_id' => $leaveRequest->appliedBy?->id,
            'employee_name' => $leaveRequest->appliedBy?->employee?->first_name . ' ' . $leaveRequest->appliedBy?->employee?->last_name,
            'role' => $leaveRequest->appliedBy?->role,
        ];

        $leaveRequest->unsetRelation('appliedBy');

        if (!empty($leaveRequest->document)) {
            $leaveRequest->document = asset('storage/' . ltrim($leaveRequest->document, '/'));
        }

        return $this->success($leaveRequest);
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

        // -- Duration calculation based on session1 / session2, excluding Sundays --
        // session1 = session for start_date: 'morning' (from morning = full) | 'afternoon' (from afternoon = half)
        // session2 = session for end_date:   'morning' (until morning = half) | 'afternoon' (until afternoon = full)
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->input('session1', 'morning');   // default: full start day
        $session2 = $request->input('session2', 'afternoon'); // default: full end day

        $durationDays = 0.0;
        $currentDate = $start->copy();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday()) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0; // full day
                } else {
                    $durationDays += 0.5; // half day (morning only or afternoon only)
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
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




    /**
     * Admin: update a leave request.
     * Only pending/rejected leaves can be edited by admin.
     */
    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
            'year' => 'nullable|integer',
        ]);

        if ($leaveRequest->status === 'approved') {
            return $this->error('Approved leave requests cannot be edited.', 400);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        // Duration calculation (excluding Sundays)
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->input('session1', 'morning');
        $session2 = $request->input('session2', 'afternoon');

        $durationDays = 0.0;
        $currentDate = $start->copy();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday()) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                $durationDays += ($session1 === 'morning' && $session2 === 'afternoon') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        // Balance check
        $currentYear = $request->input('year', date('Y'));
        $allocation = LeaveAllocation::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        if (!$allocation) {
            return $this->error("No leave allocation found for this leave type in {$currentYear}.", 422);
        }

        $leavesTaken = LeaveRequest::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->where('id', '!=', $leaveRequest->id)
            ->sum('duration_days');

        $remainingBalance = (float) $allocation->allocated_days - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. Employee has only {$remainingBalance} days remaining.", 422);
        }

        $documentPath = $leaveRequest->document;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $leaveRequest->update([
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $session1,
            'session2' => $session2,
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
        ]);

        $leaveRequest->load(['employee.user', 'leaveType', 'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name', 'appliedBy']);

        $leaveRequest->applied_by = [
            'user_id' => $leaveRequest->appliedBy?->id,
            'employee_name' => $leaveRequest->appliedBy?->employee?->first_name . ' ' . $leaveRequest->appliedBy?->employee?->last_name,
            'role' => $leaveRequest->appliedBy?->role,
        ];

        $leaveRequest->unsetRelation('appliedBy');

        if (!empty($leaveRequest->document)) {
            $leaveRequest->document = asset('storage/' . ltrim($leaveRequest->document, '/'));
        }

        return $this->success($leaveRequest, 'Leave request updated successfully.');
    }

    /**
     * Admin: delete a leave request.
     * Approved leaves cannot be deleted.
     */
    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status === 'approved') {
            return $this->error('Approved leave requests cannot be deleted.', 400);
        }

        $leaveRequest->delete();

        return $this->success(null, 'Leave request deleted successfully.');
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

        return $this->success($leaveRequest->load(['employee.user', 'leaveType', 'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name']), "Leave request {$request->status} successfully.");
    }

    private function parseMultipartPut(Request $request): void
    {
        if (!$request->isMethod('PUT') && !$request->isMethod('PATCH')) {
            return;
        }

        $contentType = $request->header('Content-Type');
        if (!$contentType || !str_contains($contentType, 'multipart/form-data')) {
            return;
        }

        preg_match('/boundary=(.*)$/', $contentType, $matches);
        if (empty($matches)) {
            return;
        }
        $boundary = $matches[1];

        $rawContent = $request->getContent();
        $parts = preg_split('/-+' . preg_quote($boundary, '/') . '/', $rawContent);

        $inputs = [];
        $files = [];

        foreach ($parts as $part) {
            if (empty(trim($part)) || $part === '--' || $part === "--\r\n") {
                continue;
            }

            $parts2 = explode("\r\n\r\n", $part, 2);
            if (count($parts2) < 2) {
                continue;
            }
            $headersStr = $parts2[0];
            $body = substr($parts2[1], 0, -2); // remove trailing \r\n

            preg_match('/name="([^"]+)"/', $headersStr, $nameMatch);
            if (empty($nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];

            preg_match('/filename="([^"]+)"/', $headersStr, $filenameMatch);
            if (!empty($filenameMatch)) {
                $filename = $filenameMatch[1];
                preg_match('/Content-Type:\s*([^\s;]+)/', $headersStr, $typeMatch);
                $mimeType = $typeMatch[1] ?? 'application/octet-stream';

                $tempPath = tempnam(sys_get_temp_dir(), 'laravel_upload_');
                file_put_contents($tempPath, $body);

                $files[$name] = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    $filename,
                    $mimeType,
                    null,
                    true // test mode
                );
            } else {
                $inputs[$name] = $body;
            }
        }

        $request->merge($inputs);
        $request->files->add($files);
    }
}
