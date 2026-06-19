<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Exports\AttendanceExport;
use App\Exports\LeaveExport;
use App\Exports\EmployeeExport;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Company;
use App\Models\User;
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
    public function attendanceReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);

        // Get start and end dates
        list($startDate, $endDate) = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date')
        );

        // Employees query with user relations
        $employeesQuery = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->whereRelation('user', 'status', 'active'); // Filter active users

        // Filter by employee_id
        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('employee_id', $employeeId);
        }

        // Filter by department
        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->whereRelation('user', 'department_id', $departmentId);
        }

        // Search filter
        if ($search) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->get();
        $employeeIds = $employees->pluck('employee_id')->toArray();

        // Fetch attendance logs for selected employees within date range
        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $employeeIds)
            ->get()
            ->groupBy(['log_date', 'userid']);

        $reportData = [];
        $tempDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Build report data
        while ($tempDate <= $end) {
            $dateStr = $tempDate->toDateString();
            $dayLogs = $allLogs->get($dateStr, collect());

            foreach ($employees as $emp) {
                $empLogs = $dayLogs->get($emp->employee_id);
                $punchIn = $empLogs ? $empLogs->min('punch_in') : null;
                $punchOut = $empLogs ? $empLogs->max('punch_out') : null;

                $status = 'Absent';
                if ($punchIn) {
                    $time = Carbon::parse($punchIn)->format('H:i:s');
                    $status = ($time > '08:10:59' && $time <= '12:00:00') ? 'Late' : 'Present';
                }

                $reportData[] = [
                    'employee_id' => $emp->employee_id,
                    'name' => $emp->first_name . ' ' . $emp->last_name,
                    'department' => $emp->user->department->name ?? 'N/A',
                    'date' => $dateStr,
                    'punch_in' => $punchIn ? Carbon::parse($punchIn)->format('H:i') : '-',
                    'punch_out' => $punchOut ? Carbon::parse($punchOut)->format('H:i') : '-',
                    'status' => $status
                ];
            }
            $tempDate->addDay();
        }

        // Sort by date descending
        usort($reportData, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Pagination
        $currentPage = $request->get('page', 1);
        $total = count($reportData);
        $paginatedItems = array_slice($reportData, ($currentPage - 1) * $perPage, $perPage);

        return $this->success([
            'data' => $paginatedItems,
            'meta' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $currentPage,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }
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
        $perPage = $request->get('per_page', 10);

        $query = Employee::with(['user.department', 'user.designation', 'user.company']);

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('user', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($companyId && $companyId !== 'all') {
            $query->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

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
     * Company Nearest Expiry (within 30 days)
     */
    public function companyNearestExpiry(): JsonResponse
    {
        $threshold = Carbon::now()->addDays(30);
        $companies = Company::where(function ($query) use ($threshold) {
            $query->whereDate('trade_license_expiry', '<=', $threshold)
                ->orWhereDate('establishment_card_expiry', '<=', $threshold);
        })->get();

        return $this->success([
            'companies' => $companies,
            'title' => 'Company Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    /**
     * Company Upcoming Renewals (31-90 days)
     */
    public function companyUpcomingRenewals(): JsonResponse
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $companies = Company::where(function ($query) use ($start, $end) {
            $query->whereBetween('trade_license_expiry', [$start, $end])
                ->orWhereBetween('establishment_card_expiry', [$start, $end]);
        })->get();

        return $this->success([
            'companies' => $companies,
            'title' => 'Company Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    /**
     * Pending Leave Requests Report
     */
    public function pendingLeavesReport(): JsonResponse
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'leaves' => $leaves,
            'title' => 'Employees Pending Leave Reports'
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
            'leave'      => $this->leaveExport($request),
            'employee'   => $this->employeeExport($request),
            'task_report' => $this->taskReportExport($request),
            default      => $this->error('Invalid report type', 400),
        };
    }

    public function attendanceExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');

        list($startDate, $endDate) = $this->getDateRange($dateRange, $request->get('from_date'), $request->get('to_date'));

        $empQuery = Employee::with(['user.department'])->whereRelation('user', 'status', 'active');
        if ($employeeId && $employeeId !== 'all') $empQuery->where('employee_id', $employeeId);
        if ($departmentId && $departmentId !== 'all') $empQuery->whereRelation('user', 'department_id', $departmentId);

        $employees = $empQuery->get();
        $employeeIds = $employees->pluck('employee_id')->toArray();

        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $employeeIds)
            ->get()
            ->groupBy(['log_date', 'userid']);

        $data = [];
        $tempDate = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($tempDate <= $end) {
            $dateStr = $tempDate->toDateString();
            $dayLogs = $allLogs->get($dateStr, collect());
            foreach ($employees as $emp) {
                $empLogs = $dayLogs->get($emp->employee_id);
                $punchIn = $empLogs ? $empLogs->min('punch_in') : null;
                $punchOut = $empLogs ? $empLogs->max('punch_out') : null;
                $status = 'Absent';
                if ($punchIn) {
                    $time = Carbon::parse($punchIn)->format('H:i:s');
                    $status = ($time > '08:10:59' && $time <= '12:00:00') ? 'Late' : 'Present';
                }
                $data[] = [$dateStr, $emp->employee_id, $emp->first_name . ' ' . $emp->last_name, $emp->user->department->name ?? 'N/A', $punchIn ? Carbon::parse($punchIn)->format('H:i') : '-', $punchOut ? Carbon::parse($punchOut)->format('H:i') : '-', $status];
            }
            $tempDate->addDay();
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
            });

        if ($employeeId && $employeeId !== 'all') $query->whereHas('employee', fn($q) => $q->where('employee_id', $employeeId));
        if ($departmentId && $departmentId !== 'all') $query->whereHas('employee.user', fn($q) => $q->where('department_id', $departmentId));

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

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])->whereRelation('user', 'status', 'active');
        if ($departmentId && $departmentId !== 'all') $query->whereRelation('user', 'department_id', $departmentId);
        if ($companyId && $companyId !== 'all') $query->whereHas('user', fn($q) => $q->where('company_id', $companyId));

        $data = [];
        foreach ($query->get() as $emp) {
            $data[] = [$emp->employee_id, $emp->first_name . ' ' . $emp->last_name, $emp->user->company->name ?? 'N/A', $emp->user->department->name ?? 'N/A', $emp->user->designation->name ?? 'N/A', $emp->joining_date, ucfirst($emp->user->status)];
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
        if ($employeeId && $employeeId !== 'all') $taskQuery->where('employee_id', $employeeId);

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
            ]);

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
                return [$now->toDateString(), $now->toDateString()];
            case 'yesterday':
                $y = $now->subDay()->toDateString();
                return [$y, $y];
            case 'this_week':
                return [$now->startOfWeek()->toDateString(), Carbon::now()->endOfWeek()->toDateString()];
            case 'this_month':
                return [$now->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()];
            case 'custom':
                if ($from && $to) {
                    return [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()];
                }
                return [$now->toDateString(), $now->toDateString()];
            default:
                return [$now->toDateString(), $now->toDateString()];
        }
    }
}
