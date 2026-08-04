<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Exports\AttendanceExport;
use App\Exports\LeaveExport;
use App\Exports\ProjectReportExport;
use App\Exports\EmployeeExport;
use App\Models\AttendanceLog;
use App\Models\ProjectTimeLog;
use App\Models\Project;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Organization;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\TaskReport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


class ReportApiController extends ApiController
{
    /**
     * Attendance Report Listing
     */
    //main
    // public function attendanceReport(Request $request): JsonResponse
    // {
    //     $dateRange = $request->get('date_range', 'today');
    //     $employeeId = $request->get('employee_id');
    //     $departmentId = $request->get('department_id');
    //     $search = $request->get('search');
    //     $perPage = 500;

    //     // Get start and end dates
    //     [$startDate, $endDate] = $this->getDateRange(
    //         $dateRange,
    //         $request->get('from_date'),
    //         $request->get('to_date')
    //     );

    //     // Employees query
    //     $employeesQuery = Employee::with([
    //         'user.company',
    //         'user.department',
    //         'user.designation'
    //     ])
    //         ->whereHas('user', function ($query) {
    //             $query->where('status', 'active')
    //                 ->where('type', 'employee');
    //         });

    //     // Filter by employee ID
    //     if ($employeeId && $employeeId !== 'all') {
    //         $employeesQuery->where('user_id', $employeeId);
    //     }

    //     // Filter by department
    //     if ($departmentId && $departmentId !== 'all') {
    //         $employeesQuery->whereHas('user', function ($query) use ($departmentId) {
    //             $query->where('department_id', $departmentId);
    //         });
    //     }

    //     // Search
    //     if ($search) {
    //         $employeesQuery->where(function ($query) use ($search) {
    //             $query->where('first_name', 'like', "%{$search}%")
    //                 ->orWhere('last_name', 'like', "%{$search}%")
    //                 ->orWhere('employee_id', 'like', "%{$search}%");
    //         });
    //     }

    //     $employees = $employeesQuery->get();

    //     if ($employees->isEmpty()) {
    //         return $this->success([
    //             'data' => [],
    //             'meta' => [
    //                 'total' => 0,
    //                 'per_page' => (int) $perPage,
    //                 'current_page' => 1,
    //                 'last_page' => 0
    //             ]
    //         ]);
    //     }

    //     $userIds = $employees->pluck('user_id')->toArray();

    //     // Fetch working hour configuration keyed by lowercase day name
    //     $workingHours = WorkingHour::all()->keyBy(fn($wh) => strtolower($wh->day));

    //     // Attendance logs
    //     $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
    //         ->whereIn('userid', $userIds)
    //         ->orderBy('log_date')
    //         ->get()
    //         ->groupBy(['log_date', 'userid']);

    //     $reportData = [];

    //     $currentDate = Carbon::parse($startDate);
    //     $lastDate = Carbon::parse($endDate);

    //     while ($currentDate->lte($lastDate)) {

    //         $date = $currentDate->toDateString();
    //         $dayLogs = $allLogs->get($date, collect());

    //         // Determine standard hours for this day from WorkingHour config
    //         $dayName = strtolower($currentDate->format('l')); // e.g. 'monday'
    //         $workingHourConfig = $workingHours->get($dayName);
    //         $standardHours = 8; // default fallback
    //         if ($workingHourConfig && $workingHourConfig->is_enabled && $workingHourConfig->start_time && $workingHourConfig->end_time) {
    //             $configStart = Carbon::createFromTimeString($workingHourConfig->start_time);
    //             $configEnd = Carbon::createFromTimeString($workingHourConfig->end_time);
    //             $standardHours = round($configStart->diffInMinutes($configEnd) / 60, 2);
    //         }

    //         foreach ($employees as $employee) {

    //             $attendance = $dayLogs->get($employee->user_id)?->first();

    //             $punchIn = $attendance?->punch_in;
    //             $punchOut = $attendance?->punch_out;

    //             $timezone = $attendance?->timezone ?? config('app.timezone');

    //             $abbr = match ($timezone) {
    //                 'Asia/Kolkata',
    //                 'Asia/Calcutta',
    //                 'IST',
    //                 '+05:30',
    //                 'UTC+05:30' => 'IST',
    //                 'Asia/Dubai',
    //                 'GST',
    //                 '+04:00',
    //                 'UTC+04:00' => 'GST',
    //                 default => Carbon::now($timezone)->format('T'),
    //             };

    //             $workedHours = 0;
    //             $overtimeMinutes = 0;

    //             if ($punchIn) {
    //                 $status = 'Present';
    //             } else {
    //                 $status = $currentDate->isSunday() ? 'Weekly Off' : 'Absent';
    //             }

