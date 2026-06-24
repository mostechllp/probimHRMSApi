<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\User;
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
        $query = Employee::with(['projects.projectManager', 'projects.teamLead']);

        if ($request->filled('employee_id')) {
            $query->where('id', $request->employee_id);
        }

        $employees = $query->get();
        return $this->success($employees);
    }

    /**
     * Display projects for a specific employee.
     */
    public function show($id): JsonResponse
    {
        $employee = Employee::with('projects')->find($id);

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
}
