<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\User;
use App\Models\Party;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $organization_files = Document::with('shareWith')->where('type', 'organization')->latest()->get();
        $agreements = Document::with('shareWith')->where('type', 'agreement')->latest()->get();
        $others = Document::with('shareWith')->where('type', 'others')->latest()->get();
        $hr = Document::with('shareWith')->where('type', 'hr')->latest()->get();
        $folders = Document::select('folder')
            ->distinct()
            ->pluck('folder');
        $share_with = User::select('id', 'name')->get();
        $parties = Party::select('id', 'name')->get();
        $employees = Employee::with(['company', 'department'])
            ->get();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $getAttendanceStats = function ($date) {

            $totalEmployees = Employee::count();

            $logs = AttendanceLog::whereDate('log_date', $date)
                ->select(
                    'userid',
                    DB::raw('MIN(punch_in) as punch_in'),
                    DB::raw('MAX(punch_out) as punch_out')
                )
                ->groupBy('userid')
                ->get();

            $presentCount = $logs->count();

            $punchedIn = $logs->filter(
                function ($log) {
                    return Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
                }
            )->count();

            $punchedOut = $logs->filter(
                function ($log) {
                    return Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00';
                }
            )->count();

            $lateCount = $logs->filter(
                function ($log) {
                    $time = Carbon::parse($log->punch_in)->format('H:i:s');
                    return $time >= '08:11:00' && $time <= '12:00:00';
                }
            )->count();

            $absentCount = $totalEmployees - $presentCount;

            return [
                'punched_in' => $punchedIn,
                'punched_out' => $punchedOut,
                'late' => $lateCount,
                'absent' => $absentCount,
                'present' => $presentCount
            ];
        };

        $todayStats = $getAttendanceStats($today);
        $yesterdayStats = $getAttendanceStats($yesterday);

        // Punch chart data
        $punchChartData = [
            $todayStats['punched_in'],
            $todayStats['punched_out'],
            $yesterdayStats['punched_in'],
            $yesterdayStats['punched_out']
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

        return view('dashboard.index', compact(
            'organization_files',
            'agreements',
            'others',
            'hr',
            'folders',
            'employees',
            'share_with',
            'parties',
            'todayStats',
            'yesterdayStats',
            'punchChartData',
            'weeklyData',
            'weeklyLabels'
        ));
    }

    public function getStats()
    {
        $today = Carbon::today()->toDateString();

        $activeEmployees = Employee::count();
        $inactiveEmployees = Employee::onlyInactive()->count();
        $totalEmployees = $activeEmployees + $inactiveEmployees;

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            return Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
        })->count();

        $punchedOutCount = $todayLogs->filter(function ($log) {
            return Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00';
        })->count();

        $lateCount = $todayLogs->filter(function ($log) {
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            return $time >= '08:11:00' && $time <= '12:00:00';
        })->count();

        $absentCount = $activeEmployees - $punchedInCount;
        $notPunchedInCount = $absentCount; // Same logic as absent usually

        return response()->json([
            'total' => $totalEmployees,
            'active' => $activeEmployees,
            'inactive' => $inactiveEmployees,
            'punched_in' => $punchedInCount,
            'punched_out' => $punchedOutCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'not_punched_in' => $notPunchedInCount
        ]);
    }

    public function getChartData()
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
            ->whereRaw("TIME(punch_in) >= '08:11:00' AND TIME(punch_out) <= '12:00:00'")
            ->where('log_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Department-wise Distribution
        $deptDistribution = Employee::join('departments', 'employees.department_id', '=', 'departments.id')
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->get();

        return response()->json([
            'monthly' => [
                'labels' => $monthlyAttendance->pluck('month'),
                'data' => $monthlyAttendance->pluck('count')
            ],
            'late' => [
                'labels' => $lateTrend->pluck('date'),
                'data' => $lateTrend->pluck('count')
            ],
            'dept' => [
                'labels' => $deptDistribution->pluck('department'),
                'data' => $deptDistribution->pluck('count')
            ]
        ]);
    }

    public function getNotifications()
    {
        $notifications = auth()->user()->unreadNotifications;
        // auth()->user()->unreadNotifications->markAsRead();
        return response()->json($notifications);
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true
        ]);
    }
}