    //             if ($punchIn && $punchOut) {

    //                 $punchInTime = Carbon::parse($punchIn);
    //                 $punchOutTime = Carbon::parse($punchOut);

    //                 $workedMinutes = $punchInTime->diffInMinutes($punchOutTime);
    //                 $workedHours = round($workedMinutes / 60, 2);

    //                 if ($workedHours >= 8) {

    //                     $status = 'Full Day';

    //                 } elseif ($workedHours >= 4) {

    //                     $status = 'Half Day';

    //                 } else {

    //                     $status = 'Absent';
    //                 }

    //                 // Overtime in minutes beyond the standard configured hours
    //                 $overtimeMinutes = max(0, (int) round(($workedHours - $standardHours) * 60));
    //             }

    //             $reportData[] = [
    //                 'employee_id' => $employee->employee_id,
    //                 'name' => trim($employee->first_name . ' ' . $employee->last_name),
    //                 'department' => $employee->user->department->name ?? 'N/A',
    //                 'designation' => $employee->user->designation->name ?? 'N/A',
    //                 'company' => $employee->user->company->name ?? 'N/A',
    //                 'date' => $date,
    //                 'punch_in' => $punchIn
    //                     ? Carbon::parse($punchIn)->format('h:i A') . ' ' . $abbr
    //                     : '-',
    //                 'punch_out' => $punchOut
    //                     ? Carbon::parse($punchOut)->format('h:i A') . ' ' . $abbr
    //                     : '-',
    //                 'worked_hours' => $workedHours,
    //                 'standard_hours' => $standardHours,
    //                 'overtime' => $this->formatOvertimeMinutes($overtimeMinutes),
    //                 'status' => $status,
    //             ];
    //         }

    //         $currentDate->addDay();
    //     }

    //     // Sort by latest date first
    //     usort($reportData, function ($a, $b) {

    //         if ($a['date'] === $b['date']) {
    //             return strcmp($a['employee_id'], $b['employee_id']);
    //         }

    //         return strcmp($b['date'], $a['date']);
    //     });

    //     // Pagination
    //     $currentPage = (int) $request->get('page', 1);

    //     $total = count($reportData);

    //     $paginatedItems = array_slice(
    //         $reportData,
    //         ($currentPage - 1) * $perPage,
    //         $perPage
    //     );

    //     return $this->success([
    //         'data' => array_values($paginatedItems),
    //         'meta' => [
    //             'total' => $total,
    //             'per_page' => (int) $perPage,
    //             'current_page' => $currentPage,
    //             'last_page' => (int) ceil($total / $perPage),
    //         ]
    //     ]);
    // }

    //dev
    public function attendanceReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $perPage = $request->get('per_page', 100);
        $search = $request->get('search');

