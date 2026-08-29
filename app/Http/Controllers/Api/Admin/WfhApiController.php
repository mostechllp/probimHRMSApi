<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\WfhRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WfhApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $perPage = $request->get('per_page', 15);

        $query = WfhRequest::with('employee.user')->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $user = auth()->user();
        if ($user && ($user->type === 'manager' || $user->type === 'team_lead')) {
            $employeeIds = \App\Models\Employee::whereIn('user_id', function ($q) use ($user) {
                $q->select('employee_id')
                    ->from('employee_project')
                    ->whereIn('project_id', function ($subQuery) use ($user) {
                        $subQuery->select('id')
                            ->from('projects')
                            ->where('project_manager_id', $user->id)
                            ->orWhere('team_lead_id', $user->id);
                    })
                    ->whereNull('deleted_at');
            })->pluck('id');

            $query->whereIn('employee_id', $employeeIds);
        }

        $requests = $query->paginate($perPage);

        return $this->success($requests);
    }

    public function show(WfhRequest $wfhRequest): JsonResponse
    {
        return $this->success($wfhRequest->load('employee.user'));
    }

    public function updateStatus(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'admin_notes' => 'nullable|string'
        ]);

        $wfhRequest->update([
            'status' => $request->status,
            'notes' => $request->admin_notes ?? $wfhRequest->notes
        ]);

        return $this->success($wfhRequest->load('employee.user'), "WFH request {$request->status} successfully.");
    }

    public function update(Request $request, WfhRequest $wfhRequest): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,Approved,Rejected'
        ]);

        $wfhRequest->update($request->all());

        return $this->success($wfhRequest->load('employee.user'), 'WFH request updated successfully');
    }

    public function destroy(WfhRequest $wfhRequest): JsonResponse
    {
        $wfhRequest->delete();
        return $this->success(null, 'WFH request deleted successfully');
    }
}
