<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TaskReportApiController extends ApiController
{
    /**
     * Display a listing of task reports.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $employeeId = $request->get('employee_id');
        $date = $request->get('date');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $search = $request->get('search');

        $query = TaskReport::with(['employee.user.company', 'employee.user.department', 'employee.user.designation']);

        if ($employeeId && $employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        if ($date) {
            $query->whereDate('date', $date);
        } elseif ($fromDate && $toDate) {
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $reports = $query->latest('date')->paginate($perPage);

        return $this->success($reports);
    }

    /**
     * Store a newly created task report.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'tasks_completed' => 'required|string',
            'plan_tomorrow' => 'required|string',
            'remarks' => 'nullable|string'
        ]);

        $report = TaskReport::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            $request->only(['tasks_completed', 'plan_tomorrow', 'remarks'])
        );

        return $this->success($report->load('employee.user'), 'Task report saved successfully', $report->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified task report.
     */
    public function show(TaskReport $taskReport): JsonResponse
    {
        return $this->success($taskReport->load(['employee.user.company', 'employee.user.department', 'employee.user.designation']));
    }

    /**
     * Update the specified task report.
     */
    public function update(Request $request, TaskReport $taskReport): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'required|string',
            'plan_tomorrow' => 'required|string',
            'remarks' => 'nullable|string',
            'date' => 'nullable|date',
            'employee_id' => 'nullable|exists:employees,id'
        ]);

        $taskReport->update($request->all());

        return $this->success($taskReport->load('employee.user'), 'Task report updated successfully');
    }

    /**
     * Remove the specified task report from storage.
     */
    public function destroy(TaskReport $taskReport): JsonResponse
    {
        $taskReport->delete();
        return $this->success(null, 'Task report deleted successfully');
    }
}
