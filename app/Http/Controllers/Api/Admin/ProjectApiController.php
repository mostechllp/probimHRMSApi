<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectApiController extends ApiController
{
    /**
     * Display a listing of the projects.
     */
    public function index(): JsonResponse
    {
        $projects = Project::latest()->get();
        return $this->success($projects);
    }

    /**
     * Get eligible project managers and team leads.
     */
    public function getEligibleManagers(): JsonResponse
    {
        $allowedRoles = [
            'Super Admin',
            'Admin',
            'Subadmin',
            'HR Manager',
            'BIM Manager',
            'BIM Assistant Manager',
            'BIM Team Lead',
            'BIM Coordinator'
        ];

        $employees = \App\Models\Employee::whereHas('user.role', function ($query) use ($allowedRoles) {
            $query->whereIn('name', $allowedRoles);
        })->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'employee_id' => $employee->employee_id,
                'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
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
