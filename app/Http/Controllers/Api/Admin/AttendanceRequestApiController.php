<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRequest;
use App\Models\AttendanceLog;
use App\Models\ProjectTimeLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceRequestApiController extends ApiController
{
    /**
     * Display a listing of all attendance requests.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceRequest::with('employee');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $requests = $query
            ->orderByRaw("status = 'pending' DESC")
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
     * Update the status of the request.
     *
     * Type-based approval logic:
     *  - late_check_in   : status update only, no attendance log changes.
     *  - early_check_in  : status update only, no attendance log changes.
     *  - missed_punch_in : create/update attendance log with punch-in (and
     *                      optionally punch-out) times, then insert project
     *                      time logs if present.
     *                        • created_by = admin   → use request_time as punch-in.
     *                        • created_by = employee → use the punch_in_time entered
     *                          by the employee in the request.
     *  - missed_punch_out: find the existing attendance log for that date and
     *                      update only the punch_out with request_time.
     */
    public function updateStatus(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $attendanceRequest->update([
            'status' => $request->status,
        ]);

        // Only perform attendance-related side-effects on approval
        if ($request->status !== 'approved') {
            return $this->success(
                $attendanceRequest,
                'Request status updated successfully.'
            );
        }

        $type = $attendanceRequest->type;
        $employee = $attendanceRequest->employee;

        // Types that only need a status change — nothing else to do
        if (in_array($type, ['early_check_in', 'late_check_in'])) {
            if ($attendanceRequest->created_by !== 'admin') {
                return $this->success(
                    $attendanceRequest,
                    'Request status updated successfully.'
                );
            }
        }

        if (!$employee || !$employee->user_id) {
            return $this->success(
                $attendanceRequest,
                'Request status updated successfully.'
            );
        }

        $timezone = $attendanceRequest->timezone ?: config('app.timezone');

        /*
        |--------------------------------------------------------------------------
        | late_check_in — create / update attendance log + project time logs
        |--------------------------------------------------------------------------
        */

        if ($type === 'late_check_in' || $type === 'missed_punch_in') {

            $log = AttendanceLog::firstOrNew([
                'userid' => $employee->user_id,
                'log_date' => $attendanceRequest->request_date,
            ]);

            /*
            | Determine punch-in time
            |   - Admin-created → use request_time (the time admin recorded)
            |   - Employee-created → use punch_in_time entered by the employee
            */
            if ($attendanceRequest->created_by === 'admin') {
                $punchInDateTime = Carbon::parse(
                    $attendanceRequest->request_date . ' ' . $attendanceRequest->request_time,
                    $timezone
                );
            } else {
                // Employee-created: punch_in_time holds the time the employee requested
                $punchInDateTime = Carbon::parse(
                    $attendanceRequest->request_date . ' ' . $attendanceRequest->punch_in_time,
                    $timezone
                );
            }

            $log->punch_in = $punchInDateTime;

            // Save punch-in location for admin-created requests
            if ($attendanceRequest->created_by === 'admin') {
                $location = $attendanceRequest->location ?? [];
                $log->punch_in_latitude  = $location['latitude']  ?? null;
                $log->punch_in_longitude = $location['longitude'] ?? null;
                $log->punch_in_address   = $location['address']   ?? null;
                $log->timezone   = $attendanceRequest->timezone   ?? null;
                $log->work_location   = $attendanceRequest->work_location   ?? null;
            }

            // Optionally set punch-out if provided
            if ($attendanceRequest->punch_out_time) {
                $punchOutDateTime = Carbon::parse(
                    $attendanceRequest->request_date . ' ' . $attendanceRequest->punch_out_time,
                    $timezone
                );
                $log->punch_out = $punchOutDateTime;
            }

            $log->status = 1;
            $log->timezone = $timezone;

            if ($log->punch_out) {
                $log->log_status = 'out';
            } else {
                $log->log_status = $log->log_status ?: 'in';
            }

            // Calculate working hours when both ends are present
            if ($log->punch_in && $log->punch_out) {
                $log->working_hours = max(
                    0,
                    Carbon::parse($log->punch_in, $timezone)
                        ->diffInMinutes(Carbon::parse($log->punch_out, $timezone))
                );
            }

            $log->save();

            /*
            |------------------------------------------------------------------
            | Project Time Logs
            |------------------------------------------------------------------
            */

            $projectTimes = $attendanceRequest->project_times ?? [];

            if (is_string($projectTimes)) {
                $projectTimes = json_decode($projectTimes, true) ?? [];
            }

            foreach ($projectTimes as $projectTime) {

                if (
                    !isset($projectTime['project_id']) ||
                    !isset($projectTime['time_minutes'])
                ) {
                    continue;
                }

                ProjectTimeLog::updateOrCreate(
                    [
                        'user_id' => $employee->user_id,
                        'project_id' => $projectTime['project_id'],
                        'date' => $attendanceRequest->request_date,
                    ],
                    [
                        'time_taken_minutes' => $projectTime['time_minutes'],
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | missed_punch_out — update punch_out on existing log for that date
        |--------------------------------------------------------------------------
        */

        if ($type === 'missed_punch_out') {

            $log = AttendanceLog::where('userid', $employee->user_id)
                ->where('log_date', $attendanceRequest->request_date)
                ->whereNotNull('punch_in')
                ->first();

            if ($log) {
                $punchOutDateTime = Carbon::parse(
                    $attendanceRequest->request_date . ' ' . $attendanceRequest->request_time,
                    $timezone
                );

                $log->punch_out = $punchOutDateTime;
                $log->log_status = 'OUT';
                $log->timezone = $timezone;

                // Save punch-out location for admin-created requests
                if ($attendanceRequest->created_by === 'admin') {
                    $location = $attendanceRequest->location ?? [];
                    $log->punch_out_latitude  = $location['latitude']  ?? null;
                    $log->punch_out_longitude = $location['longitude'] ?? null;
                    $log->punch_out_address   = $location['address']   ?? null;
                }

                // Recalculate working hours
                if ($log->punch_in) {
                    $log->working_hours = max(
                        0,
                        Carbon::parse($log->punch_in, $timezone)
                            ->diffInMinutes($punchOutDateTime)
                    );
                }

                $log->save();
            }
        }

        return $this->success(
            $attendanceRequest,
            'Request status updated successfully.'
        );
    }

    /**
     * Update the attendance request details.
     */
    public function update(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $request->validate([
            'request_date' => 'required|date',
            'request_time' => 'required',
            'reason' => 'required|string',
            'timezone' => 'nullable|string',
        ]);

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
