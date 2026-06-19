<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendanceRequestApiController extends ApiController
{
    /**
     * Display a listing of all attendance requests.
     */
    public function index(): JsonResponse
    {
        $requests = AttendanceRequest::with('employee')->latest()->get();
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
            'request_time' => 'required|date_format:H:i',
            'reason' => 'required|string',
        ]);

        $attendanceRequest->update([
            'request_date' => $request->request_date,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
        ]);

        return $this->success($attendanceRequest, 'Attendance request updated successfully.');
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
