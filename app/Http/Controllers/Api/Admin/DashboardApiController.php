<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\User;
use App\Models\Party;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends ApiController
{
    /**
     * Get main dashboard statistics and data.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $thisWeekStart = Carbon::now()->startOfWeek()->toDateString();
        $thisWeekEnd = Carbon::now()->endOfWeek()->toDateString();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek()->toDateString();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek()->toDateString();

        // Active employees
        $activeEmployeesCount = Employee::whereHas('user', function ($q) {
            $q->where('status', 'active')->where('type', '!=', 'admin');
        })->count();

        // 1. TODAY'S STATUS
        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'))
            ->groupBy('userid')
            ->get();

        $punchedInToday = $todayLogs->filter(function ($log) {
            return !is_null($log->punch_in);
        });

        $onTimeCount = $punchedInToday->filter(function ($log) {
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            return $time < '08:11:00';
        })->count();

        $lateCount = $punchedInToday->filter(function ($log) {
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            return $time >= '08:11:00';
        })->count();

        $wfhCount = WfhRequest::where('status', 'approved')
            ->whereDate('date', $today)
            ->count();

        $leaveCount = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $presentTotal = $punchedInToday->count();
        $absentCount = max(0, $activeEmployeesCount - ($presentTotal + $wfhCount + $leaveCount));

        $todayStatus = [
            "On time" => $onTimeCount,
            "Late" => $lateCount,
            "Absent" => $absentCount,
            "WFH" => $wfhCount,
            "Leave" => $leaveCount,
            "punched_in" => $presentTotal,
        ];

        // 2. AVERAGE PUNCH-IN TIME
        $thisWeekLogs = AttendanceLog::whereBetween('log_date', [$thisWeekStart, $thisWeekEnd])
            ->whereNotNull('punch_in')
            ->get();

        $lastWeekLogs = AttendanceLog::whereBetween('log_date', [$lastWeekStart, $lastWeekEnd])
            ->whereNotNull('punch_in')
            ->get();

        $calcAvgTime = function ($logs) {
            if ($logs->isEmpty())
                return null;
            $totalMinutes = 0;
            foreach ($logs as $log) {
                $parts = explode(':', Carbon::parse($log->punch_in)->format('H:i'));
                $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
            }
            $avgMinutes = (int) round($totalMinutes / $logs->count());
            return sprintf('%02d:%02d', intdiv($avgMinutes, 60), $avgMinutes % 60);
        };

        $thisWeekAvg = $calcAvgTime($thisWeekLogs) ?? '00:00';
        $lastWeekAvg = $calcAvgTime($lastWeekLogs) ?? '00:00';

        $trend = "Same as last week";
        if ($thisWeekAvg !== '00:00' && $lastWeekAvg !== '00:00') {
            $thisParts = explode(':', $thisWeekAvg);
            $lastParts = explode(':', $lastWeekAvg);
            $thisMins = (int) $thisParts[0] * 60 + (int) $thisParts[1];
            $lastMins = (int) $lastParts[0] * 60 + (int) $lastParts[1];

            if ($thisMins < $lastMins) {
                $trend = ($lastMins - $thisMins) . " min earlier than last week";
            } elseif ($thisMins > $lastMins) {
                $trend = ($thisMins - $lastMins) . " min later than last week";
            }
        }

        $dailyAvg = [];
        $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        foreach ($daysOfWeek as $i => $dayName) {
            $dayDate = Carbon::now()->startOfWeek()->addDays($i)->toDateString();
            $dayLogs = $thisWeekLogs->where('log_date', $dayDate);
            $avgStr = $calcAvgTime($dayLogs);
            $val = 0.0;
            if ($avgStr) {
                $parts = explode(':', $avgStr);
                $val = (int) $parts[0] + ((int) $parts[1] / 60);
            }
            $dailyAvg[] = ["day" => $dayName, "value" => round($val, 2)];
        }

        $avgPunchTime = [
            "this_week_avg" => $thisWeekAvg,
            "trend" => $trend,
            "daily" => $dailyAvg
        ];

        // 3. RECENT PUNCH-INS
        $recentPunchLogs = AttendanceLog::with('user.employee')
            ->whereDate('log_date', $today)
            ->whereNotNull('punch_in')
            ->orderBy('punch_in', 'desc')
            ->take(7)
            ->get();

        $recentPunches = $recentPunchLogs->map(function ($log) {
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            $status = $time < '08:11:00' ? 'on_time' : 'late';

            $name = 'Unknown';
            if ($log->user && $log->user->employee) {
                $emp = $log->user->employee;
                $name = trim($emp->first_name . ' ' . $emp->last_name);
            } elseif ($log->user) {
                $name = $log->user->username ?? 'Unknown';
            }

            return [
                "name" => $name,
                "time" => Carbon::parse($log->punch_in)->format('H:i'),
                "status" => $status
            ];
        })->toArray();

        // 4. PUNCH-IN DISTRIBUTION
        $distribution = [
            "8:00" => 0,
            "8:30" => 0,
            "9:00" => 0,
            "9:30" => 0,
            "10:00" => 0,
            "10:30" => 0,
            "11:00" => 0
        ];

        foreach ($punchedInToday as $log) {
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            if ($time < '08:30:00') {
                $distribution["8:00"]++;
            } elseif ($time < '09:00:00') {
                $distribution["8:30"]++;
            } elseif ($time < '09:30:00') {
                $distribution["9:00"]++;
            } elseif ($time < '10:00:00') {
                $distribution["9:30"]++;
            } elseif ($time < '10:30:00') {
                $distribution["10:00"]++;
            } elseif ($time < '11:00:00') {
                $distribution["10:30"]++;
            } else {
                $distribution["11:00"]++;
            }
        }

        $punchDistribution = [];
        foreach ($distribution as $label => $val) {
            $punchDistribution[] = ["label" => $label, "value" => $val];
        }

        // 5. PROJECT STATS
        $totalProjects = Project::count();
        $activeProjects = $totalProjects; // Assuming all projects are active
        $totalAssignments = DB::table('employee_project')->whereNull('deleted_at')->count();
        $employeesAssigned = DB::table('employee_project')->whereNull('deleted_at')->distinct('employee_id')->count('employee_id');

        $projectStats = [
            "total_projects" => $totalProjects,
            "active_projects" => $activeProjects,
            "total_assignments" => $totalAssignments,
            "employees_assigned" => $employeesAssigned
        ];

        // 6. PROJECT ALLOCATION
        $projectAllocationData = DB::table('employee_project')
            ->join('projects', 'employee_project.project_id', '=', 'projects.id')
            ->whereNull('employee_project.deleted_at')
            ->select('projects.name', DB::raw('count(employee_project.employee_id) as employees'))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('employees')
            ->take(8)
            ->get();

        $projectAllocation = $projectAllocationData->map(function ($item) {
            return ["name" => $item->name, "employees" => (int) $item->employees];
        })->toArray();

        // 7. PROJECT HOURS
        $projectHoursData = ProjectTimeLog::join('projects', 'project_time_logs.project_id', '=', 'projects.id')
            ->select('projects.name', DB::raw('SUM(time_taken_minutes) as total_minutes'))
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('total_minutes')
            ->take(8)
            ->get();

        $projectHours = $projectHoursData->map(function ($item) {
            return ["name" => $item->name, "hours" => round($item->total_minutes / 60)];
        })->toArray();

        // 8. WEEKLY ATTENDANCE
        $weeklyLabels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        $presentArray = [];
        $leaveArray = [];

        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->startOfWeek()->addDays($i)->toDateString();

            $present = AttendanceLog::whereDate('log_date', $date)
                ->whereNotNull('punch_in')
                ->distinct('userid')
                ->count('userid');

            $leave = LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->count();

            $presentArray[] = $present;
            $leaveArray[] = $leave;
        }

        $weeklyAttendance = [
            "labels" => $weeklyLabels,
            "present" => $presentArray,
            "leave" => $leaveArray
        ];

        return $this->success([
            "data" => [
                "today_status" => $todayStatus,
                "avg_punch_time" => $avgPunchTime,
                "recent_punches" => $recentPunches,
                "punch_distribution" => $punchDistribution,
                "project_stats" => $projectStats,
                "project_allocation" => $projectAllocation,
                "project_hours" => $projectHours,
                "weekly_attendance" => $weeklyAttendance
            ]
        ]);
    }

    /**
     * Get summary statistics for the dashboard cards.
     */
    public function getSummaryStats(): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $activeEmployees = Employee::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->count();
        $inactiveEmployees = Employee::whereHas('user', function ($q) {
            $q->where('status', 'inactive');
        })->count();
        $totalEmployees = $activeEmployees + $inactiveEmployees;

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn <= '12:00:00';
        })->count();

        $punchedOutCount = $todayLogs->filter(function ($log) {
            $punchOut = $log->punch_out ? Carbon::parse($log->punch_out)->format('H:i:s') : null;
            return $punchOut && $punchOut >= '12:00:00';
        })->count();

        $lateCount = $todayLogs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn >= '08:11:00' && $punchIn <= '12:00:00';
        })->count();

        $absentCount = $activeEmployees - $punchedInCount;

        return $this->success([
            'employees' => [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'inactive' => $inactiveEmployees,
            ],
            'attendance_today' => [
                'punched_in' => $punchedInCount,
                'punched_out' => $punchedOutCount,
                'late' => $lateCount,
                'absent' => $absentCount > 0 ? $absentCount : 0,
            ]
        ]);
    }

    /**
     * Get complex chart data.
     */
    public function getDetailedChartData(): JsonResponse
    {
        // Monthly Attendance (Last 6 months)
        $monthlyAttendance = AttendanceLog::select(
            DB::raw("DATE_FORMAT(log_date, '%b %Y') as month"),
            DB::raw("count(DISTINCT userid, DATE(log_date)) as count")
        )
            ->where('log_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy(DB::raw('MIN(log_date)'))
            ->get();

        // Late Employees Trend (Last 30 days)
        $lateTrend = DB::table('attendance_logs')
            ->select(DB::raw("DATE(log_date) as date"), DB::raw("count(*) as count"))
            ->whereRaw("TIME(punch_in) >= '08:11:00' AND TIME(punch_in) <= '12:00:00'")
            ->where('log_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Department-wise Distribution
        $deptDistribution = Employee::join('users', 'employees.user_id', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->get();

        return $this->success([
            'monthly_attendance' => [
                'labels' => $monthlyAttendance->pluck('month'),
                'data' => $monthlyAttendance->pluck('count')
            ],
            'late_trend' => [
                'labels' => $lateTrend->pluck('date'),
                'data' => $lateTrend->pluck('count')
            ],
            'department_distribution' => [
                'labels' => $deptDistribution->pluck('department'),
                'data' => $deptDistribution->pluck('count')
            ]
        ]);
    }

    /**
     * Get user notifications.
     */
    public function getNotifications(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('User not found', 401);
        }

        $notifications = $user->unreadNotifications;
        return $this->success($notifications);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead($id): JsonResponse
    {
        $notification = auth()->user()
            ->notifications()
            ->find($id);

        if ($notification) {
            $notification->markAsRead();
            return $this->success(null, 'Notification marked as read');
        }

        return $this->error('Notification not found', 404);
    }

    /**
     * Helper to get attendance stats by date.
     */
    private function getAttendanceStatsByDate($date): array
    {
        $totalEmployees = Employee::whereHas('user', function ($query) {
            $query->where('status', 'active')
                ->where('type', '!=', 'admin');
        })->count();

        $logs = AttendanceLog::whereDate('log_date', $date)
            ->select(
                'userid',
                DB::raw('MIN(punch_in) as punch_in'),
                DB::raw('MAX(punch_out) as punch_out')
            )
            ->groupBy('userid')
            ->get();

        $presentCount = $logs->count();

        $punchedIn = $logs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn <= '12:00:00';
        })->count();

        $punchedOut = $logs->filter(function ($log) {
            $punchOut = $log->punch_out ? Carbon::parse($log->punch_out)->format('H:i:s') : null;
            return $punchOut && $punchOut >= '12:00:00';
        })->count();

        $lateCount = $logs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn >= '08:11:00' && $punchIn <= '12:00:00';
        })->count();

        $absentCount = $totalEmployees - $presentCount;

        return [
            'punched_in' => $punchedIn,
            'punched_out' => $punchedOut,
            'late' => $lateCount,
            'absent' => $absentCount > 0 ? $absentCount : 0,
            'present' => $presentCount
        ];
    }
}
