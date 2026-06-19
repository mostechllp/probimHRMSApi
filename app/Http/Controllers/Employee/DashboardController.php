<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\Employee $employee */
        $employee = Auth::guard('employee')->user();
        $employee->load('department', 'company', 'designation');

        // Get own attendance logs for last 30 days
        $from = Carbon::now()->subDays(30)->startOfDay();
        $to = Carbon::now()->endOfDay();

        $attendanceLogs = AttendanceLog::where('userid', $employee->employee_id)
            ->whereBetween('log_date', [$from, $to])
            ->select(
                'log_date',
                'punch_in',
                'punch_out'
            )
            ->orderByDesc('log_date')
            ->get();

        $todayLog = AttendanceLog::where('userid', $employee->employee_id)
            ->whereDate('log_date', Carbon::today())
            ->select(
                'punch_in',
                'punch_out'
            )
            ->first();

        // Leave stats
        $totalLeavesTaken = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $leaveBalance = $employee->total_leaves_allocated - $totalLeavesTaken;

        // Punch Access Logic
        $today = Carbon::today()->format('Y-m-d');
        $canPunch = false;

        // 1. Check default designation punch access
        $designation = \App\Models\Designation::find($employee->designation_id);
        if ($designation && $designation->default_punch_access) {
            $canPunch = true;
        }

        // 2. Fallback to older designation string check just in case
        if ($employee->designation === 'Delivery Man' || $employee->designation === 'Salesperson') {
            $canPunch = true;
        }

        // 3. Check for approved WFH request today
        if (!$canPunch) {
            $wfh = \App\Models\WfhRequest::where('employee_id', $employee->id)
                ->whereDate('log_date', $today)
                ->where('status', 'Approved')
                ->exists();
            if ($wfh) {
                $canPunch = true;
            }
        }

        return view('employee.dashboard.index', compact('employee', 'attendanceLogs', 'todayLog', 'totalLeavesTaken', 'leaveBalance', 'canPunch'));
    }
}