        [$startDate, $endDate] = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date')
        );

        $employeesQuery = Employee::with([
            // 'user.company',
            'user.department',
            'user.designation'
        ])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->where('type', '!=', 'admin');
            });

        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('user_id', $employeeId);
        }

        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->whereHas('user', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            });
        }

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
                    'per_page' => $perPage,
                    'current_page' => 1,
                    'last_page' => 0
                ]
            ]);
        }

        $userIds = $employees->pluck('user_id')->toArray();

        $workingHours = WorkingHour::all()->keyBy(fn($wh) => strtolower($wh->day));

        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $userIds)
            ->orderBy('log_date')
            ->get()
            ->groupBy(['log_date', 'userid']);

        $reportData = [];

        foreach ($employees as $employee) {

            $reportData[$employee->user_id] = [
                'employee_id' => $employee->employee_id,
                'user_id' => $employee->user_id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'department' => $employee->user->department->name ?? 'N/A',
                'designation' => $employee->user->designation->name ?? 'N/A',
                'company' => $employee->user->company->name ?? 'N/A',
                'attendance' => [],
            ];
        }

        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate->lte($lastDate)) {

            $date = $currentDate->toDateString();
            $dayLogs = $allLogs->get($date, collect());

            $dayName = strtolower($currentDate->format('l'));

            $workingHourConfig = $workingHours->get($dayName);

            $standardHours = 8;

            if (
                $workingHourConfig &&
                $workingHourConfig->is_enabled &&
                $workingHourConfig->start_time &&
                $workingHourConfig->end_time
            ) {
                $configStart = Carbon::createFromTimeString($workingHourConfig->start_time);
                $configEnd = Carbon::createFromTimeString($workingHourConfig->end_time);

                $standardHours = round($configStart->diffInMinutes($configEnd) / 60, 2);
            }

            foreach ($employees as $employee) {

                $attendance = $dayLogs->get($employee->user_id)?->first();

                $punchIn = $attendance?->punch_in;
                $punchOut = $attendance?->punch_out;

                $timezone = $attendance?->timezone ?? config('app.timezone');

                $abbr = match ($timezone) {
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

                $workedHours = 0;
                $overtimeMinutes = 0;

                if ($punchIn) {
                    $status = 'Present';
                } else {
                    $status = $currentDate->isSunday()
                        ? 'Weekly Off'
                        : 'Absent';
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

                    $overtimeMinutes = max(
                        0,
                        (int) round(($workedHours - $standardHours) * 60)
                    );
                }

                $reportData[$employee->user_id]['attendance'][] = [

                    'date' => $date,

                    'punch_in' => $punchIn
                        ? Carbon::parse($punchIn)->format('h:i A') . ' ' . $abbr
                        : '-',

                    'punch_out' => $punchOut
                        ? Carbon::parse($punchOut)->format('h:i A') . ' ' . $abbr
                        : '-',

                    'worked_hours' => $workedHours,

                    'standard_hours' => $standardHours,

                    'overtime' => $this->formatOvertimeMinutes($overtimeMinutes),

                    'status' => $status,
                ];
            }

            $currentDate->addDay();
        }

        // Sort employees by Employee ID
        usort($reportData, function ($a, $b) {
            return strcmp($a['employee_id'], $b['employee_id']);
        });

        $reportData = array_values($reportData);

        $currentPage = (int) $request->get('page', 1);

        $total = count($reportData);

        $paginatedItems = array_slice(
            $reportData,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        return $this->success([
            'data' => $paginatedItems,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }

    //dev

    /**
     * Leave Report Listing
     */
    public function leaveReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'this_month');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $perPage = $request->get('per_page', 10);

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $query = LeaveRequest::with(['employee.user.department', 'leaveType'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            });

        if ($employeeId && $employeeId !== 'all') {
            $query->whereHas('employee', function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId);
            });
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('employee.user', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $leaves = $query->latest()->paginate($perPage);

        return $this->success($leaves);
    }

    /**
     * Employee Report Listing
     */
    public function employeeReport(Request $request): JsonResponse
    {
        $departmentId = $request->get('department_id');
        $companyId = $request->get('company_id');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 10);

        $query = Employee::with([
            'user.department',
            'user.designation',
            'user.company'
        ])->whereHas('user', function ($q) use ($departmentId, $companyId, $status) {

            $q->where('type', '!=', 'admin');

            if ($departmentId && $departmentId !== 'all') {
                $q->where('department_id', $departmentId);
            }

            if ($companyId && $companyId !== 'all') {
                $q->where('company_id', $companyId);
            }

            if ($status) {
                $q->where('status', $status);
            }
        });

        $employees = $query->paginate($perPage);

        return $this->success($employees);
    }
    /**
     * Employee Details Report
     */
    public function employeeDetails(): JsonResponse
    {
        $employees = Employee::with(['user.company', 'user.department', 'user.designation'])->get();
        return $this->success($employees);
    }

    /**
     * Employee Nearest Expiry (within 30 days)
     */
    public function employeeNearestExpiry(): JsonResponse
    {
        $threshold = Carbon::now()->addDays(30);
        $employees = Employee::where(function ($query) use ($threshold) {
            $query->whereDate('passport_expiry_date', '<=', $threshold)
                ->orWhereDate('visa_expiry_date', '<=', $threshold)
                ->orWhereDate('labor_expiry_date', '<=', $threshold)
                ->orWhereDate('eid_expiry_date', '<=', $threshold);
        })->get();

        return $this->success([
            'employees' => $employees,
            'title' => 'Employee Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    /**
     * Employee Upcoming Renewals (31-90 days)
     */
    public function employeeUpcomingRenewals(): JsonResponse
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $employees = Employee::where(function ($query) use ($start, $end) {
            $query->whereBetween('passport_expiry_date', [$start, $end])
                ->orWhereBetween('visa_expiry_date', [$start, $end])
                ->orWhereBetween('labor_expiry_date', [$start, $end])
                ->orWhereBetween('eid_expiry_date', [$start, $end]);
        })->get();

        return $this->success([
            'employees' => $employees,
            'title' => 'Employee Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    /**
     * Organization Nearest Expiry – documents expiring within 30 days
     */
    public function companyNearestExpiry(): JsonResponse
    {
        $threshold = Carbon::now()->addDays(30);

        $documents = Document::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $threshold)
            ->with('party')
            ->orderBy('expiry_date')
            ->get();

        $organizations = Organization::all(['id', 'name']);

        return $this->success([
            'documents' => $documents,
            'organizations' => $organizations,
            'title' => 'Organization Nearest Expiry Details',
            'subtitle' => 'Documents expiring within 30 days',
        ]);
    }

    /**
     * Organization / Company Upcoming Renewals – documents expiring in 31-90 days
     */
    public function companyUpcomingRenewals(): JsonResponse
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $documents = Document::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$start, $end])
            ->with('party')
            ->get();

        $organizations = Organization::all();

        return $this->success([
            'documents' => $documents,
            'organizations' => $organizations,
            'title' => 'Organization Upcoming Renewals',
            'subtitle' => 'Documents expiring within 31-90 days'
        ]);
    }

    /**
     * Pending Leave Requests Report
     */
    public function pendingLeavesReport(): JsonResponse
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'leaves' => $leaves,
            'title' => 'Employees Pending Leave Reports'
        ]);
    }

    /**
     * Project Report Listing
     */
    public function projectReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'this_month');
        $projectId = $request->get('project_id');
        $search = $request->get('search');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $projectsQuery = Project::query();

        if ($projectId && $projectId !== 'all') {
            $projectsQuery->where('id', $projectId);
        }

        if ($search) {
            $projectsQuery->where('name', 'like', "%{$search}%");
        }

        $projects = $projectsQuery->get();

        $reportData = [];

        // Build list of dates in the range
        $dates = [];
        $tempDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($tempDate <= $end) {
            $dates[] = $tempDate->toDateString();
            $tempDate->addDay();
        }

        foreach ($projects as $project) {
            // Get all time logs for this project in date range
            $logs = ProjectTimeLog::where('project_id', $project->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with([
                    'user.employee' => function ($query) {
                        $query->withTrashed();
                    }
                ])
                ->get();

            // Group logs by user
            $logsByUser = $logs->groupBy('user_id');

            $employeesList = [];
            $totalProjectMinutes = 0;

            foreach ($logsByUser as $userId => $userLogs) {
                $user = $userLogs->first()->user;
                $employee = $user?->employee;

                $dailyBreakdown = [];
                $employeeTotalMinutes = 0;

                // For each day in the date range, calculate hours worked
                foreach ($dates as $date) {
                    $dayMinutes = $userLogs->where('date', $date)->sum('time_taken_minutes');
                    $dailyBreakdown[$date] = round($dayMinutes / 60, 2);
                    $employeeTotalMinutes += $dayMinutes;
                }

                $totalProjectMinutes += $employeeTotalMinutes;

                $employeesList[] = [
                    'id' => $employee?->id,
                    'user_id' => $userId,
                    'employee_id' => $employee?->employee_id ?? 'N/A',
                    'name' => $employee ? trim($employee->first_name . ' ' . $employee->last_name) : ($user ? trim($user->first_name . ' ' . $user->last_name) : 'Unknown'),
                    'daily_breakdown' => $dailyBreakdown,
                    'total_employee_hours' => round($employeeTotalMinutes / 60, 2),
                ];
            }

            $reportData[] = [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status ?? 'active',
                'total_hours' => round($totalProjectMinutes / 60, 2),
                'employee_count' => count($employeesList),
                'employees' => $employeesList,
            ];
        }

        return $this->success($reportData);
    }

    public function projectExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'this_month');
        $projectId = $request->get('project_id');
        $search = $request->get('search');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $projectsQuery = Project::query();

        if ($projectId && $projectId !== 'all') {
            $projectsQuery->where('id', $projectId);
        }

        if ($search) {
            $projectsQuery->where('name', 'like', "%{$search}%");
        }

        $projects = $projectsQuery->get();

        // Build list of dates in the range
        $dates = [];
        $tempDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($tempDate <= $end) {
            $dates[] = $tempDate->toDateString();
            $tempDate->addDay();
        }

        $data = [];

        foreach ($projects as $project) {
            // Get logs
            $logs = ProjectTimeLog::where('project_id', $project->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->with([
                    'user.employee' => function ($query) {
                        $query->withTrashed();
                    }
                ])
                ->get();

            $logsByUser = $logs->groupBy('user_id');

            $totalProjectMinutes = 0;
            $projectRows = [];
            $uniqueEmployeesCount = count($logsByUser);

            foreach ($logsByUser as $userId => $userLogs) {
                $user = $userLogs->first()->user;
                $employee = $user?->employee;
                $empName = $employee ? trim($employee->first_name . ' ' . $employee->last_name) : ($user ? trim($user->first_name . ' ' . $user->last_name) : 'Unknown');
                $empId = $employee?->employee_id ?? 'N/A';

                $employeeTotalMinutes = 0;
                $employeeRows = [];

                foreach ($dates as $date) {
                    $dayMinutes = $userLogs->where('date', $date)->sum('time_taken_minutes');
                    if ($dayMinutes > 0) {
                        $hours = round($dayMinutes / 60, 2);
                        $employeeTotalMinutes += $dayMinutes;
                        $employeeRows[] = [
                            'emp_id' => $empId,
                            'emp_name' => $empName,
                            'date' => $date,
                            'hours' => $hours
                        ];
                    }
                }

                $totalProjectMinutes += $employeeTotalMinutes;

                foreach ($employeeRows as $row) {
                    $projectRows[] = [
                        'emp_id' => $row['emp_id'],
                        'emp_name' => $row['emp_name'],
                        'emp_total_hours' => round($employeeTotalMinutes / 60, 2),
                        'date' => $row['date'],
                        'hours' => $row['hours']
                    ];
                }
            }

            $totalProjectHours = round($totalProjectMinutes / 60, 2);

            foreach ($projectRows as $row) {
                $data[] = [
                    $project->id,
                    $project->name,
                    ucfirst($project->status ?? 'active'),
                    $totalProjectHours,
                    $uniqueEmployeesCount,
                    $row['emp_id'],
                    $row['emp_name'],
                    $row['emp_total_hours'],
                    $row['date'],
                    $row['hours']
                ];
            }

            // If a project has no hours logged, we can still list it once
            if (empty($projectRows)) {
                $data[] = [
                    $project->id,
                    $project->name,
                    ucfirst($project->status ?? 'active'),
                    0,
                    0,
                    'N/A',
                    'N/A',
                    0,
                    '-',
                    0
                ];
            }
        }

        if (strtolower($request->get('format', '')) === 'pdf') {
            $exportClass = new ProjectReportExport($data);
            $filename = "project_report_" . now()->format('YmdHis');
            $pdf = Pdf::loadView('reports.generic_pdf', [
                'data' => $exportClass->array(),
                'headings' => $exportClass->headings(),
                'title' => $exportClass->title(),
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        return $this->downloadResponse(new ProjectReportExport($data), "project_report", $request->get('format'));
    }

    public function employeeNearestExpiryExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $threshold = Carbon::now()->addDays(30);
        $employees = Employee::where(function ($query) use ($threshold) {
            $query->whereDate('passport_expiry_date', '<=', $threshold)
                ->orWhereDate('visa_expiry_date', '<=', $threshold)
                ->orWhereDate('labor_expiry_date', '<=', $threshold)
                ->orWhereDate('eid_expiry_date', '<=', $threshold);
        })->get();

        $data = [];
        $columns = ['Employee ID', 'Name', 'Passport Expiry', 'Visa Expiry', 'Labor Expiry', 'EID Expiry'];
        foreach ($employees as $emp) {
            $data[] = [
                $emp->employee_id,
                trim($emp->first_name . ' ' . $emp->last_name),
                $emp->passport_expiry_date ?? 'N/A',
                $emp->visa_expiry_date ?? 'N/A',
                $emp->labor_expiry_date ?? 'N/A',
                $emp->eid_expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "employee_nearest_expiry", $request->get('format'));
    }

    public function employeeUpcomingRenewalsExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $employees = Employee::where(function ($query) use ($start, $end) {
            $query->whereBetween('passport_expiry_date', [$start, $end])
                ->orWhereBetween('visa_expiry_date', [$start, $end])
                ->orWhereBetween('labor_expiry_date', [$start, $end])
                ->orWhereBetween('eid_expiry_date', [$start, $end]);
        })->get();

        $data = [];
        $columns = ['Employee ID', 'Name', 'Passport Expiry', 'Visa Expiry', 'Labor Expiry', 'EID Expiry'];
        foreach ($employees as $emp) {
            $data[] = [
                $emp->employee_id,
                trim($emp->first_name . ' ' . $emp->last_name),
                $emp->passport_expiry_date ?? 'N/A',
                $emp->visa_expiry_date ?? 'N/A',
                $emp->labor_expiry_date ?? 'N/A',
                $emp->eid_expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "employee_upcoming_renewals", $request->get('format'));
    }

    public function companyNearestExpiryExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $threshold = Carbon::now()->addDays(30);

        $documents = Document::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $threshold)
            ->with('party')
            ->get();

        $data = [];
        $columns = ['Document Name', 'Type', 'Party', 'Expiry Date'];
        foreach ($documents as $doc) {
            $data[] = [
                $doc->name,
                ucfirst($doc->type),
                $doc->party->name ?? 'N/A',
                $doc->expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "organization_nearest_expiry", $request->get('format'));
    }

    public function companyUpcomingRenewalsExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $documents = Document::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$start, $end])
            ->with('party')
            ->get();

        $data = [];
        $columns = ['Document Name', 'Type', 'Party', 'Expiry Date'];
        foreach ($documents as $doc) {
            $data[] = [
                $doc->name,
                ucfirst($doc->type),
                $doc->party->name ?? 'N/A',
                $doc->expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "organization_upcoming_renewals", $request->get('format'));
    }

    public function pendingLeavesExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [];
        $columns = ['Employee ID', 'Employee Name', 'Leave Type', 'Start Date', 'End Date', 'Duration (Days)', 'Reason'];
        foreach ($leaves as $leave) {
            $data[] = [
                $leave->employee->employee_id ?? 'N/A',
                $leave->employee ? trim($leave->employee->first_name . ' ' . $leave->employee->last_name) : 'N/A',
                $leave->leaveType->name ?? 'N/A',
                $leave->start_date ? Carbon::parse($leave->start_date)->toDateString() : 'N/A',
                $leave->end_date ? Carbon::parse($leave->end_date)->toDateString() : 'N/A',
                $leave->duration_days,
                $leave->reason ?? 'N/A'
            ];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "pending_leaves", $request->get('format'));
    }

    /**
     * Report Counts – returns record count for every report card in one call.
     *
     * Accepts optional date filter params (same as attendanceReport / leaveReport):
     *   date_range, from_date, to_date
     */
    public function reportCounts(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'this_month');
        [$startDate, $endDate] = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date')
        );

        // -- Attendance: unique employees who have at least one log in the period --
        $attendanceCount = AttendanceLog::distinct('userid')
            ->count('userid');

        // -- Leave requests that overlap the period --
        $leaveCount = LeaveRequest::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate]);
        })
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            })->count();

        // -- Active employees --
        $employeeCount = Employee::whereHas('user', function ($q) {
            $q->where('status', 'active')->where('type', '!=', 'admin');
        })->count();

        // -- Task reports in the period --
        $taskReportCount = TaskReport::count();

        // -- Projects (all active) --
        $projectCount = Project::count();

        // -- Projects with at least one time-log in the period --
        $projectWithLogsCount = Project::whereHas('timeLogs')->count();

        // -- Documents expiring within 30 days (employees) --
        $expiryThreshold30 = Carbon::now()->addDays(30);
        $employeeNearestExpiryCount = Employee::where(function ($q) use ($expiryThreshold30) {
            $q->whereDate('passport_expiry_date', '<=', $expiryThreshold30)
                ->orWhereDate('visa_expiry_date', '<=', $expiryThreshold30)
                ->orWhereDate('labor_expiry_date', '<=', $expiryThreshold30)
                ->orWhereDate('eid_expiry_date', '<=', $expiryThreshold30);
        })->count();

        // -- Documents expiring 31-90 days (employees) --
        $renewalStart = Carbon::now()->addDays(31);
        $renewalEnd = Carbon::now()->addDays(90);
        $employeeUpcomingRenewalsCount = Employee::where(function ($q) use ($renewalStart, $renewalEnd) {
            $q->whereBetween('passport_expiry_date', [$renewalStart, $renewalEnd])
                ->orWhereBetween('visa_expiry_date', [$renewalStart, $renewalEnd])
                ->orWhereBetween('labor_expiry_date', [$renewalStart, $renewalEnd])
                ->orWhereBetween('eid_expiry_date', [$renewalStart, $renewalEnd]);
        })->count();

        // -- Organization/document records expiring within 30 days --
        $companyNearestExpiryCount = Document::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $expiryThreshold30)
            ->count();

        // -- Organization/document records expiring 31-90 days --
        $companyUpcomingRenewalsCount = Document::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$renewalStart, $renewalEnd])
            ->count();

        // -- Pending leave requests (active users only) --
        $pendingLeavesCount = LeaveRequest::where('status', 'pending')
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            })->count();

        return $this->success([
            'period' => [
                'date_range' => $dateRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'counts' => [
                [
                    'key' => 'attendance',
                    'label' => 'Attendance',
                    'count' => $attendanceCount,
                ],
                [
                    'key' => 'leave',
                    'label' => 'Leave Requests',
                    'count' => $leaveCount,
                ],
                [
                    'key' => 'employee',
                    'label' => 'Active Employees',
                    'count' => $employeeCount,
                ],
                [
                    'key' => 'task_report',
                    'label' => 'Task Reports',
                    'count' => $taskReportCount,
                ],
                [
                    'key' => 'project',
                    'label' => 'Projects',
                    'count' => $projectCount,
                ],
                [
                    'key' => 'project_active',
                    'label' => 'Projects (Active This Period)',
                    'count' => $projectWithLogsCount,
                ],
                [
                    'key' => 'employee_nearest_expiry',
                    'label' => 'Employee Documents Expiring (≤30 days)',
                    'count' => $employeeNearestExpiryCount,
                ],
                [
                    'key' => 'employee_upcoming_renewals',
                    'label' => 'Employee Documents Renewing (31–90 days)',
                    'count' => $employeeUpcomingRenewalsCount,
                ],
                [
                    'key' => 'company_nearest_expiry',
                    'label' => 'Company Documents Expiring (≤30 days)',
                    'count' => $companyNearestExpiryCount,
                ],
                [
                    'key' => 'company_upcoming_renewals',
                    'label' => 'Company Documents Renewing (31–90 days)',
                    'count' => $companyUpcomingRenewalsCount,
                ],
                [
                    'key' => 'pending_leaves',
                    'label' => 'Pending Leave Requests',
                    'count' => $pendingLeavesCount,
                ],
            ],
        ]);
    }

    /**
     * Export Report
     */
    /**
     * Main Export Dispatcher
     */
    public function export(Request $request)
    {
        $this->authenticateFromToken($request);

        $reportType = $request->get('report_type');

        return match ($reportType) {
            'attendance' => $this->attendanceExport($request),
            'leave' => $this->leaveExport($request),
            'employee' => $this->employeeExport($request),
            'task_report' => $this->taskReportExport($request),
            'project' => $this->projectExport($request),
            'employee_nearest_expiry' => $this->employeeNearestExpiryExport($request),
            'employee_upcoming_renewals' => $this->employeeUpcomingRenewalsExport($request),
            'company_nearest_expiry' => $this->companyNearestExpiryExport($request),
            'company_upcoming_renewals' => $this->companyUpcomingRenewalsExport($request),
            'pending_leaves' => $this->pendingLeavesExport($request),
            default => $this->error('Invalid report type', 400),
        };
    }

    public function attendanceExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $empQuery = Employee::with(['user.department'])->whereHas('user', function ($q) {
            $q->where('status', 'active')->where('type', '!=', 'admin');
        });
        if ($employeeId && $employeeId !== 'all')
            $empQuery->where('user_id', $employeeId);
        if ($departmentId && $departmentId !== 'all')
            $empQuery->whereRelation('user', 'department_id', $departmentId);

        $employees = $empQuery->get();
        $userIds = $employees->pluck('user_id')->toArray();

        $workingHours = WorkingHour::all()->keyBy(fn($wh) => strtolower($wh->day));

        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $userIds)
            ->get()
            ->groupBy(['log_date', 'userid']);

        $data = [];
        $tempDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($tempDate <= $end) {
            $dateStr = $tempDate->toDateString();
            $dayLogs = $allLogs->get($dateStr, collect());

            $dayName = strtolower($tempDate->format('l'));
            $workingHourConfig = $workingHours->get($dayName);
            $standardHours = 8;
            if ($workingHourConfig && $workingHourConfig->is_enabled && $workingHourConfig->start_time && $workingHourConfig->end_time) {
                $configStart = Carbon::createFromTimeString($workingHourConfig->start_time);
                $configEnd = Carbon::createFromTimeString($workingHourConfig->end_time);
                $standardHours = round($configStart->diffInMinutes($configEnd) / 60, 2);
            }

            foreach ($employees as $emp) {
                $attendance = $dayLogs->get($emp->user_id)?->first();
                $punchIn = $attendance?->punch_in;
                $punchOut = $attendance?->punch_out;

                $timezone = $attendance?->timezone ?? config('app.timezone');

                $abbr = match ($timezone) {
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

                $workedHours = 0;
                $overtimeMinutes = 0;
                $status = $tempDate->isSunday() ? 'Weekly Off' : 'Absent';

                if ($punchIn) {
                    $status = 'Present';
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

                    $overtimeMinutes = max(0, (int) round(($workedHours - $standardHours) * 60));
                }

                $data[] = [
                    $dateStr,
                    $emp->employee_id,
                    $emp->first_name . ' ' . $emp->last_name,
                    $emp->user->department->name ?? 'N/A',
                    $punchIn ? Carbon::parse($punchIn)->format('H:i') . ' ' . $abbr : '-',
                    $punchOut ? Carbon::parse($punchOut)->format('H:i') . ' ' . $abbr : '-',
                    $workedHours,
                    $standardHours,
                    $this->formatOvertimeMinutes($overtimeMinutes),
                    $status
                ];
            }
            $tempDate->addDay();
        }

        $totalRecords = count($data);

        if (strtolower($request->get('format', '')) === 'pdf') {
            $exportClass = new AttendanceExport($data);
            $filename = "attendance_report_" . now()->format('YmdHis');
            $pdf = Pdf::loadView('reports.attendance_pdf', [
                'data' => $exportClass->array(),
                'headings' => $exportClass->headings(),
                'title' => $exportClass->title(),
                'period' => $startDate . ' to ' . $endDate,
                'summary' => 'Total Records: ' . $totalRecords,
            ])->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        return $this->downloadResponse(new AttendanceExport($data), "attendance_report", $request->get('format'));
    }

    public function leaveExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'this_month');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $query = LeaveRequest::with(['employee.user', 'leaveType'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->whereHas('employee.user', function ($q) {
                $q->whereNull('deleted_at');
            });

        if ($employeeId && $employeeId !== 'all')
            $query->whereHas('employee', fn($q) => $q->where('employee_id', $employeeId));
        if ($departmentId && $departmentId !== 'all')
            $query->whereHas('employee.user', fn($q) => $q->where('department_id', $departmentId));

        $data = [];
        foreach ($query->get() as $leave) {
            $data[] = [$leave->employee->employee_id ?? 'N/A', ($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''), $leave->leaveType->name ?? 'N/A', $leave->start_date->toDateString(), $leave->end_date->toDateString(), $leave->duration_days, ucfirst($leave->status), $leave->reason];
        }

        return $this->downloadResponse(new LeaveExport($data), "leave_report", $request->get('format'));
    }

    public function employeeExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $departmentId = $request->get('department_id');
        $companyId = $request->get('company_id');

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])->whereRelation('user', 'status', 'active')->whereRelation('user', 'type', '!=', 'admin');
        if ($departmentId && $departmentId !== 'all')
            $query->whereRelation('user', 'department_id', $departmentId);
        if ($companyId && $companyId !== 'all')
            $query->whereHas('user', fn($q) => $q->where('company_id', $companyId));

        $data = [];
        foreach ($query->get() as $emp) {
            $data[] = [$emp->employee_id, $emp->first_name, $emp->user->department->name ?? 'N/A', $emp->user->designation->name ?? 'N/A', $emp->joining_date, ucfirst($emp->user->status)];
        }

        return $this->downloadResponse(new EmployeeExport($data), "employee_report", $request->get('format'));
    }

    public function taskReportExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $taskQuery = TaskReport::with(['employee.user'])->whereBetween('date', [$startDate, $endDate]);
        if ($employeeId && $employeeId !== 'all')
            $taskQuery->where('employee_id', $employeeId);

        $data = [];
        $columns = ['Date', 'Employee ID', 'Name', 'Tasks Completed', 'Plan for Tomorrow', 'Remarks'];
        foreach ($taskQuery->latest('date')->get() as $report) {
            $data[] = [$report->date, $report->employee->employee_id ?? 'N/A', ($report->employee->first_name ?? '') . ' ' . ($report->employee->last_name ?? ''), $report->tasks_completed, $report->plan_tomorrow, $report->remarks];
        }

        return $this->downloadResponse(new \App\Exports\GenericExport($data, $columns), "task_report", $request->get('format'));
    }

    /**
     * Helper: Handle authentication via query token for downloads
     */
    private function authenticateFromToken(Request $request)
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Fail silently or handle
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }

    /**
     * Helper: Centralized download response handler
     */
    private function downloadResponse($exportClass, $baseFilename, $format)
    {
        $filename = $baseFilename . "_" . now()->format('YmdHis');
        $format = strtolower($format);

        if ($format === 'pdf') {
            $data = method_exists($exportClass, 'array') ? $exportClass->array() : (method_exists($exportClass, 'collection') ? $exportClass->collection()->toArray() : []);
            $headings = method_exists($exportClass, 'headings') ? $exportClass->headings() : [];
            $title = method_exists($exportClass, 'title') ? $exportClass->title() : str_replace('_', ' ', ucfirst($baseFilename));

            $pdf = Pdf::loadView('reports.generic_pdf', [
                'data' => $data,
                'headings' => $headings,
                'title' => $title
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        return match ($format) {
            'xlsx' => Excel::download($exportClass, $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX),
            default => Excel::download($exportClass, $filename . '.csv', \Maatwebsite\Excel\Excel::CSV),
        };
    }

    /**
     * Helper: Get Date Range Group
     */
    private function getDateRange($preset, $from = null, $to = null): array
    {
        $now = Carbon::now();
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
