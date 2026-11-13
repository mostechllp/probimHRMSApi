<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\TimesheetExport;
use App\Http\Controllers\Api\ApiController;
use App\Models\ProjectTimeLog;
use App\Models\User;
use App\Services\TimesheetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TimesheetApiController extends ApiController
{
    protected TimesheetService $timesheetService;

    public function __construct(TimesheetService $timesheetService)
    {
        $this->timesheetService = $timesheetService;
    }

    /**
     * Resolve authenticated user (from Bearer header or ?token= query param).
     */
    protected function resolveUser(Request $request): User
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Ignore and proceed to auth check
            }
        }

        $user = auth('api')->user() ?? auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    /**
     * Display listing of timesheets matching filters, KPIs, and current user role.
     *
     * GET /api/admin/timesheets
     * Query Parameters:
     * - filter: this_month (default) | last_month | this_quarter | project_to_date | custom
     * - start_date, end_date (for custom range, YYYY-MM-DD)
     * - month, year (optional integer)
     * - project_id (optional)
     * - employee_id / user_id (optional)
     * - status: all | pending | approved | rejected
     * - search (optional string)
     * - per_page (optional integer, default 50)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $result = $this->timesheetService->getTimesheets($user, $request->all());

        return $this->success($result, 'Timesheets retrieved successfully.');
    }

    /**
     * Display a specific timesheet entry.
     *
     * GET /api/admin/timesheets/{id}
     */
    public function show($id, Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $roleInfo = $this->timesheetService->getUserRoleInfo($user);

        $log = ProjectTimeLog::with([
            'project.projectManager',
            'project.teamLead',
            'user.employee.user.designation',
            'approver.employee',
        ])->find($id);

        if (!$log) {
            return $this->error('Timesheet entry not found.', 404);
        }

        // Check if user is allowed to view this entry
        if (!$roleInfo['is_super_admin'] && !$roleInfo['is_management']) {
            if ($log->user_id !== $user->id) {
                $isManaged = $roleInfo['is_line_manager']
                    && (in_array($log->project_id, $roleInfo['managed_project_ids']) || in_array((int) $log->user_id, $roleInfo['team_user_ids']));
                if (!$isManaged) {
                    return $this->error('Unauthorized to view this timesheet entry.', 403);
                }
            }
        }

        $formatted = $this->timesheetService->formatTimesheetRow($log, $user, $roleInfo);

        return $this->success($formatted, 'Timesheet entry details retrieved successfully.');
    }

    /**
     * Submit a new timesheet entry.
     *
     * POST /api/admin/timesheets
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'date' => 'required|date',
            'hours' => 'nullable|numeric|min:0.1|max:24',
            'time_taken_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $log = $this->timesheetService->createTimesheet($user, $request->all());
        $roleInfo = $this->timesheetService->getUserRoleInfo($user);
        $formatted = $this->timesheetService->formatTimesheetRow($log, $user, $roleInfo);

        return $this->success($formatted, 'Timesheet entry submitted successfully.', 201);
    }

    /**
     * Update an existing timesheet entry.
     *
     * PUT /api/admin/timesheets/{id}
     */
    public function update($id, Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $log = ProjectTimeLog::find($id);
        if (!$log) {
            return $this->error('Timesheet entry not found.', 404);
        }

        $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'date' => 'nullable|date',
            'hours' => 'nullable|numeric|min:0.1|max:24',
            'time_taken_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ]);

        $updated = $this->timesheetService->updateTimesheet($user, $log, $request->all());
        $roleInfo = $this->timesheetService->getUserRoleInfo($user);
        $formatted = $this->timesheetService->formatTimesheetRow($updated, $user, $roleInfo);

        return $this->success($formatted, 'Timesheet entry updated successfully.');
    }

    /**
     * Delete an existing timesheet entry.
     *
     * DELETE /api/admin/timesheets/{id}
     */
    public function destroy($id, Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $log = ProjectTimeLog::find($id);
        if (!$log) {
            return $this->error('Timesheet entry not found.', 404);
        }

        $this->timesheetService->deleteTimesheet($user, $log);

        return $this->success(null, 'Timesheet entry deleted successfully.');
    }

    /**
     * Approve a timesheet entry.
     *
     * POST /api/admin/timesheets/{id}/approve
     */
    public function approve($id, Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $log = ProjectTimeLog::find($id);
        if (!$log) {
            return $this->error('Timesheet entry not found.', 404);
        }

        $approved = $this->timesheetService->approveTimesheet($user, $log);
        $roleInfo = $this->timesheetService->getUserRoleInfo($user);
        $formatted = $this->timesheetService->formatTimesheetRow($approved, $user, $roleInfo);

        return $this->success($formatted, 'Timesheet entry approved successfully.');
    }

    /**
     * Reject a timesheet entry.
     *
     * POST /api/admin/timesheets/{id}/reject
     */
    public function reject($id, Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $log = ProjectTimeLog::find($id);
        if (!$log) {
            return $this->error('Timesheet entry not found.', 404);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $reason = $request->reason ?? $request->rejection_reason;
        $rejected = $this->timesheetService->rejectTimesheet($user, $log, $reason);
        $roleInfo = $this->timesheetService->getUserRoleInfo($user);
        $formatted = $this->timesheetService->formatTimesheetRow($rejected, $user, $roleInfo);

        return $this->success($formatted, 'Timesheet entry rejected successfully.');
    }

    /**
     * Bulk approve multiple timesheet entries.
     *
     * POST /api/admin/timesheets/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:project_time_logs,id',
        ]);

        $approvedCount = 0;
        $errors = [];

        foreach ($request->ids as $id) {
            $log = ProjectTimeLog::find($id);
            if ($log) {
                try {
                    $this->timesheetService->approveTimesheet($user, $log);
                    $approvedCount++;
                } catch (\Exception $e) {
                    $errors[$id] = $e->getMessage();
                }
            }
        }

        return $this->success([
            'approved_count' => $approvedCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ], "{$approvedCount} timesheet(s) approved successfully.");
    }

    /**
     * Bulk reject multiple timesheet entries.
     *
     * POST /api/admin/timesheets/bulk-reject
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:project_time_logs,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $rejectedCount = 0;
        $errors = [];

        foreach ($request->ids as $id) {
            $log = ProjectTimeLog::find($id);
            if ($log) {
                try {
                    $this->timesheetService->rejectTimesheet($user, $log, $request->reason);
                    $rejectedCount++;
                } catch (\Exception $e) {
                    $errors[$id] = $e->getMessage();
                }
            }
        }

        return $this->success([
            'rejected_count' => $rejectedCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ], "{$rejectedCount} timesheet(s) rejected successfully.");
    }

    /**
     * Export timesheets data to Excel or PDF.
     *
     * GET /api/admin/timesheets/export?format=xlsx|pdf
     */
    public function export(Request $request)
    {
        $format = strtolower($request->get('format', 'xlsx'));

        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }

        return $this->exportExcel($request);
    }

    /**
     * Export timesheets to Excel spreadsheet.
     *
     * GET /api/admin/timesheets/export/excel
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $user = $this->resolveUser($request);

        $params = $request->all();
        $params['per_page'] = 5000; // Export all matching
        $result = $this->timesheetService->getTimesheets($user, $params);

        $filename = 'Timesheets_Report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new TimesheetExport($result), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export timesheets to PDF document.
     *
     * GET /api/admin/timesheets/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $user = $this->resolveUser($request);

        $params = $request->all();
        $params['per_page'] = 500;
        $result = $this->timesheetService->getTimesheets($user, $params);

        $filename = 'Timesheets_Report_' . now()->format('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('reports.timesheet_pdf', $result)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        return $pdf->download($filename);
    }
}

