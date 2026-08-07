<?php

namespace App\Observers;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Employee;
use App\Notifications\LeaveRequestSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class LeaveRequestObserver
{
    /**
     * Handle the LeaveRequest "created" event.
     */
    public function created(LeaveRequest $leaveRequest): void
    {
        $employee = Employee::with('projects')->find($leaveRequest->employee_id);

        if (!$employee) {
            return;
        }

        // 1. Fetch HR and Admin users
        $adminAndHrUsers = User::whereIn('type', ['admin', 'hr'])->get();

        // 2. Fetch Team Leads and Managers for the employee's projects
        $managerIds = [];
        foreach ($employee->projects as $project) {
            if ($project->project_manager_id) {
                // project_manager_id is typically a User ID or Employee ID.
                // Let's verify based on Project model: it maps to Employee user_id or id?
                // Wait, Project.php says: belongsTo(Employee::class, 'project_manager_id', 'user_id');
                // This means project_manager_id in projects table IS the user_id of the manager.
                $managerIds[] = $project->project_manager_id;
            }
            if ($project->team_lead_id) {
                $managerIds[] = $project->team_lead_id;
            }
        }

        $managerUsers = collect();
        if (!empty($managerIds)) {
            $managerUsers = User::whereIn('id', $managerIds)->get();
        }

        // 3. Merge and make unique so we don't notify someone twice
        $allUsersToNotify = $adminAndHrUsers->merge($managerUsers)->unique('id');

        // 4. Send Notification
        if ($allUsersToNotify->isNotEmpty()) {
            Notification::send($allUsersToNotify, new LeaveRequestSubmittedNotification($leaveRequest));
        }
    }

    /**
     * Handle the LeaveRequest "updated" event.
     */
    public function updated(LeaveRequest $leaveRequest): void
    {
        //
    }

    /**
     * Handle the LeaveRequest "deleted" event.
     */
    public function deleted(LeaveRequest $leaveRequest): void
    {
        //
    }

    /**
     * Handle the LeaveRequest "restored" event.
     */
    public function restored(LeaveRequest $leaveRequest): void
    {
        //
    }

    /**
     * Handle the LeaveRequest "force deleted" event.
     */
    public function forceDeleted(LeaveRequest $leaveRequest): void
    {
        //
    }
}
