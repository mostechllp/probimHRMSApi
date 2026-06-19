<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\User;
use App\Models\Party;
use App\Models\Folder;
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
        $hr = Document::with('folder')
            ->where('type', 'hr')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });

        $agreements = Document::with('folder')
            ->where('type', 'agreements')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });

        $others = Document::with('folder')
            ->where('type', 'others')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });

        $organization_files = Document::with('folder')
            ->where('type', 'organization')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });

        $folders = Folder::all();

        $share_with = User::with(['employee', 'company', 'department'])->select('id', 'username')->get();
        $parties = Party::select('id', 'name')->get();
        $employees = Employee::with([
            'user.department',
            'user.designation',
            'user.company'
        ])
            ->whereHas('user', function ($query) {
                $query->where('type', '!=', 'admin')
                    ->where('status', 'active');
            })
            ->get();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayStats = $this->getAttendanceStatsByDate($today);
        $yesterdayStats = $this->getAttendanceStatsByDate($yesterday);

        $punchChartData = [
            'today' => [
                'punched_in' => $todayStats['punched_in'],
                'punched_out' => $todayStats['punched_out'],
            ],
            'yesterday' => [
                'punched_in' => $yesterdayStats['punched_in'],
                'punched_out' => $yesterdayStats['punched_out'],
            ]
        ];

        $weeklyData = [];
        $weeklyLabels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $count = AttendanceLog::whereDate('log_date', $date)
                ->distinct('userid')
                ->count('userid');

            $weeklyData[] = $count;
            $weeklyLabels[] = $date->format('D');
        }

        return $this->success([
            'stats' => [
                'today' => $todayStats,
                'yesterday' => $yesterdayStats,
            ],
            'charts' => [
                'punch_chart' => $punchChartData,
                'weekly_attendance' => [
                    'labels' => $weeklyLabels,
                    'data' => $weeklyData
                ]
            ],
            'recent_data' => [
                'organization_files' => $organization_files,
                'agreements' => $agreements,
                'others' => $others,
                'hr' => $hr,
                'employees' => $employees,
                'folders' => $folders,
            ],
            'metadata' => [
                'share_with' => $share_with,
                'parties' => $parties,
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
