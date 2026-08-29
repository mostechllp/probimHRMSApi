<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectApiController extends ApiController
{
    /**
     * Display a listing of the projects.
     */
    public function index(): JsonResponse
    {
        $query = Project::query();
        $user = auth()->user();

        if ($user && ($user->type === 'manager' || $user->type === 'team_lead')) {
            $query->where(function ($q) use ($user) {
                $q->where('project_manager_id', $user->id)
                    ->orWhere('team_lead_id', $user->id);
            });
        }

        $projects = $query->latest()->get();
        return $this->success($projects);
    }

    /**
     * Get eligible project managers and team leads code in probim dev
     */
    public function getEligibleManagers(): JsonResponse
    {
        $employees = Employee::whereHas('user', function ($query) {
            $query->whereIn('type', ['manager', 'hr', 'team_lead']);
        })
            ->with('user')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
                    'user_type' => $employee->user?->user_type,
                ];
            });

        return $this->success($employees);
    }

    //code in probim main live
    // public function getEligibleManagers(): JsonResponse
    // {
    //     $allowedRoles = [
    //         // 'Super Admin',
    //         'Admin',
    //         'Subadmin',
    //         'HR Manager',
    //         'BIM Manager',
    //         'BIM Assistant Manager',
    //         'BIM Team Lead',
    //     ];

    //     $employees = Employee::whereHas('user.role', function ($query) use ($allowedRoles) {
    //         $query->whereIn('name', $allowedRoles);
    //     })
    //         ->with(['user.role'])
    //         ->get()
    //         ->map(function ($employee) {
    //             return [
    //                 'id' => $employee->id,
    //                 'user_id' => $employee->user_id,
    //                 'employee_id' => $employee->employee_id,
    //                 'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
    //                 'role' => $employee->user?->role?->name,
    //             ];
    //         });

    //     return $this->success($employees);
    // }


    public function getEligibleTeamLeads(): JsonResponse
    {
        $employees = Employee::whereHas('user', function ($query) {
            $query->whereIn('type', ['hr', 'team_lead', 'manager']);
        })
            ->with('user')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
                    'user_type' => $employee->user?->user_type,
                ];
            });

        return $this->success($employees);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_manager_id' => 'nullable|exists:users,id',
            'team_lead_id' => 'nullable|exists:users,id',
            'total_hours' => 'nullable|integer|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'project_manager_id' => $request->project_manager_id,
            'team_lead_id' => $request->team_lead_id,
            'total_hours' => $request->total_hours,
            'total_cost' => $request->total_cost,
            'currency' => $request->currency,
            'created_by' => auth()->id(),
        ]);

        return $this->success($project, 'Project created successfully', 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        return $this->success($project);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_manager_id' => 'nullable|exists:users,id',
            'team_lead_id' => 'nullable|exists:users,id',
            'total_hours' => 'nullable|integer|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        $project->update($request->only(['name', 'description', 'project_manager_id', 'team_lead_id', 'total_hours', 'total_cost', 'currency']));

        return $this->success($project, 'Project updated successfully');
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->update(['deleted_by' => auth()->id()]);
        $project->delete();

        return $this->success(null, 'Project deleted successfully');
    }
}
