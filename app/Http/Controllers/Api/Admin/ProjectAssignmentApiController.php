<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectAssignmentApiController extends ApiController
{
    /**
     * Display a listing of employees and their assigned projects.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['projects.projectManager', 'projects.teamLead']);

        if ($request->filled('employee_id')) {
            $query->where('id', $request->employee_id);
        }

        $employees = $query->get()->map(function ($employee) {
            $employee->projects->each(function ($project) use ($employee) {
                $project->project_time = \App\Models\ProjectTimeLog::where('employee_id', $employee->user_id)
                    ->where('project_id', $project->id)
                    ->sum('time_taken_minutes');
            });
            return $employee;
        });

        return $this->success($employees);
    }

    /**
     * Display projects for a specific employee.
     */
    public function show($id): JsonResponse
    {
        $employee = Employee::with(['projects.projectManager.user', 'projects.teamLead.user'])->find($id);

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $formatEmployee = function ($emp) {
            if (!$emp)
                return null;
            return [
                'id' => $emp->id,
                'name' => trim($emp->first_name . ' ' . $emp->last_name),
                'employee_id' => $emp->employee_id,
                'avatar' => $emp->avatar,
                'email' => $emp->company_email ?? ($emp->user->email ?? $emp->personal_email),
            ];
        };

        $projects = $employee->projects->map(function ($project) use ($formatEmployee, $employee) {
            $timeSpent = \App\Models\ProjectTimeLog::where('employee_id', $employee->user_id)
                ->where('project_id', $project->id)
                ->sum('time_taken_minutes');

            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'project_manager' => $formatEmployee($project->projectManager),
                'team_lead' => $formatEmployee($project->teamLead),
                'assigned_by' => $project->pivot->assigned_by,
                'assigned_at' => $project->pivot->created_at,
                'project_time' => $timeSpent,
            ];
        });

        return $this->success(['projects' => $projects]);
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
                ->where('employee_id', $employee->id)
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
                    ->where('employee_id', $employee->id)
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
}
