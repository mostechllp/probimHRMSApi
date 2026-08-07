<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRequest;
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

        $requests = $query->latest()->get();

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
     */
    public function updateStatus(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $attendanceRequest->update([
            'status' => $request->status,
        ]);

        return $this->success($attendanceRequest, 'Request status updated successfully.');
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
