<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkingHour;
use App\Models\User;
use App\Models\AttendanceUpload;
use App\Jobs\ProcessAttendanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class AttendanceApiController extends ApiController
{
    /**
     * Get Attendance Summary with Stats
     */

    // public function index(Request $request): JsonResponse
    // {
    //     $perPage = 100;
    //     $companyId = $request->get('company_id');
    //     $employeeName = $request->get('employee_name');
    //     $datePreset = $request->get('date_preset', 'all');

    //     $query = AttendanceLog::with(['company', 'user.employee', 'user.department'])
    //         ->select(
    //             'id',
    //             'company_id',
    //             'userid',
    //             'log_date',
    //             'working_hours',
    //             'punch_in_latitude',
    //             'punch_in_longitude',
    //             'punch_in_address',
    //             'punch_out_latitude',
    //             'punch_out_longitude',
    //             'punch_out_address',
    //             'punch_in',
    //             'punch_out'
    //         );

    //     if ($companyId) {
    //         $query->where('company_id', $companyId);
    //     }

    //     if ($employeeName) {
    //         $query->whereHas('user.employee', function ($q) use ($employeeName) {
    //             $q->where('first_name', 'like', "%$employeeName%")
    //                 ->orWhere('last_name', 'like', "%$employeeName%");
    //         });
    //     }

    //     if ($datePreset != 'all') {
    //         $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));
    //     }


    //     $attendance = $query->groupBy(
    //         'id',
    //         'company_id',
    //         'userid',
    //         'log_date',
    //         'working_hours',
    //         'punch_in_latitude',
    //         'punch_in_longitude',
    //         'punch_in_address',
    //         'punch_out_latitude',
    //         'punch_out_longitude',
    //         'punch_out_address',
    //     )
    //         ->orderBy('log_date', 'desc')
    //         ->paginate($perPage);

    //     $attendance->getCollection()->transform(function ($log) {
    //         $tz = config('app.timezone', 'Asia/Dubai');

    //         // Format date to dd/mm/yyyy
    //         $log->log_date = $log->log_date
    //             ? Carbon::parse($log->log_date)->format('d/m/Y')
    //             : '--';

    //         $log->punch_in = $log->punch_in
    //             ? Carbon::parse($log->punch_in)->setTimezone($tz)->format('h:i A')
    //             : '--';
    //         // Output → "08 Jun 2026, 07:29 AM"

    //         $log->punch_out = $log->punch_out
    //             ? Carbon::parse($log->punch_out)->setTimezone($tz)->format('h:i A')
    //             : '--';
    //         // Output → "08 Jun 2026, 12:32 PM"

    //         // Calculate working_hours dynamically if it is 0 in DB (to fix past data)
    //         $minutes = $log->working_hours ?? 0;

    //         if ($minutes == 0 && $log->getRawOriginal('punch_in') && $log->getRawOriginal('punch_out')) {
    //             $in = Carbon::parse($log->getRawOriginal('punch_in'));
    //             $out = Carbon::parse($log->getRawOriginal('punch_out'));
    //             $minutes = max(0, $in->diffInMinutes($out));
    //         }

    //         $hours = intdiv($minutes, 60);
    //         $mins = $minutes % 60;

    //         if ($minutes == 0)
    //             $log->working_hours = '--';
    //         elseif ($hours == 0)
    //             $log->working_hours = "{$mins} mins";
    //         elseif ($mins == 0)
    //             $log->working_hours = "{$hours} hrs";
    //         else
    //             $log->working_hours = "{$hours} hrs {$mins} mins";

    //         return $log;
    //     });

    //     return $this->success([
    //         'attendance' => $attendance,
    //         'stats' => $this->getStats()
    //     ]);
    // }

    public function index(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $month = $request->get('month');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 500);

        // Get start and end dates
        [$startDate, $endDate] = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date'),
            $month
        );

        // Employees query
        $employeesQuery = Employee::with([
            'user.company',
            'user.department',
            'user.designation'
        ])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->where('type', 'employee');
            });

        // Filter by employee ID
        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('employee_id', $employeeId);
        }

        // Filter by department
        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->whereHas('user', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            });
        }

        // Search
        if ($search) {
            $employeesQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->get();

        if ($employees->isEmpty()) {
            return $this->success([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => (int) $perPage,
                    'current_page' => 1,
                    'last_page' => 0
                ]
            ]);
        }

        $userIds = $employees->pluck('user_id')->toArray();

        // Fetch working hour configuration keyed by lowercase day name
        $workingHours = WorkingHour::all()->keyBy(fn($wh) => strtolower($wh->day));

        // Attendance logs
        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $userIds)
            ->orderBy('log_date')
            ->get()
            ->groupBy(['log_date', 'userid']);

        $reportData = [];

        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate->lte($lastDate)) {

            $date = $currentDate->toDateString();
            $dayLogs = $allLogs->get($date, collect());

            // Determine standard hours for this day from WorkingHour config
            $dayName = strtolower($currentDate->format('l')); // e.g. 'monday'
            $workingHourConfig = $workingHours->get($dayName);
            $standardHours = 8; // default fallback
            if ($workingHourConfig && $workingHourConfig->is_enabled && $workingHourConfig->start_time && $workingHourConfig->end_time) {
                $configStart = Carbon::createFromTimeString($workingHourConfig->start_time);
                $configEnd = Carbon::createFromTimeString($workingHourConfig->end_time);
                $standardHours = round($configStart->diffInMinutes($configEnd) / 60, 2);
            }

            foreach ($employees as $employee) {

                $attendance = $dayLogs->get($employee->user_id)?->first();

                $punchIn = $attendance?->punch_in;
                $punchOut = $attendance?->punch_out;

                $workedHours = 0;
                $overtimeMinutes = 0;

                if ($punchIn) {
                    $status = 'Present';
                } else {
                    $status = 'Absent';
                }

                if ($punchIn && $punchOut) {

                    $punchInTime = Carbon::parse($punchIn);
                    $punchOutTime = Carbon::parse($punchOut);

                    $workedMinutes = $punchInTime->diffInMinutes($punchOutTime);
                    $workedHours = round($workedMinutes / 60, 2);

                    if ($workedHours >= 8) {

                        $status = 'Full Day';

                    } elseif ($workedHours >= 4) {

                        $status = 'Half Day';

                    } else {

                        $status = 'Absent';
                    }

                    // Overtime in minutes beyond the standard configured hours
                    $overtimeMinutes = max(0, (int) round(($workedHours - $standardHours) * 60));
                }

                $reportData[] = [
                    'employee_id' => $employee->employee_id,
                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                    'department' => $employee->user->department->name ?? 'N/A',
                    'designation' => $employee->user->designation->name ?? 'N/A',
                    'company' => $employee->user->company->name ?? 'N/A',
                    'date' => $date,
                    'punch_in' => $punchIn
                        ? Carbon::parse($punchIn)->format('h:i A')
                        : '-',
                    'punch_out' => $punchOut
                        ? Carbon::parse($punchOut)->format('h:i A')
                        : '-',
                    'worked_hours' => $workedHours,
                    'standard_hours' => $standardHours,
                    'overtime' => $this->formatOvertimeMinutes($overtimeMinutes),
                    'status' => $status,
                ];
            }

            $currentDate->addDay();
        }

        // Sort by latest date first
        usort($reportData, function ($a, $b) {

            if ($a['date'] === $b['date']) {
                return strcmp($a['employee_id'], $b['employee_id']);
            }

            return strcmp($b['date'], $a['date']);
        });

        // Pagination
        $currentPage = (int) $request->get('page', 1);

        $total = count($reportData);

        $paginatedItems = array_slice(
            $reportData,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        return $this->success([
            'data' => array_values($paginatedItems),
            'meta' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => $currentPage,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        // Get all active, non-admin employees
        $employees = Employee::with('user')->whereHas('user', function ($query) {
            $query->where('status', 'active')->where('type', '!=', 'admin');
        })->get();

        // Get today's attendance logs
        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get()
            ->keyBy('userid'); // assuming userid in AttendanceLog is employee_id (string) or user_id (int). Based on codebase, it's usually employee_id or user_id. Wait, AttendanceLog uses user_id or employee_id?
            // In DashboardApiController: $todayLogs = AttendanceLog::whereDate('log_date', $today)... ->groupBy('userid')->get();
            // Let's assume userid maps to user_id or employee_id. The previous code uses it.
            // Let's key by userid to easily check.

        $result = [
            'total' => ['count' => 0, 'employees' => []],
            'punched_in' => ['count' => 0, 'employees' => []],
            'punched_out' => ['count' => 0, 'employees' => []],
            'absent' => ['count' => 0, 'employees' => []],
            'late' => ['count' => 0, 'employees' => []],
        ];

        foreach ($employees as $employee) {
            // Need to know what 'userid' in AttendanceLog refers to.
            // In Employee model, attendanceLogs() has 'userid' referencing 'employee_id'.
            // In DashboardApiController: userid is used. Let's use both user_id and employee_id to be safe, or just employee_id based on the model relation.
            // Actually, in DashboardApiController: $activeEmployees - $punchedInCount. So it just uses count.
            // Let's check employee->employee_id or employee->user_id against log->userid.
            $log = $todayLogs->get($employee->employee_id) ?? $todayLogs->get($employee->user_id);

            $empData = [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'user_id' => $employee->user_id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'email' => $employee->user ? $employee->user->email : null,
            ];

            $result['total']['count']++;
            $result['total']['employees'][] = $empData;

            if ($log && $log->punch_in) {
                // Punched in
                $result['punched_in']['count']++;
                $result['punched_in']['employees'][] = $empData;

                $punchInTime = Carbon::parse($log->punch_in)->format('H:i:s');
                if ($punchInTime >= '08:11:00' && $punchInTime <= '12:00:00') {
                    $result['late']['count']++;
                    $result['late']['employees'][] = $empData;
                }

                if ($log->punch_out && Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00') {
                    $result['punched_out']['count']++;
                    $result['punched_out']['employees'][] = $empData;
                }
            } else {
                // Absent
                $result['absent']['count']++;
                $result['absent']['employees'][] = $empData;
            }
        }

        return $this->success($result);
    }

    /**
     * Upload Attendance File
     */

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:dat,csv,txt|max:2048',
            // 'company_id' => 'required|exists:companies,id'
        ]);

        try {
            $file = $request->file('file');

            // Ensure directory exists
            if (!Storage::disk('private')->exists('attendance')) {
                Storage::disk('private')->makeDirectory('attendance');
            }

            // Store file
            $path = $file->store('attendance', 'private');

            // Double check file actually exists
            if (!Storage::disk('private')->exists($path)) {
                Log::error('File not stored properly: ' . $path);
                return response()->json([
                    'message' => 'File upload failed'
                ], 500);
            }

            // Save in DB
            $upload = AttendanceUpload::create([
                'file_path' => $path,
                'status' => 'pending',
                'progress' => 0
            ]);

            $extension = $file->getClientOriginalExtension();

            if (in_array($extension, ['xlsx', 'csv'])) {
                // Direct import for Excel/CSV
                Excel::import(new \App\Imports\AttendanceImport($upload->id), $path, 'private');
                $upload->update(['status' => 'completed', 'progress' => 100]);

                return $this->success($upload, 'Attendance imported successfully');
            }
            // Dispatch background job for .dat/.txt
            ProcessAttendanceJob::dispatch($upload->id);

            return $this->success($upload, 'Attendance file uploaded and processing started');
        } catch (\Exception $e) {
            Log::error('Upload Error: ' . $e->getMessage());
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check Upload Progress
     */
    public function uploadStatus($id): JsonResponse
    {
        $upload = AttendanceUpload::find($id);
        if (!$upload)
            return $this->error('Upload record not found', 404);
        return $this->success($upload);
    }

    /**
     * Get Punch-In Today
     */
    public function punchInToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_in');
    }

    /**
     * Get Punch-In Yesterday
     */
    public function punchInYesterday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'yesterday', 'punch_in');
    }

    /**
     * Get Punch-Out Today
     */
    public function punchOutToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_out');
    }

    /**
     * Get Late Comers
     * Dynamically checks against the WorkingHour start_time per day.
     */
    public function lateComers(Request $request): JsonResponse
    {
        $perPage  = (int) $request->get('per_page', 15);
        $page     = (int) $request->get('page', 1);
        $datePreset = $request->get('date_preset', 'today');

        // Load working hours keyed by lowercase day name
        $workingHours = WorkingHour::all()->keyBy(fn($wh) => strtolower($wh->day));

        // Build base attendance query
        $query = AttendanceLog::with(['user.employee', 'user.department', 'user.designation'])
            ->whereNotNull('punch_in')
            ->select('company_id', 'userid', 'log_date', 'punch_in', 'punch_out')
            ->orderBy('log_date', 'desc');

        $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Fetch all matching logs, then filter by dynamic per-day start_time
        $allLogs = $query->get();

        $lateComers = $allLogs->filter(function ($log) use ($workingHours) {
            $dayName = strtolower(Carbon::parse($log->log_date)->format('l')); // e.g. 'monday'
            $config  = $workingHours->get($dayName);

            // Skip days that have no config or are disabled
            if (!$config || !$config->is_enabled || !$config->start_time) {
                return false;
            }

            $punchInTime  = Carbon::parse($log->punch_in)->format('H:i:s');
            $configStart  = Carbon::createFromTimeString($config->start_time)->format('H:i:s');
            $noonCutoff   = '12:00:00';

            // Late = punched in after the configured start time but before noon
            return $punchInTime > $configStart && $punchInTime <= $noonCutoff;
        })->map(function ($log) {
            $employee = $log->user?->employee ?? null;

            return [
                'id' => $employee->id,
                'user_id' => $employee?->user_id ?? null,
                'employee_id'  => $employee?->employee_id ?? null,
                'name'         => $employee
                    ? trim($employee->first_name . ' ' . $employee->last_name)
                    : ($log->user?->name ?? 'Unknown'),
                'department'   => $log->user?->department?->name ?? 'N/A',
                'designation'  => $log->user?->designation?->name ?? 'N/A',
                'company_id'   => $log->company_id,
                'log_date'     => $log->log_date,
                'punch_in'     => Carbon::parse($log->punch_in)->format('h:i A'),
                'punch_out'    => $log->punch_out
                    ? Carbon::parse($log->punch_out)->format('h:i A')
                    : '-',
            ];
        })->values();

        // Manual pagination
        $total          = $lateComers->count();
        $paginatedItems = $lateComers->forPage($page, $perPage)->values();

        return $this->success([
            'data' => $paginatedItems,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get Absentees
     */
    public function absentees(Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $companyId = $request->get('company_id');

        $presentUserIds = AttendanceLog::whereDate('log_date', $date)
            ->pluck('userid')
            ->unique();

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->whereNotIn('employee_id', $presentUserIds)
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
                $q->where('type', '!=', 'admin');
            });

        if ($companyId) {
            $query->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $this->success($query->paginate($request->get('per_page', 15)));
    }

    /**
     * Private Helpers
     */

    private function getFilteredAttendance(Request $request, $day, $type): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $date = ($day === 'today') ? Carbon::today()->toDateString() : Carbon::yesterday()->toDateString();

        $query = AttendanceLog::with(['company', 'user'])
            ->whereDate('log_date', $date)
            ->select(
                'company_id',
                'userid',
                'log_date',
                DB::raw("MIN(punch_in) as punch_in"),
                DB::raw("MAX(punch_out) as punch_out")
            );

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($type === 'punch_out') {
            $query->havingRaw("TIME(MAX(punch_out)) >= '12:00:00'");
        } else {
            $query->havingRaw("TIME(MIN(punch_in)) <= '12:00:00'");
        }

        return $this->success($query->groupBy('company_id', 'userid', 'log_date')->paginate($perPage));
    }

    private function applyDateFilter($query, $preset, $from = null, $to = null)
    {
        $now = Carbon::now();
        switch ($preset) {
            case 'today':
                $query->whereDate('log_date', $now->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('log_date', $now->subDay()->toDateString());
                break;
            case 'last_week':
                $query->whereBetween('log_date', [$now->subWeek()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'last_month':
                $query->whereBetween('log_date', [$now->subMonth()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('log_date', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay()
                    ]);
                }
                break;
        }
    }

    // private function getStats(): array
    // {
    //     $today = Carbon::today()->toDateString();
    //     $activeEmployeesCount = User::where('status', 'active')->count();

    //     $todayLogs = AttendanceLog::whereDate('log_date', $today)
    //         ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
    //         ->groupBy('userid')
    //         ->get();

    //     $punchedInCount = $todayLogs->filter(function ($log) {
    //         return $log->punch_in && Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
    //     })->count();

    //     $punchedLateCount = $todayLogs->filter(function ($log) {
    //         if (!$log->punch_in)
    //             return false;
    //         $time = Carbon::parse($log->punch_in)->format('H:i:s');
    //         return $time > '08:10:59' && $time <= '12:00:00';
    //     })->count();

    //     return [
    //         'total_active_employees' => $activeEmployeesCount,
    //         'present_today' => $todayLogs->count(),
    //         'absent_today' => max(0, $activeEmployeesCount - $todayLogs->count()),
    //         'punched_in_on_time' => $punchedInCount - $punchedLateCount,
    //         'punched_late' => $punchedLateCount,
    //         'punched_out_today' => $todayLogs->filter(fn($l) => $l->punch_out && Carbon::parse($l->punch_out)->format('H:i:s') >= '12:00:00')->count()
    //     ];
    // }

    /**
     * Helper: Get Date Range Group
     */
    private function getDateRange($preset, $from = null, $to = null, $month = null): array
    {
        $now = Carbon::now();

        if ($month) {
            try {
                // Supports formats like "YYYY-MM" or "MM" or full date string
                $parsedMonth = Carbon::parse($month);
                return [$parsedMonth->copy()->startOfMonth()->toDateString(), $parsedMonth->copy()->endOfMonth()->toDateString()];
            } catch (\Exception $e) {
                // ignore and fall back to switch statement
            }
        }

        switch ($preset) {
            case 'today':
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
            case 'yesterday':
                $y = $now->copy()->subDay()->toDateString();
                return [$y, $y];
            case 'this_week':
                return [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()];
            case 'this_month':
                return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()];
            case 'custom':
                if ($from && $to) {
                    return [
                        Carbon::createFromFormat('Y-m-d', $from)->toDateString(),
                        Carbon::createFromFormat('Y-m-d', $to)->toDateString(),
                    ];
                }
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
            default:
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
        }
    }

    /**
     * Helper: Format overtime minutes into a human-readable string.
     * Examples: 0 => '-', 30 => '30 mins', 60 => '1 hour', 75 => '1 hour 15 mins'
     */
    private function formatOvertimeMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            $hourLabel = $hours === 1 ? '1 hour' : "{$hours} hours";
            return "{$hourLabel} {$mins} mins";
        }

        if ($hours > 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$mins} mins";
    }
}
