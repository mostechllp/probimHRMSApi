<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class ProjectAssignmentApiController extends ApiController
{
    /**
     * Display a listing of employees and their assigned projects.
     */
    /**
     * Display a listing of employees and their assigned projects.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isManagerOrLead = $user && ($user->type === 'manager' || $user->type === 'team_lead');

        $query = Employee::with([
            'projects' => function ($q) use ($user, $isManagerOrLead) {
                $q->with(['projectManager', 'teamLead']);
                if ($isManagerOrLead) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('project_manager_id', $user->id)
                            ->orWhere('team_lead_id', $user->id);
                    });
                }
            }
        ]);

        if ($request->filled('employee_id')) {
            $query->where('id', $request->employee_id);
        }

        if ($isManagerOrLead) {
            $query->whereHas('projects', function ($q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhere('team_lead_id', $user->id);
            });
        }

        $employees = $query->get();
        return $this->success($employees);
    }

    public function getEmployees()
    {
        $employees = Employee::where('type', '!=', 'admin')->whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        return $this->success($employees);
    }

    /**
     * Display projects for a specific employee.
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        $isManagerOrLead = $user && ($user->type === 'manager' || $user->type === 'team_lead');

        $employeeQuery = Employee::query();
        if ($isManagerOrLead) {
            $employeeQuery->with([
                'projects' => function ($q) use ($user) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('project_manager_id', $user->id)
                            ->orWhere('team_lead_id', $user->id);
                    });
                }
            ]);
        } else {
            $employeeQuery->with('projects');
        }

        $employee = $employeeQuery->find($id);

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $formatEmployee = function ($emp) {
            if (!$emp) {
                return null;
            }

            return [
                'id' => $emp->id,
                'name' => trim($emp->first_name . ' ' . $emp->last_name),
                'employee_id' => $emp->employee_id,
                'avatar' => $emp->avatar,
                'email' => $emp->company_email ?? $emp->personal_email,
            ];
        };

        $projects = $employee->projects->map(function ($project) use ($formatEmployee) {

            $manager = Employee::where('user_id', $project->project_manager_id)->first();

            $lead = Employee::where('user_id', $project->team_lead_id)->first();

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'project_manager' => $formatEmployee($manager),
                'team_lead' => $formatEmployee($lead),
                'assigned_by' => $project->pivot->assigned_by,
                'assigned_at' => $project->pivot->created_at,
            ];
        });

        return $this->success([
            'projects' => $projects
        ]);
    }

    /**
     * Assign projects to an employee.
     */
    public function assign(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'project_ids' => 'array',
            'project_ids.*' => 'exists:projects,id',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $newProjectIds = collect($request->project_ids ?? []);

        // Existing active projects
        $existingProjects = $employee->projects()->pluck('projects.id');

        $toDetach = $existingProjects->diff($newProjectIds);

        // Detach (Soft delete)
        if ($toDetach->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::table('employee_project')
                ->where('employee_id', $employee->user_id)
                ->whereIn('project_id', $toDetach)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // Attach or restore
        foreach ($newProjectIds as $projectId) {
            if (!$existingProjects->contains($projectId)) {
                $existingPivot = \Illuminate\Support\Facades\DB::table('employee_project')
                    ->where('employee_id', $employee->user_id)
                    ->where('project_id', $projectId)
                    ->first();

                if ($existingPivot) {
                    \Illuminate\Support\Facades\DB::table('employee_project')
                        ->where('id', $existingPivot->id)
                        ->update([
                            'deleted_at' => null,
                            'deleted_by' => null,
                            'assigned_by' => auth()->id(),
                            'updated_at' => now(),
                        ]);
                } else {
                    $employee->projects()->attach($projectId, [
                        'assigned_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        // Reload to get fresh projects
        $employee->load('projects');

        return $this->success($employee, 'Projects assigned successfully');
    }

    /**
     * Get employee's working time in each project assigned to them (daily breakdown).
     */
    public function workingTime($id): JsonResponse
    {
        $employee = User::with('projects')->find($id);

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $projectTimes = $employee->projects->map(function ($project) use ($employee) {
            // Note: ProjectTimeLog uses 'user_id' column to store employee ID based on recent updates
            $dailyLogs = \App\Models\ProjectTimeLog::where('user_id', $employee->id)
                ->where('project_id', $project->id)
                ->orderBy('date', 'desc')
                ->get();

            $dailyTimes = $dailyLogs->map(function ($log) {
                return [
                    'date' => $log->date,
                    'working_time_minutes' => (int) $log->time_taken_minutes,
                    'working_time_formatted' => floor($log->time_taken_minutes / 60) . ' hours ' . ($log->time_taken_minutes % 60) . ' mins'
                ];
            });

            $totalSpentMinutes = $dailyLogs->sum('time_taken_minutes');

            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'total_working_time_minutes' => (int) $totalSpentMinutes,
                'total_working_time_formatted' => floor($totalSpentMinutes / 60) . ' hours ' . ($totalSpentMinutes % 60) . ' mins',
                'daily_logs' => $dailyTimes
            ];
        });

        return $this->success([
            'employee_id' => $employee->id,
            'employee_name' => trim($employee->first_name . ' ' . $employee->last_name),
            'project_times' => $projectTimes
        ]);
    }

    /**
     * Remove all project assignments for an employee.
     */
    public function removeAllAssignments($id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        \Illuminate\Support\Facades\DB::table('employee_project')
            ->where('employee_id', $employee->user_id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_by' => auth()->id(),
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->success(null, 'All project assignments removed successfully.');
    }

    /**
     * Monthly employee hours worked per project.
     *
     * GET /api/v1/project-assignments/monthly-hours
     * Query params:
     *   date        (string Y-m-d, optional) – filter to a single day; derives month/year automatically
     *   month       (int, 1-12, default: current month) – ignored when `date` is supplied
     *   year        (int, default: current year)         – ignored when `date` is supplied
     *   project_id  (int, optional) – filter to a single project
     *   employee_id (int, optional) – filter to a single employee (employees.id PK)
     */
    public function monthlyProjectHours(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000',
            'project_id' => 'nullable|exists:projects,id',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $projectId = $request->input('project_id');
        $employeeId = $request->input('employee_id');

        // ------------------------------------------------------------------
        // Date-range resolution
        // When `date` is supplied → single-day filter; derive month & year.
        // Otherwise fall back to month/year params (full-month range).
        // ------------------------------------------------------------------
        $filterDate = $request->input('date');

        if ($filterDate) {
            $dateCarbon = Carbon::parse($filterDate);
            $month = $dateCarbon->month;
            $year = $dateCarbon->year;
            $startDate = $dateCarbon->toDateString();
            $endDate = $dateCarbon->toDateString();   // same day → single-day range
        } else {
            $month = (int) $request->input('month', Carbon::now()->month);
            $year = (int) $request->input('year', Carbon::now()->year);
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        }

        // Build base query on project_time_logs for the resolved date range.
        // Eager-load user and employee (including soft-deleted) so inactive employees still resolve.
        $logsQuery = ProjectTimeLog::with([
            'project',
            'user' => function ($q) {
                $q->with([
                    'employee' => function ($eq) {
                        $eq->withTrashed();
                    }
                ]);
            },
        ])
            ->whereBetween('date', [$startDate, $endDate]);

        $user = auth()->user();
        if ($user && ($user->type === 'manager' || $user->type === 'team_lead')) {
            $logsQuery->whereHas('project', function ($q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhere('team_lead_id', $user->id);
            });
        }

        if ($projectId) {
            $logsQuery->where('project_id', $projectId);
        }

        if ($employeeId) {
            // Resolve employees.id (PK) → user_id for the log table.
            // Use withTrashed() so inactive/offboarded employees are included.
            $emp = Employee::withTrashed()->find($employeeId);

            if (!$emp) {
                return $this->error('Employee not found.', 404);
            }
            $logsQuery->where('user_id', $emp->user_id);
        }

        $logs = $logsQuery->get();

        // Build the base response meta that is shared across both empty and populated responses.
        $meta = [
            'month' => $month,
            'year' => $year,
            'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
        ];
        if ($filterDate) {
            $meta['date'] = $filterDate;
        }

        if ($logs->isEmpty()) {
            return $this->success(array_merge($meta, ['employees' => []]));
        }

        // Group logs by user_id then project_id
        $grouped = $logs->groupBy('user_id');
        $employees = [];

        foreach ($grouped as $userId => $userLogs) {
            // Resolve employee record
            $userModel = $userLogs->first()->user;
            $empRecord = $userModel?->employee;

            $projectBreakdown = [];
            $totalMinutes = 0;

            foreach ($userLogs->groupBy('project_id') as $projId => $projLogs) {
                $projectModel = $projLogs->first()->project;
                $minutes = (int) $projLogs->sum('time_taken_minutes');
                $totalMinutes += $minutes;

                $projectBreakdown[] = [
                    'project_id' => $projId,
                    'project_name' => $projectModel?->name ?? 'Unknown',
                    'total_minutes' => $minutes,
                    'total_hours' => round($minutes / 60, 2),
                    'formatted' => $this->formatMinutes($minutes),
                ];
            }

            // Sort projects by most time spent first
            usort($projectBreakdown, fn($a, $b) => $b['total_minutes'] <=> $a['total_minutes']);

            $employees[] = [
                'id' => $empRecord?->id,
                'user_id' => $empRecord?->user_id ?? $userId,
                'employee_id' => $empRecord?->employee_id,
                'name' => $empRecord
                    ? trim($empRecord->first_name . ' ' . $empRecord->last_name)
                    : ($userModel ? trim($userModel->first_name . ' ' . $userModel->last_name) : 'Unknown'),
                'avatar' => $empRecord?->avatar,
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'total_formatted' => $this->formatMinutes($totalMinutes),
                'projects' => $projectBreakdown,
            ];
        }

        // Sort employees by most time spent first
        usort($employees, fn($a, $b) => $b['total_minutes'] <=> $a['total_minutes']);

        return $this->success(array_merge($meta, ['employees' => $employees]));
    }

    /**
     * Format minutes into a human-readable string: e.g. "2 hours 30 mins"
     */
    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 mins';
        }
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours} " . ($hours === 1 ? 'hour' : 'hours') . " {$mins} mins";
        }
        if ($hours > 0) {
            return "{$hours} " . ($hours === 1 ? 'hour' : 'hours');
        }
        return "{$mins} mins";
    }
}

