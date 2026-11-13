<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimesheetService
{
    /**
     * Determine user permissions and role classification.
     */
    public function getUserRoleInfo(?User $user): array
    {
        if (!$user) {
            return [
                'is_super_admin' => false,
                'is_management' => false,
                'is_line_manager' => false,
                'is_employee' => false,
                'role_name' => 'Guest',
                'managed_project_ids' => [],
                'team_user_ids' => [],
            ];
        }

        $roleName = strtolower(trim($user->role?->name ?? ''));
        $userType = strtolower(trim($user->type ?? ''));

        // Admin and HR have full access
        $isSuperAdmin = in_array($userType, ['admin', 'hr'])
            || in_array($roleName, ['super admin', 'admin']);

        // Identify management roles without granting unrestricted access
        $isManagement = in_array($roleName, [
            'management',
            'bim manager',
            'hr manager',
            'manager',
        ]) || str_contains($roleName, 'manager');

        // Employee identification
        $isEmployee = $userType === 'employee';

        // Find projects managed or led by this user
        $managedProjectIds = Project::where('project_manager_id', $user->id)
            ->orWhere('team_lead_id', $user->id)
            ->pluck('id')
            ->toArray();

        // Find assigned team members
        $teamUserIds = [];

        if (!empty($managedProjectIds)) {
            $teamUserIds = DB::table('employee_project')
                ->whereIn('project_id', $managedProjectIds)
                ->whereNull('deleted_at')
                ->pluck('employee_id')
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();
        }

        // Managers and team leads get team-level access
        $isLineManager = in_array($userType, ['manager', 'team_lead'])
            || $isManagement
            || in_array($roleName, [
                'bim team lead',
                'bim assistant manager',
                'bim coordinator',
            ])
            || !empty($managedProjectIds);

        return [
            'is_super_admin' => $isSuperAdmin,
            'is_management' => $isManagement,
            'is_line_manager' => $isLineManager,
            'is_employee' => $isEmployee,
            'role_name' => $user->role?->name ?? ucfirst($userType),
            'managed_project_ids' => $managedProjectIds,
            'team_user_ids' => $teamUserIds,
        ];
    }

    /**
     * Resolve date range from filter parameters (matching Project Cost standard).
     */
    public function resolveDateRange(array $params): array
    {
        $now = Carbon::now();

        if (!empty($params['month']) && !empty($params['year'])) {
            $month = (int) $params['month'];
            $year = (int) $params['year'];
            $date = Carbon::createFromDate($year, $month, 1);
            return [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
                'month_year',
                $date->format('F Y'),
            ];
        }

        $filter = strtolower(trim($params['filter'] ?? $params['date_range'] ?? 'this_month'));
        $filter = str_replace(' ', '_', $filter);

        switch ($filter) {
            case 'last_month':
                $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $now->copy()->subMonthNoOverflow()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'last_month',
                    'Last Month (' . $start->format('F Y') . ')',
                ];

            case 'this_quarter':
                $start = $now->copy()->firstOfQuarter();
                $end = $now->copy()->lastOfQuarter();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_quarter',
                    'This Quarter (Q' . $now->quarter . ' ' . $now->year . ')',
                ];

            case 'project_to_date':
                $earliest = ProjectTimeLog::min('date');
                $start = $earliest ? Carbon::parse($earliest) : $now->copy()->subYears(5)->startOfYear();
                $end = $now->copy()->endOfDay();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'project_to_date',
                    'Project to Date',
                ];

            case 'custom':
            case 'custom_range':
                $from = $params['start_date'] ?? $params['from_date'] ?? null;
                $to = $params['end_date'] ?? $params['to_date'] ?? null;

                if ($from && $to) {
                    $start = Carbon::parse($from);
                    $end = Carbon::parse($to);
                    return [
                        $start->toDateString(),
                        $end->toDateString(),
                        'custom',
                        $start->format('d M Y') . ' - ' . $end->format('d M Y'),
                    ];
                }

                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_month',
                    'This Month (' . $now->format('F Y') . ')',
                ];

            case 'this_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_month',
                    'This Month (' . $now->format('F Y') . ')',
                ];
        }
    }

    /**
     * Build base query scoped according to role.
     * NOTE: project.projectManager and project.teamLead are Employee records
     * (joined via user_id FK), so we do NOT chain .user on them.
     */
    public function getScopedQuery(User $user, array $roleInfo): Builder
    {
        $query = ProjectTimeLog::with([
            'project.projectManager.user',
            'project.teamLead.user',
            'user.employee.user.designation',
            'user.designation',
            'approver.employee',
        ]);

        // Admin / HR can see everyone's timesheets
        if ($roleInfo['is_super_admin']) {
            return $query;
        }

        // Manager / Team Lead can see their own and their team's timesheets
        if ($roleInfo['is_line_manager']) {
            $teamUserIds = $roleInfo['team_user_ids'] ?? [];

            return $query->where(function ($q) use ($user, $teamUserIds) {
                $q->where('user_id', $user->id);

                if (!empty($teamUserIds)) {
                    $q->orWhereIn('user_id', $teamUserIds);
                }
            });
        }

        // Employees can see only their own timesheets
        return $query->where('user_id', $user->id);
    }

    /**
     * Get timesheets list, KPI summary cards, and pagination.
     */
    public function getTimesheets(User $user, array $params = []): array
    {
        $roleInfo = $this->getUserRoleInfo($user);
        [$startDate, $endDate, $filterKey, $filterLabel] = $this->resolveDateRange($params);

        $query = $this->getScopedQuery($user, $roleInfo);

        // Apply Date Range
        $query->whereBetween('date', [$startDate, $endDate]);

        // Filter by Project
        if (!empty($params['project_id']) && $params['project_id'] !== 'all') {
            $query->where('project_id', $params['project_id']);
        }

        // Filter by Employee / User
        if (!empty($params['employee_id']) && $params['employee_id'] !== 'all') {
            // Can be employees.id or users.id
            $emp = Employee::withTrashed()->find($params['employee_id']);
            $targetUserId = $emp ? $emp->user_id : $params['employee_id'];
            $query->where('user_id', $targetUserId);
        } elseif (!empty($params['user_id']) && $params['user_id'] !== 'all') {
            $query->where('user_id', $params['user_id']);
        }

        // Filter by Status
        // NULL status means the row pre-dates our workflow and is treated as 'approved'.
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $filterStatus = strtolower(trim($params['status']));
            if ($filterStatus === ProjectTimeLog::STATUS_APPROVED) {
                // Include both explicitly approved AND legacy NULL-status rows
                $query->where(function ($q) {
                    $q->where('status', ProjectTimeLog::STATUS_APPROVED)
                        ->orWhereNull('status');
                });
            } else {
                $query->where('status', $filterStatus);
            }
        }

        // Search by Employee Name or Project Name
        if (!empty($params['search'])) {
            $search = trim($params['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('project', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%");
                })->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('username', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($eq) use ($search) {
                            $eq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%");
                        });
                });
            });
        }

        // Clone query for KPI metrics before pagination
        $kpiQuery = clone $query;
        $allLogs = $kpiQuery->get();

        $totalMinutes = $allLogs->sum('time_taken_minutes');

        // NULL status = legacy row pre-dating the approval workflow → treated as approved
        $approvedMinutes = $allLogs
            ->filter(fn($l) => $l->status === ProjectTimeLog::STATUS_APPROVED || $l->status === null)
            ->sum('time_taken_minutes');

        $pendingApprovalsCount = $allLogs
            ->where('status', ProjectTimeLog::STATUS_PENDING)
            ->count();

        $rejectedMinutes = $allLogs
            ->where('status', ProjectTimeLog::STATUS_REJECTED)
            ->sum('time_taken_minutes');

        $totalHours = round($totalMinutes / 60, 2);
        $approvedHours = round($approvedMinutes / 60, 2);
        $rejectedHours = round($rejectedMinutes / 60, 2);

        $kpis = [
            'total_logged_hours' => $totalHours,
            'total_logged_hours_formatted' => $this->formatHours($totalHours),
            'pending_approvals_count' => $pendingApprovalsCount,
            'approved_hours' => $approvedHours,
            'approved_hours_formatted' => $this->formatHours($approvedHours),
            'rejected_hours' => $rejectedHours,
            'rejected_hours_formatted' => $this->formatHours($rejectedHours),
        ];

        // Sort by date desc, id desc
        $query->orderBy('date', 'desc')->orderBy('id', 'desc');

        // Pagination
        $perPage = (int) ($params['per_page'] ?? $params['limit'] ?? 50);
        $paginated = $query->paginate($perPage);

        $formattedRows = collect($paginated->items())->map(function (ProjectTimeLog $log) use ($user, $roleInfo) {
            return $this->formatTimesheetRow($log, $user, $roleInfo);
        });

        return [
            'period' => [
                'filter' => $filterKey,
                'label' => $filterLabel,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'kpis' => $kpis,
            'timesheets' => $formattedRows,
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
            'user_permissions' => [
                'role' => $roleInfo['role_name'],
                'is_super_admin' => $roleInfo['is_super_admin'],
                'is_management' => $roleInfo['is_management'],
                'is_line_manager' => $roleInfo['is_line_manager'],
                'can_approve_any' => $roleInfo['is_super_admin'] || $roleInfo['is_management'],
                'can_submit_for_others' => $roleInfo['is_super_admin'] || $roleInfo['is_management'] || $roleInfo['is_line_manager'],
            ],
        ];
    }

    /**
     * Format a single timesheet log for the frontend table.
     *
     * NOTE: Rows with status = NULL pre-date our approval workflow and are
     * treated as already-approved legacy entries — displayed as 'approved'
     * and not actionable (no approve/reject/edit/delete for non-admins).
     */
    public function formatTimesheetRow(ProjectTimeLog $log, User $currentUser, array $roleInfo): array
    {
        $logUser = $log->user;
        $employee = $logUser?->employee;
        $project = $log->project;

        $empName = $employee
            ? trim($employee->first_name . ' ' . $employee->last_name)
            : ($logUser ? trim($logUser->username) : 'Unknown');

        // projectManager and teamLead are Employee records (not User), resolve name directly
        $managerName = null;
        if ($project?->projectManager) {
            $pm = $project->projectManager;
            $managerName = trim(($pm->first_name ?? '') . ' ' . ($pm->last_name ?? ''));
        } elseif ($project?->teamLead) {
            $tl = $project->teamLead;
            $managerName = trim(($tl->first_name ?? '') . ' ' . ($tl->last_name ?? ''));
        }
        $managerName = $managerName ?: null;

        // Approver info
        $approverUser = $log->approver;
        $approverEmp = $approverUser?->employee;
        $approverName = $approverEmp
            ? trim($approverEmp->first_name . ' ' . $approverEmp->last_name)
            : ($approverUser ? $approverUser->username : null);

        // Resolve effective status — NULL means legacy row, treat as approved
        $effectiveStatus = $log->status ?? ProjectTimeLog::STATUS_APPROVED;
        $isLegacyRow = ($log->status === null); // pre-dates workflow

        // Determine if current user can approve/reject this row
        // Only pending rows (explicitly set, not legacy) can be actioned
        $canApprove = false;
        if ($effectiveStatus === ProjectTimeLog::STATUS_PENDING && !$isLegacyRow && $log->user_id !== $currentUser->id) {
            if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
                $canApprove = true;
            } elseif ($roleInfo['is_line_manager']) {
                $isProjectManager = in_array($log->project_id, $roleInfo['managed_project_ids']);
                $isTeamMember = in_array((int) $log->user_id, $roleInfo['team_user_ids']);
                $canApprove = $isProjectManager || $isTeamMember;
            }
        }

        // Determine if current user can edit this row
        $canEdit = false;
        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            $canEdit = true;
        } elseif (!$isLegacyRow && $log->user_id === $currentUser->id) {
            // Employee can only edit if still pending
            $canEdit = ($effectiveStatus === ProjectTimeLog::STATUS_PENDING);
        } elseif (!$isLegacyRow && $roleInfo['is_line_manager']) {
            $canEdit = ($effectiveStatus === ProjectTimeLog::STATUS_PENDING)
                && (in_array($log->project_id, $roleInfo['managed_project_ids']) || in_array((int) $log->user_id, $roleInfo['team_user_ids']));
        }

        // Determine if current user can delete this row
        $canDelete = false;
        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            $canDelete = true;
        } elseif (!$isLegacyRow && $log->user_id === $currentUser->id) {
            $canDelete = ($effectiveStatus === ProjectTimeLog::STATUS_PENDING);
        }

        return [
            'id' => $log->id,
            'employee' => [
                'id' => $employee?->id,
                'user_id' => $log->user_id,
                'code' => $employee?->employee_id ?? 'N/A',
                'name' => $empName,
                'avatar' => $employee?->avatar_url ?? null,
                'designation' => $employee?->designation?->name ?? $logUser?->designation?->name ?? 'N/A',
                'manager_name' => $managerName,
                'manager_label' => $managerName ? "Mgr: {$managerName}" : null,
            ],
            'project' => [
                'id' => $project?->id,
                'name' => $project?->name ?? 'N/A',
            ],
            'date' => Carbon::parse($log->date)->toDateString(),
            'hours' => $log->hours,
            'hours_formatted' => $log->formatted_hours,
            'time_taken_minutes' => (int) $log->time_taken_minutes,
            'status' => $effectiveStatus,
            'status_label' => ucfirst($effectiveStatus),
            'is_legacy' => $isLegacyRow, // frontend hint: row pre-dates approval workflow
            'description' => $log->description,
            'approver' => [
                'id' => $log->approved_by,
                'name' => $approverName,
                'approved_at' => $log->approved_at?->toIso8601String(),
                'rejection_reason' => $log->rejection_reason,
            ],
            'actions' => [
                'can_approve' => $canApprove,
                'can_reject' => $canApprove,
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
            ],
        ];
    }

    /**
     * Submit a new timesheet entry.
     */
    public function createTimesheet(User $user, array $data): ProjectTimeLog
    {
        $roleInfo = $this->getUserRoleInfo($user);

        // Resolve target user_id
        $targetUserId = $user->id;
        if (!empty($data['user_id']) || !empty($data['employee_id'])) {
            $reqUserId = !empty($data['user_id']) ? (int) $data['user_id'] : null;
            if (!$reqUserId && !empty($data['employee_id'])) {
                $emp = Employee::find($data['employee_id']);
                $reqUserId = $emp ? (int) $emp->user_id : null;
            }

            if ($reqUserId && $reqUserId !== $user->id) {
                // Must have manager or admin permission to submit for another user
                $canSubmitForOthers = $roleInfo['is_super_admin']
                    || $roleInfo['is_management']
                    || ($roleInfo['is_line_manager'] && in_array($reqUserId, $roleInfo['team_user_ids']));

                if (!$canSubmitForOthers) {
                    abort(403, 'You are not authorized to submit timesheets for other employees.');
                }
                $targetUserId = $reqUserId;
            }
        }

        // Convert hours or minutes
        $minutes = 0;
        if (isset($data['time_taken_minutes'])) {
            $minutes = (int) $data['time_taken_minutes'];
        } elseif (isset($data['hours'])) {
            $minutes = (int) round(((float) $data['hours']) * 60);
        }

        if ($minutes <= 0) {
            abort(422, 'Hours or minutes must be greater than zero.');
        }

        $log = ProjectTimeLog::create([
            'user_id' => $targetUserId,
            'project_id' => $data['project_id'],
            'date' => Carbon::parse($data['date'])->toDateString(),
            'time_taken_minutes' => $minutes,
            'status' => ProjectTimeLog::STATUS_PENDING,
            'description' => $data['description'] ?? null,
        ]);

        return $log->load(['user.employee.user.designation', 'project.projectManager', 'approver']);
    }

    /**
     * Update an existing timesheet entry.
     */
    public function updateTimesheet(User $user, ProjectTimeLog $log, array $data): ProjectTimeLog
    {
        $roleInfo = $this->getUserRoleInfo($user);

        // Permission check
        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            // Super Admin & Management can update anytime
        } elseif ($log->user_id === $user->id) {
            // Employee can only update if still pending
            if ($log->status !== ProjectTimeLog::STATUS_PENDING) {
                abort(403, 'You cannot edit a timesheet that has already been ' . $log->status . '.');
            }
        } elseif ($roleInfo['is_line_manager']) {
            $isManaged = in_array($log->project_id, $roleInfo['managed_project_ids'])
                || in_array((int) $log->user_id, $roleInfo['team_user_ids']);
            if (!$isManaged || $log->status !== ProjectTimeLog::STATUS_PENDING) {
                abort(403, 'Unauthorized to edit this timesheet entry.');
            }
        } else {
            abort(403, 'Unauthorized access.');
        }

        $updateData = [];

        if (isset($data['project_id'])) {
            $updateData['project_id'] = $data['project_id'];
        }

        if (isset($data['date'])) {
            $updateData['date'] = Carbon::parse($data['date'])->toDateString();
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['time_taken_minutes'])) {
            $updateData['time_taken_minutes'] = (int) $data['time_taken_minutes'];
        } elseif (isset($data['hours'])) {
            $updateData['time_taken_minutes'] = (int) round(((float) $data['hours']) * 60);
        }

        // Admin/Management can also change status directly
        if (($roleInfo['is_super_admin'] || $roleInfo['is_management']) && isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $log->update($updateData);

        return $log->fresh(['user.employee.user.designation', 'project.projectManager', 'approver']);
    }

    /**
     * Delete a timesheet entry.
     */
    public function deleteTimesheet(User $user, ProjectTimeLog $log): void
    {
        $roleInfo = $this->getUserRoleInfo($user);

        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            $log->delete();
            return;
        }

        if ($log->user_id === $user->id) {
            if ($log->status !== ProjectTimeLog::STATUS_PENDING) {
                abort(403, 'You cannot delete a timesheet that has already been ' . $log->status . '.');
            }
            $log->delete();
            return;
        }

        if ($roleInfo['is_line_manager']) {
            $isManaged = in_array($log->project_id, $roleInfo['managed_project_ids'])
                || in_array((int) $log->user_id, $roleInfo['team_user_ids']);
            if ($isManaged && $log->status === ProjectTimeLog::STATUS_PENDING) {
                $log->delete();
                return;
            }
        }

        abort(403, 'Unauthorized to delete this timesheet entry.');
    }

    /**
     * Approve a timesheet entry.
     */
    public function approveTimesheet(User $user, ProjectTimeLog $log): ProjectTimeLog
    {
        $roleInfo = $this->getUserRoleInfo($user);

        // Cannot approve own timesheet
        if ($log->user_id === $user->id && !$roleInfo['is_super_admin']) {
            abort(403, 'You cannot approve your own timesheet.');
        }

        $canApprove = false;
        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            $canApprove = true;
        } elseif ($roleInfo['is_line_manager']) {
            $isManaged = in_array($log->project_id, $roleInfo['managed_project_ids'])
                || in_array((int) $log->user_id, $roleInfo['team_user_ids']);
            $canApprove = $isManaged;
        }

        if (!$canApprove) {
            abort(403, 'You are not authorized to approve timesheets for this employee or project.');
        }

        $log->update([
            'status' => ProjectTimeLog::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return $log->fresh(['user.employee.user.designation', 'project.projectManager', 'approver']);
    }

    /**
     * Reject a timesheet entry.
     */
    public function rejectTimesheet(User $user, ProjectTimeLog $log, ?string $reason = null): ProjectTimeLog
    {
        $roleInfo = $this->getUserRoleInfo($user);

        // Cannot reject own timesheet
        if ($log->user_id === $user->id && !$roleInfo['is_super_admin']) {
            abort(403, 'You cannot reject your own timesheet.');
        }

        $canReject = false;
        if ($roleInfo['is_super_admin'] || $roleInfo['is_management']) {
            $canReject = true;
        } elseif ($roleInfo['is_line_manager']) {
            $isManaged = in_array($log->project_id, $roleInfo['managed_project_ids'])
                || in_array((int) $log->user_id, $roleInfo['team_user_ids']);
            $canReject = $isManaged;
        }

        if (!$canReject) {
            abort(403, 'You are not authorized to reject timesheets for this employee or project.');
        }

        $log->update([
            'status' => ProjectTimeLog::STATUS_REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $log->fresh(['user.employee.user.designation', 'project.projectManager', 'approver']);
    }

    /**
     * Helper to format decimal hours (e.g. 8.5 -> '8.5h', 9 -> '9h').
     */
    public function formatHours(float $hours): string
    {
        if (floor($hours) == $hours) {
            return ((int) $hours) . 'h';
        }
        return $hours . 'h';
    }
}

