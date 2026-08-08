<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceRequestApiController extends ApiController
{
    /**
     * Display a listing of the employee's attendance requests.
     */
    public function index(): JsonResponse
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        $requests = AttendanceRequest::where('employee_id', $employee->id)
            ->latest()
            ->get();

        $requests->transform(function ($request) {
            $timezone = $request->timezone ?? 'Asia/Dubai';

            $tzAbbreviation = match ($timezone) {
                'Asia/Kolkata',
                'Asia/Calcutta',
                'IST',
                '+05:30',
                'UTC+05:30' => 'IST',
                'Asia/Dubai',
                'GST',
                '+04:00',
                'UTC+04:00' => 'GST',
                default => Carbon::now($timezone)->format('T'),
            };

            $request->request_time = $request->request_time
                ? Carbon::createFromFormat('H:i:s', $request->request_time)
                    ->format('h:i A') . " {$tzAbbreviation}"
                : '--';

            return $request;
        });

        return $this->success($requests);
    }

    /**
     * Store a new attendance request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:early_check_in,late_check_in,missed_punch_in,missed_punch_out',
            'request_date' => 'required|date',
            'request_time' => 'required',
            'reason' => 'required|string|max:1000',
            'timezone' => 'nullable|string',
        ]);

        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        // Check if date needs conversion if it's not Y-m-d, but usually API should send Y-m-d
        $date = $request->request_date;

        $existingRequest = AttendanceRequest::where('employee_id', $employee->id)
            ->where('request_date', $date)
            ->where('type', $request->type)
            ->first();

        if ($existingRequest) {
            return $this->error('You have already submitted a request for this date.', 400);
        }

        $attendanceRequest = AttendanceRequest::create([
            'employee_id' => $employee->id,
            'type' => $request->type,
            'request_date' => $date,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
            'timezone' => $request->timezone,
            'status' => 'pending'
        ]);

        return $this->success($attendanceRequest, 'Attendance request submitted successfully.', 201);
    }

    public function update(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $request->validate([
            'request_date' => 'required|date',
            'request_time' => 'required',
            'reason' => 'required|string',
            'timezone' => 'nullable|string',
            'type' => 'required|string'
        ]);

        $exists = AttendanceRequest::where('employee_id', $attendanceRequest->employee_id)
            ->where('request_date', $request->request_date)
            ->where('type', $request->type)
            ->where('id', '!=', $attendanceRequest->id)
            ->exists();

        if ($exists) {
            return $this->error(
                'An attendance request already exists for this date and type.',
                422
            );
        }

        $attendanceRequest->update([
            'request_date' => $request->request_date,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
        ]);

        return $this->success($attendanceRequest, 'Attendance request updated successfully.');
    }

    public function show(AttendanceRequest $attendanceRequest): JsonResponse
    {
        $attendanceRequest->load('employee');

        return $this->success($attendanceRequest);
    }

    /**
     * Remove the attendance request.
     */
    public function destroy(AttendanceRequest $attendanceRequest): JsonResponse
    {
        $attendanceRequest->delete();
        return $this->success(null, 'Attendance request deleted successfully.');
    }
}
