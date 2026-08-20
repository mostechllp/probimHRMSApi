<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\ProjectTimeLog;
use App\Models\AttendanceBreak;
use App\Models\TaskReport;
use App\Models\Employee;
use App\Models\WfhRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use App\Models\Payroll;
use App\Models\WorkingHour;
use App\Models\AttendanceRequest;
use App\Models\Holiday;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Mail\RequestNotificationMail;
use App\Models\User;

class EmployeePortalApiController extends ApiController
{
    /**
     * Get Employee Dashboard Data
     */
    public function dashboard(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user)
            return $this->error('Unauthorized', 401);

        $employee = $user;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $user->load('department', 'company', 'designation');

        $today = Carbon::today()->toDateString();

        // Attendance stats for today
        $attendance = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->select('id', 'punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'working_hours')
            ->first();

        // ↓ Store raw punch_out status BEFORE formatting
        $isPunchedOut = $attendance && !is_null($attendance->punch_out);

        $isOnBreak = false;
        $totalBreakMinutes = 0;
        $breaksList = [];
        $tz = config('app.timezone', 'Asia/Dubai');

        if ($attendance) {
            $attendance->punch_in = $attendance->punch_in
                ? Carbon::parse($attendance->punch_in)->setTimezone($tz)->format('h:i A')
                : '--';

            $attendance->punch_out = $attendance->punch_out
                ? Carbon::parse($attendance->punch_out)->setTimezone($tz)->format('h:i A')
                : '--';

            // Format working_hours → "8 hrs 30 mins"
            $minutes = $attendance->working_hours ?? 0;
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            if ($minutes == 0) {
                $attendance->working_hours = '--';
            } elseif ($hours == 0) {
                $attendance->working_hours = "{$mins} mins";
            } elseif ($mins == 0) {
                $attendance->working_hours = "{$hours} hrs";
            } else {
                $attendance->working_hours = "{$hours} hrs {$mins} mins";
            }
        }

        // 30-day attendance history
        $from = Carbon::now()->subDays(30)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $attendanceHistory = AttendanceLog::where('userid', $user->id)
            ->whereBetween('log_date', [$from, $to])
            ->select('log_date', 'punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'working_hours')
            ->orderByDesc('log_date')
            ->get();
        if ($attendanceHistory) {
            $attendanceHistory->transform(function ($log) {
                $tz = config('app.timezone', 'Asia/Kolkata');

                $log->log_date = $log->log_date
                    ? Carbon::parse($log->log_date)->format('d/m/Y')
                    : '--';

                $log->punch_in = $log->punch_in
                    ? Carbon::parse($log->punch_in)->setTimezone($tz)->format('h:i A')
                    : '--';

                $log->punch_out = $log->punch_out
                    ? Carbon::parse($log->punch_out)->setTimezone($tz)->format('h:i A')
                    : '--';

                // Format working_hours → "8 hrs 30 mins"
                $minutes = $log->working_hours ?? 0;
                $hours = intdiv($minutes, 60);
                $mins = $minutes % 60;

                if ($minutes == 0)
                    $log->working_hours = '--';
                elseif ($hours == 0)
                    $log->working_hours = "{$mins} mins";
                elseif ($mins == 0)
                    $log->working_hours = "{$hours} hrs";
                else
                    $log->working_hours = "{$hours} hrs {$mins} mins";

                return $log;
            });
        }

        $leave_employee_id = Employee::where('user_id', $user->id)->first();

        $year = Carbon::now()->format('Y');
        // Leave stats
        $totalLeaveAllocated = LeaveAllocation::where('employee_id', $leave_employee_id->id)->where('year', $year)->sum('allocated_days');

        $totalLeavesTaken = LeaveRequest::where('employee_id', $leave_employee_id->id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $leaveBalance = $totalLeaveAllocated - $totalLeavesTaken;

        // Punch Access Logic
        $canPunch = true;

        // 1. Check default designation punch access
        if ($employee->designation && $employee->designation->default_punch_access) {
            $canPunch = true;
        }

        // 2. Specific designation checks
        if (!$canPunch && ($employee->designation && in_array($employee->designation->name, ['Delivery Man', 'Salesperson']))) {
            $canPunch = true;
        }

        // 3. Check for approved WFH request today
        if (!$canPunch) {
            $canPunch = WfhRequest::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->where('status', 'Approved')
                ->exists();
        }

        // Project assignments for this employee (project details + assignment details)
        $projectAssignments = $user->projects()
            ->with([
                'projectManager.user.department',
                'projectManager.user.designation',
                'teamLead.user.department',
                'teamLead.user.designation',
            ])
            ->get()
            ->map(function ($project) {

                $projectManager = $project->projectManager;
                $teamLead = $project->teamLead;

                return [
                    'project' => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'project_manager_id' => $project->project_manager_id,
                        'team_lead_id' => $project->team_lead_id,
                        'total_hours' => $project->total_hours,
                        'total_cost' => $project->total_cost,
                        'currency' => $project->currency,
                    ],

                    'project_manager' => $projectManager ? [
                        'id' => $projectManager->id,
                        'name' => trim(
                            $projectManager->first_name . ' ' .
                            $projectManager->last_name
                        ),
                        'department' => $projectManager->user?->department?->name,
                        'designation' => $projectManager->user?->designation?->name,
                    ] : null,

                    'team_lead' => $teamLead ? [
                        'id' => $teamLead->id,
                        'name' => trim(
                            $teamLead->first_name . ' ' .
                            $teamLead->last_name
                        ),
                        'department' => $teamLead->user?->department?->name,
                        'designation' => $teamLead->user?->designation?->name,
                    ] : null,

                    'assignment' => [
                        'assigned_by' => $project->pivot->assigned_by,
                        'assigned_at' => $project->pivot->created_at,
                        'updated_at' => $project->pivot->updated_at,
                    ],
                ];
            });

        // ── Missed punch-in days (last 30 days, scheduled working days only) ──
        $loggedDates = AttendanceLog::where('userid', $user->id)
            ->whereBetween('log_date', [$from, $to])
            ->pluck('log_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique();

        $holidayDates = Holiday::whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $leaveDates = [];
        LeaveRequest::where('employee_id', $leave_employee_id->id)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $to->toDateString())
            ->get(['start_date', 'end_date'])
            ->each(function ($leave) use (&$leaveDates, $from, $to) {
                $cursor = Carbon::parse($leave->start_date)->max($from);
                $end = Carbon::parse($leave->end_date)->min($to);
                while ($cursor->lte($end)) {
                    $leaveDates[] = $cursor->toDateString();
                    $cursor->addDay();
                }
            });

        $enabledWorkingDays = WorkingHour::where('is_enabled', true)->pluck('day')->toArray();

        $joiningDate = $leave_employee_id->joining_date ? Carbon::parse($leave_employee_id->joining_date) : null;
        $rangeStart = ($joiningDate && $joiningDate->gt($from)) ? $joiningDate->copy() : $from->copy();
        $rangeEnd = Carbon::yesterday()->lt($to) ? Carbon::yesterday() : $to->copy();

        $missedPunchIns = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $dateStr = $cursor->toDateString();
            $dayName = $cursor->format('l');

            $isWorkingDay = in_array($dayName, $enabledWorkingDays);
            $isSunday = $cursor->isSunday();
            $isHoliday = in_array($dateStr, $holidayDates);
            $isOnLeave = in_array($dateStr, $leaveDates);
            $hasPunched = $loggedDates->contains($dateStr);

            if ($isWorkingDay && !$isSunday && !$isHoliday && !$isOnLeave && !$hasPunched) {
                $missedPunchIns[] = [
                    'date' => $dateStr,
                    'day' => $dayName,
                ];
            }

            $cursor->addDay();
        }

        return $this->success([
            'employee' => $user->employee,
            'today_attendance' => [
                'punched_in' => (bool) $attendance,
                'punched_out' => $isPunchedOut,
                'is_on_break' => $isOnBreak,
                'total_break_minutes' => $totalBreakMinutes,
                'breaks' => $breaksList,
                'punch_in_time' => $attendance ? $attendance->punch_in : null,
                'punch_out_time' => $attendance ? $attendance->punch_out : null,
                'punch_in_location' => [          // ← ADD THIS
                    'latitude' => $attendance ? $attendance->punch_in_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_in_longitude : null,
                    'address' => $attendance ? $attendance->punch_in_address : null
                ],
                'punch_out_location' => [          // ← ADD THIS
                    'latitude' => $attendance ? $attendance->punch_out_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_out_longitude : null,
                    'address' => $attendance ? $attendance->punch_out_address : null
                ],
                'working_hours' => $attendance ? $attendance->working_hours : '--'
            ],
            'leave_stats' => [
                'total_taken' => (float) $totalLeavesTaken,
                'balance' => (float) $leaveBalance,
                'allocated' => (float) $totalLeaveAllocated,
            ],
            'attendance_history' => $attendanceHistory,
            'can_punch' => $canPunch,
            'pending_wfh_count' => WfhRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            'recent_leaves' => LeaveRequest::where('employee_id', $employee->id)->latest()->take(5)->get(),
            'project_assignments' => $projectAssignments,
            'missed_punch_ins' => [
                'count' => count($missedPunchIns),
                'days' => $missedPunchIns,
            ],
        ]);
    }

    /**
     * Punch In
     */
    public function punchIn(Request $request): JsonResponse
    {
        $request->validate([
            'punch_in_latitude' => 'nullable|numeric',
            'punch_in_longitude' => 'nullable|numeric',
            'punch_in_address' => 'nullable|string',
            'timezone' => 'nullable|string|timezone',
            'work_location' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check for missing punch out on previous records
        $missingPunchOut = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if ($missingPunchOut) {
            return $this->error("You have a pending punch-out for {$missingPunchOut->log_date}. Please complete your project timings and punch out for that day first.", 403);
        }

        $timezone = $request->input('timezone', config('app.timezone'));
        session(['employee_timezone' => $timezone]);

        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $dayName = $now->format('l'); // e.g. "Monday"

        $alreadyPunched = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->exists();

        if ($alreadyPunched) {
            return $this->error('Already punched in today.', 400);
        }

        // ── Late check-in guard ──────────────────────────────────────────────
        // If working hours are configured for today and the employee is trying
        // to punch in more than 10 minutes after the scheduled start_time,
        // block the punch-in and raise a late_check_in attendance request.
        $scheduledStartTime = null;
        $tzLower = strtolower($timezone);

        if (str_contains($tzLower, 'kolkata') || str_contains($tzLower, 'calcutta') || str_contains($tzLower, 'india')) {
            $scheduledStartTime = '10:30:00';
        } elseif (str_contains($tzLower, 'dubai') || str_contains($tzLower, 'uae') || str_contains($tzLower, 'asia/dubai')) {
            $scheduledStartTime = '09:00:00';
        } else {
            $workingHour = WorkingHour::where('day', $dayName)
                ->where('is_enabled', true)
                ->first();
            if ($workingHour && !empty($workingHour->start_time)) {
                $scheduledStartTime = $workingHour->start_time;
            }
        }

        if ($scheduledStartTime) {
            // Build today's scheduled start as a full Carbon datetime
            $scheduledStart = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $today . ' ' . $scheduledStartTime,
                $timezone
            );

            $minutesLate = $scheduledStart->diffInMinutes($now, false);

            if ($minutesLate > 0) {
                $hours = intdiv($minutesLate, 60);
                $minutes = $minutesLate % 60;

                if ($hours > 0 && $minutes > 0) {
                    $lateDuration = "{$hours} hrs {$minutes} mins";
                } elseif ($hours > 0) {
                    $lateDuration = "{$hours} hrs";
                } else {
                    $lateDuration = "{$minutes} mins";
                }
            } else {
                $lateDuration = "On Time";
            } // positive = late

            if ($minutesLate > 10) {
                $scheduledStartFormatted = Carbon::createFromFormat('H:i:s', $scheduledStartTime)->format('h:i A');

                // Check if an approved late_check_in request exists for today
                $approvedRequest = AttendanceRequest::where('employee_id', $employee->id)
                    ->where('type', 'late_check_in')
                    ->where('request_date', $today)
                    ->where('status', 'approved')
                    ->first();

                // Admin approved → allow the punch-in to proceed normally
                if ($approvedRequest) {
                    // fall through to the AttendanceLog::create() below
                }
                // Check if a pending request already exists
                else {
                    $pendingRequest = AttendanceRequest::where('employee_id', $employee->id)
                        ->where('type', 'late_check_in')
                        ->where('request_date', $today)
                        ->where('status', 'pending')
                        ->first();

                    if (!$pendingRequest) {
                        // No request at all — auto-create one now
                        $newAttendanceRequest = AttendanceRequest::create([
                            'employee_id' => $employee->id,
                            'type' => 'late_check_in',
                            'request_date' => $today,
                            'request_time' => $now->format('H:i:s'),
                            'reason' => "Employee attempted to punch in {$lateDuration} late (scheduled: {$scheduledStartTime}).",
                            'status' => 'pending',
                            'timezone' => $timezone,
                            'created_by' => 'admin'
                        ]);
                        $this->notifyHrAdmins('Attendance Request', 'created', $newAttendanceRequest);

                        return $this->error(
                            "Punch-in blocked: you are {$lateDuration} late (scheduled start: {$scheduledStartFormatted}). " .
                            "A late check-in request has been sent to HR for approval.",
                            403
                        );
                    }

                    // Pending request exists — just inform the employee to wait
                    return $this->error(
                        "Punch-in blocked: you are {$lateDuration} late (scheduled start: {$scheduledStartFormatted}). " .
                        "Your late check-in request is pending HR approval. Please wait.",
                        403
                    );
                }
            }
        }
        // ────────────────────────────────────────────────────────────────────

        $log = AttendanceLog::create([
            'userid' => $user->id,
            'log_date' => $today,
            'punch_in' => $now,
            'status' => 1,
            'log_status' => 'IN',
            'punch_in_latitude' => $request->input('punch_in_latitude'),
            'punch_in_longitude' => $request->input('punch_in_longitude'),
            'punch_in_address' => $request->input('punch_in_address'),
            'timezone' => $timezone,
            'work_location' => $request->input('work_location'),
        ]);

        return $this->success($log, 'Punched in successfully.', 201);
    }

    /**
     * Punch Out
     */
    public function punchOut(Request $request): JsonResponse
    {
        $request->validate([
            'punch_out_latitude' => 'nullable|numeric',
            'punch_out_longitude' => 'nullable|numeric',
            'punch_out_address' => 'nullable|string',
            'project_times' => 'nullable|array',
            'project_times.*.project_id' => 'required|exists:projects,id',
            'project_times.*.time_minutes' => 'required|integer|min:0',
            'timezone' => 'nullable|string|timezone',
            'punch_out_time' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Get the active punch in record (could be from today or a previous day they forgot to punch out of)
        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log)
            return $this->error('You have no active punch in.', 400);

        $logDate = $log->log_date;
        $dayOfWeek = Carbon::parse($logDate)->format('l');

        $assignedProjectIds = $employee->projects()->pluck('projects.id')->toArray();
        $totalProjectTime = 0;

        if (count($assignedProjectIds) > 0) {
            $submittedProjectTimes = collect($request->project_times ?? []);
            $totalProjectTime = $submittedProjectTimes->sum('time_minutes');

            if ($submittedProjectTimes) {
                foreach ($submittedProjectTimes as $pt) {
                    ProjectTimeLog::updateOrCreate(
                        ['user_id' => $user->id, 'project_id' => $pt['project_id'], 'date' => $logDate],
                        ['time_taken_minutes' => $pt['time_minutes']]
                    );
                }
            }
        }

        $timezone = $request->input('timezone', $log->timezone ?? config('app.timezone'));
        $punchIn = Carbon::parse($log->punch_in, $timezone);

        if ($request->filled('punch_out_time')) {
            $punchOutInput = $request->punch_out_time;
            $punchOutDate = $request->punch_out_date;

            // Case 1: time-only input like "18:00" or "18:00:00"
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $punchOutInput)) {
                $now = Carbon::parse($punchOutDate . ' ' . $punchOutInput, $timezone);
                if ($now->lt($punchIn)) {
                    $now->addDay();
                }
            }
            // Case 2: full ISO datetime like "2026-06-17T18:00:00+05:30"
            else {
                try {
                    $now = Carbon::parse($punchOutInput);
                } catch (\Exception $e) {
                    return $this->error('Invalid punch_out_time format.', 422);
                }
            }

            // Validate: punch-out cannot be before punch-in
            if ($now->lt($punchIn)) {
                return $this->error('Punch out time cannot be before punch in time.', 422);
            }

            // Validate: punch-out cannot be in the future
            if ($now->gt(Carbon::now($timezone)->addMinutes(1))) {
                return $this->error('Punch out time cannot be in the future.', 422);
            }
        } else {
            $now = Carbon::now($timezone);
        }

        // Calculate working hours
        $totalMinutes = $punchIn->diffInMinutes($now);
        $workingHours = max(0, $totalMinutes);

        // Safety guard against anomalous durations (e.g. stale punch-in from days ago)
        if ($workingHours > 1440) { // more than 24 hours
            \Log::warning("Anomalous working hours for user {$user->id}: {$workingHours} minutes (punch_in: {$punchIn}, punch_out: {$now})");
        }

        if ($totalProjectTime > $workingHours) {
            return $this->error('The total project time cannot exceed the total working hours.', 422);
        }

        $log->update([
            'punch_out' => $now,
            'log_status' => 'OUT',
            'working_hours' => $workingHours,
            'punch_out_latitude' => $request->input('punch_out_latitude'),
            'punch_out_longitude' => $request->input('punch_out_longitude'),
            'punch_out_address' => $request->input('punch_out_address')
        ]);

        return $this->success($log, 'Punched out successfully.');
    }

    /**
     * Start Break
     */
    public function startBreak(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log) {
            return $this->error('You must punch in before starting a break.', 403);
        }

        $activeBreak = $log->breaks()->whereNull('end_time')->first();
        if ($activeBreak) {
            return $this->error('You are already on a break.', 400);
        }

        $timezone = $request->input('timezone', config('app.timezone'));

        $break = $log->breaks()->create([
            'start_time' => Carbon::now($timezone),
        ]);

        return $this->success($break, 'Break started successfully.', 201);
    }

    /**
     * End Break
     */
    public function endBreak(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log) {
            return $this->error('No active punch in found.', 400);
        }

        $activeBreak = $log->breaks()->whereNull('end_time')->first();
        if (!$activeBreak) {
            return $this->error('You are not currently on a break.', 400);
        }

        $timezone = $request->input('timezone', config('app.timezone'));
        $now = Carbon::now($timezone);
        $duration = $activeBreak->start_time->diffInMinutes($now);

        $activeBreak->update([
            'end_time' => $now,
            'duration_minutes' => $duration
        ]);

        return $this->success($activeBreak, 'Break ended successfully.');
    }

    /**
     * Leaves
     */
    public function leaves(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaves = LeaveRequest::with(
            'leaveType',
            'appliedBy',
            'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name'
        )->where('employee_id', $employee->id)->latest()->get();
        $leaves->transform(function ($leave) {

            $leave->applied_by = [
                'user_id' => $leave->appliedBy?->id,
                'employee_name' => $leave->appliedBy?->employee?->first_name . ' ' . $leave->appliedBy?->employee?->last_name,
                'role' => $leave->appliedBy?->role,
            ];

            $leave->unsetRelation('appliedBy');

            if (!empty($leave->document)) {
                $leave->document = asset('storage/' . ltrim($leave->document, '/'));
            }

            return $leave;
        });

        return $this->success([
            'leaves' => $leaves
        ]);
    }

    public function leaveTypesAndBalance(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaveTypes = LeaveType::where('status', true)->get();
        $currentYear = date('Y');

        $totalAllocated = 0;
        $totalTaken = 0;
        $totalBalance = 0;

        $leaveTypesData = $leaveTypes->map(function ($leaveType) use ($employee, $currentYear, &$totalAllocated, &$totalTaken, &$totalBalance) {
            $allocation = LeaveAllocation::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $currentYear)
                ->first();

            $taken = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->sum('duration_days');

            $pending = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->sum('duration_days');

            $allocated = $allocation ? (float) $allocation->allocated_days : 0;
            $balance = $allocated - $taken;

            $totalAllocated += $allocated;
            $totalTaken += $taken;
            $totalBalance += $balance;

            return [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'status' => $leaveType->status,
                'allocated' => $allocated,
                'taken' => $taken,
                'pending' => $pending,
                'balance' => $balance,
            ];
        });

        return $this->success([
            'leave_types' => $leaveTypesData,
            'total_allocated' => $totalAllocated,
            'leaves_taken' => $totalTaken,
            'remaining_balance' => $totalBalance,
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',  // session for start date
            'session2' => 'nullable|in:morning,afternoon',  // session for end date
            'year' => 'nullable|integer',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check if there are overlapping leaves
        $hasOverlap = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($request) {
                $q->where('start_date', '<=', $request->end_date)
                    ->where('end_date', '>=', $request->start_date);
            })
            ->exists();

        if ($hasOverlap) {
            return $this->error('You have already applied/taken leave on the selected date(s).', 422);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        // Check for sick leave document
        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        // ── Duration calculation based on session1 / session2, excluding Sundays ──
        // session1 = session for start_date: 'morning' (from morning = full) | 'afternoon' (from afternoon = half)
        // session2 = session for end_date:   'morning' (until morning = half) | 'afternoon' (until afternoon = full)
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->input('session1', 'morning');   // default: full start day
        $session2 = $request->input('session2', 'afternoon'); // default: full end day

        $durationDays = 0.0;
        $currentDate = $start->copy();
        $holidays = Holiday::pluck('holiday_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday() || in_array($currentDate->toDateString(), $holidays)) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0;
                } else {
                    $durationDays += 0.5;
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        // Balance check
        $currentYear = $request->input('year', date('Y'));
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. You have only $remainingBalance days remaining.", 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $request->input('session1', 'morning'),
            'session2' => $request->input('session2', 'afternoon'),
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
            'applied_by' => auth::id(),
            'status' => 'pending',
        ]);

        $this->notifyHrAdmins('Leave Request', 'created', $leave);

        return $this->success($leave, 'Leave request submitted successfully', 201);
    }

    public function storeMissedPunchInLeave(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',  // session for start date
            'session2' => 'nullable|in:morning,afternoon',  // session for end date
            'year' => 'nullable|integer',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check if there are overlapping leaves
        $hasOverlap = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($request) {
                $q->where('start_date', '<=', $request->end_date)
                    ->where('end_date', '>=', $request->start_date);
            })
            ->exists();

        if ($hasOverlap) {
            return $this->error('You have already applied/taken leave on the selected date(s).', 422);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        // Check for sick leave document
        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        // ── Duration calculation based on session1 / session2, excluding Sundays ──
        // session1 = session for start_date: 'morning' (from morning = full) | 'afternoon' (from afternoon = half)
        // session2 = session for end_date:   'morning' (until morning = half) | 'afternoon' (until afternoon = full)
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->input('session1', 'morning');   // default: full start day
        $session2 = $request->input('session2', 'afternoon'); // default: full end day

        $durationDays = 0.0;
        $currentDate = $start->copy();
        $holidays = Holiday::pluck('holiday_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday() || in_array($currentDate->toDateString(), $holidays)) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0;
                } else {
                    $durationDays += 0.5;
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        // Balance check
        $currentYear = $request->input('year', date('Y'));
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. You have only $remainingBalance days remaining.", 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $request->input('session1', 'morning'),
            'session2' => $request->input('session2', 'afternoon'),
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
            'applied_by' => auth::id(),
            'status' => 'pending',
        ]);

        $this->notifyHrAdmins('Leave Request', 'created', $leave);

        return $this->success($leave, 'Leave request submitted successfully', 201);
    }

    public function showLeave($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::with([
            'leaveType',
            'appliedBy',
            'approver.employee:id,user_id,first_name,last_name',
            'approver.role:id,name'
        ])->where('employee_id', $employee->id)->find($id);

        if (!$leave)
            return $this->error('Leave request not found', 404);

        $leave->applied_by = [
            'user_id' => $leave->appliedBy?->id,
            'employee_name' => $leave->appliedBy?->employee?->first_name . ' ' . $leave->appliedBy?->employee?->last_name,
            'role' => $leave->appliedBy?->role,
        ];

        $leave->unsetRelation('appliedBy');

        if (!empty($leave->document)) {
            $leave->document = asset('storage/' . ltrim($leave->document, '/'));
        }

        return $this->success($leave);
    }

    public function updateLeave(Request $request, $id): JsonResponse
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::where('employee_id', $employee->id)->find($id);

        if (!$leave) {
            return $this->error('Leave request not found', 404);
        }

        if ($leave->status !== 'pending') {
            return $this->error('Only pending leave requests can be updated.', 400);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document') && !$leave->document) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->session1;
        $session2 = $request->session2;

        $durationDays = 0.0;
        $currentDate = $start->copy();
        $holidays = Holiday::pluck('holiday_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday() || in_array($currentDate->toDateString(), $holidays)) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0;
                } else {
                    $durationDays += 0.5;
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        $currentYear = date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. You have only $remainingBalance days remaining.", 422);
        }

        $documentPath = $leave->document;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $leave->update([
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $session1,
            'session2' => $session2,
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
            'applied_by' => auth::id(),
        ]);

        $this->notifyHrAdmins('Leave Request', 'updated', $leave);

        return $this->success($leave, 'Leave request updated successfully');
    }

    /**
     * Task Reports
     */
    public function taskReports(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $reports = TaskReport::where('employee_id', $employee->id)->latest()->get();
        return $this->success($reports);
    }

    public function showTaskReport($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        return $this->success($report);
    }

    public function storeTaskReport(Request $request): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'nullable|string',
            'plan_tomorrow' => 'nullable|string',
            'remarks' => 'nullable|string',
            'date' => 'nullable|date'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $date = $request->date ?? Carbon::today()->toDateString();

        // Check if report already exists for this date
        $report = TaskReport::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            [
                'tasks_completed' => $request->tasks_completed,
                'plan_tomorrow' => $request->plan_tomorrow,
                'remarks' => $request->remarks
            ]
        );

        return $this->success($report, 'Task report saved successfully', $report->wasRecentlyCreated ? 201 : 200);
    }

    public function updateTaskReport(Request $request, $id): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'nullable|string',
            'plan_tomorrow' => 'nullable|string',
            'remarks' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        $report->update($request->only(['tasks_completed', 'plan_tomorrow', 'remarks']));

        return $this->success($report, 'Task report updated successfully');
    }

    public function destroyTaskReport($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        $report->delete();

        return $this->success(null, 'Task report deleted successfully');
    }

    /**
     * WFH Requests
     */
    public function wfhRequests(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $requests = WfhRequest::where('employee_id', $employee->id)->latest()->get();
        return $this->success($requests);
    }

    public function showWfhRequest($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $request = WfhRequest::where('employee_id', $employee->id)->find($id);
        if (!$request) {
            return $this->error('WFH request not found', 404);
        }

        return $this->success($request);
    }

    public function storeWfhRequest(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check for duplicate request on the same date
        $exists = WfhRequest::where('employee_id', $employee->id)
            ->whereDate('date', $request->date)
            ->exists();

        if ($exists) {
            return $this->error('You have already submitted a WFH request for this date.', 422);
        }

        $wfh = WfhRequest::create([
            'employee_id' => $employee->id,
            'date' => $request->date,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'status' => 'pending'
        ]);

        $this->notifyHrAdmins('Work From Home Request', 'created', $wfh);

        return $this->success($wfh, 'WFH request submitted successfully', 201);
    }

    public function updateWfhRequest(Request $request, $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $wfh = WfhRequest::where('employee_id', $employee->id)->find($id);

        if (!$wfh) {
            return $this->error('WFH request not found', 404);
        }

        if ($wfh->status !== 'pending') {
            return $this->error('Only pending requests can be updated.', 400);
        }

        $wfh->update($request->only(['date', 'reason', 'notes']));

        $this->notifyHrAdmins('Work From Home Request', 'updated', $wfh);

        return $this->success($wfh, 'WFH request updated successfully');
    }

    public function destroyWfhRequest($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $wfh = WfhRequest::where('employee_id', $employee->id)->find($id);

        if (!$wfh) {
            return $this->error('WFH request not found', 404);
        }

        if ($wfh->status !== 'pending') {
            return $this->error('Only pending requests can be deleted.', 400);
        }

        $wfh->delete();

        return $this->success(null, 'WFH request deleted successfully');
    }


    public function destroyLeave($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::where('employee_id', $employee->id)->find($id);

        if (!$leave) {
            return $this->error('Leave request not found', 404);
        }

        if ($leave->status !== 'pending') {
            return $this->error('Only pending leave requests can be deleted.', 400);
        }

        $leave->delete();

        return $this->success(null, 'Leave request deleted successfully');
    }

    /**
     * Get logged-in employee's completed salary summary & payment history.
     */
    public function mySalarySummary(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();

        // Fetch completed payrolls for this logged-in user
        $payrolls = Payroll::where(function ($q) use ($user, $employee) {
            $q->where('user_id', $user->id);
            if ($employee) {
                $q->orWhere('user_id', $user->id);
            }
        })
            ->where('status', 'completed')
            ->orderBy('pay_period_year', 'desc')
            ->orderBy('pay_period_month', 'desc')
            ->get();

        $totalEarnings = 0;
        $monthsGeneratedCount = $payrolls->count();
        $paymentHistory = [];

        foreach ($payrolls as $payroll) {
            $totalEarnings += (float) ($payroll->net_pay ?? 0);
            $paymentDate = ($payroll->status === 'completed' && $payroll->updated_at)
                ? $payroll->updated_at->toDateString()
                : null;

            $paymentHistory[] = [
                'id' => $payroll->id,
                'month' => $payroll->pay_period_month,
                'year' => $payroll->pay_period_year,
                'month_year' => Carbon::createFromDate($payroll->pay_period_year, $payroll->pay_period_month, 1)->format('F Y'),
                'gross_pay' => (float) ($payroll->gross_salary ?? 0),
                'deductions' => (float) ($payroll->deductions ?? 0),
                'overtime' => (float) ($payroll->overtime ?? 0),
                'net_pay' => (float) ($payroll->net_pay ?? 0),
                'currency' => $payroll->currency ?? 'AED',
                'status' => $payroll->status,
                'payment_date' => $paymentDate,
            ];
        }

        return $this->success([
            'employee_id' => $employee?->id,
            'user_id' => $user->id,
            'employee_name' => $employee ? trim($employee->first_name . ' ' . $employee->last_name) : $user->name,
            'total_earnings' => round($totalEarnings, 2),
            'months_generated_count' => $monthsGeneratedCount,
            'payment_history' => $paymentHistory,
        ]);
    }

    /**
     * Get logged-in employee's completed salary history list.
     */
    public function mySalaryHistory(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();

        $payrolls = Payroll::where(function ($q) use ($user, $employee) {
            $q->where('user_id', $user->id);
            if ($employee) {
                $q->orWhere('user_id', $employee->user_id);
            }
        })
            ->where('status', 'completed')
            ->orderBy('pay_period_year', 'desc')
            ->orderBy('pay_period_month', 'desc')
            ->get()
            ->map(function ($payroll) use ($employee, $user) {
                return [
                    'id' => $payroll->id,
                    'employee_name' => $employee ? trim($employee->first_name . ' ' . $employee->last_name) : $user->name,
                    'employee_id' => $payroll->user_id,
                    'month' => $payroll->pay_period_month,
                    'year' => $payroll->pay_period_year,
                    'month_year' => Carbon::createFromDate($payroll->pay_period_year, $payroll->pay_period_month, 1)->format('F Y'),
                    'gross_salary' => (float) ($payroll->gross_salary ?? 0),
                    'deductions' => (float) ($payroll->deductions ?? 0),
                    'overtime' => (float) ($payroll->overtime ?? 0),
                    'net_pay' => (float) ($payroll->net_pay ?? 0),
                    'currency' => $payroll->currency ?? 'AED',
                    'status' => $payroll->status,
                    'payment_date' => $payroll->updated_at ? $payroll->updated_at->toDateString() : null,
                ];
            });

        return $this->success($payrolls);
    }

    /**
     * Download logged-in employee's own payslip PDF.
     */
    public function downloadMyPayslip($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();

        $payroll = Payroll::with(['employee.user.designation', 'employee.bankDetails'])
            ->where(function ($q) use ($user, $employee) {
                $q->where('user_id', $user->id);
                if ($employee) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->find($id);

        if (!$payroll) {
            return $this->error('Payslip not found or access denied', 404);
        }

        if ($payroll->status !== 'completed') {
            return $this->error('Cannot download payslip for incomplete payroll', 400);
        }

        try {
            if (!view()->exists('pdf.payslip')) {
                return $this->error('Payslip template not found', 500);
            }

            $monthStr = $payroll->pay_period_year . '-' . $payroll->pay_period_month;

            $startDate = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $monthStr)->endOfMonth();

            // Fetch holidays in the selected month
            $holidays = Holiday::whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->pluck('holiday_date')
                ->map(fn($date) => Carbon::parse($date)->toDateString())
                ->toArray();

            $totalDays = $startDate->diffInDays($endDate) + 1;
            $workingDays = 0;
            $sundays = [];

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->toDateString();
                if ($currentDate->isSunday()) {
                    $sundays[] = $dateStr;
                } elseif (in_array($dateStr, $holidays)) {
                    // Skip holiday
                } else {
                    $workingDays++;
                }
                $currentDate->addDay();
            }

            $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $payroll, 'working_days' => $workingDays]);
            $monthName = Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F');
            $fileName = "Payslip_{$monthName}_{$payroll->pay_period_year}.pdf";

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate PDF for payroll {$payroll->id}: " . $e->getMessage());
            return $this->error('Failed to generate payslip: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get the logged-in employee's uploaded documents.
     *
     * Returns every document field stored on the employee record
     * as a fully-resolved public URL (null when not uploaded yet).
     */
    public function myDocuments(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        // All document fields stored on the employees table
        $documentFields = [
            'avatar',
            'passport_1st_page',
            'passport_2nd_page',
            'passport_outer_page',
            'passport_id_page',
            'visa_page',
            'labor_card',
            'eid_1st_page',
            'eid_2nd_page',
            'educational_1st_page',
            'educational_2nd_page',
            'home_country_id_proof',
        ];

        $documents = [];
        foreach ($documentFields as $field) {
            $path = $employee->$field;
            $documents[$field] = $path
                ? asset('storage/' . ltrim($path, '/'))
                : null;
        }

        // Include additional_documents (JSON array of extra uploads)
        $additional = [];
        if (!empty($employee->additional_documents) && is_array($employee->additional_documents)) {
            foreach ($employee->additional_documents as $doc) {
                $additional[] = [
                    'label' => $doc['label'] ?? null,
                    'url' => isset($doc['path'])
                        ? asset('storage/' . ltrim($doc['path'], '/'))
                        : null,
                ];
            }
        }

        return $this->success([
            'employee_id' => $employee->id,
            'documents' => $documents,
            'additional_documents' => $additional,
        ]);
    }

    /**
     * Upload a temporary document file during employee onboarding.
     *
     * The returned `path` value should be sent back in the employee
     * create/update payload so the server can move it to permanent storage.
     */
    public function uploadTempDocument(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            $file = $request->file('file');

            if (!$file->isValid()) {
                return $this->error('Invalid file upload', 400);
            }

            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('temp')) {
                Storage::disk('public')->makeDirectory('temp');
            }

            $path = Storage::disk('public')->putFileAs('temp', $file, $fileName);

            if (!$path) {
                return $this->error('File storage failed', 500);
            }

            return $this->success([
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ], 'File uploaded successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    private function parseMultipartPut(Request $request): void
    {
        if (!$request->isMethod('PUT') && !$request->isMethod('PATCH')) {
            return;
        }

        $contentType = $request->header('Content-Type');
        if (!$contentType || !str_contains($contentType, 'multipart/form-data')) {
            return;
        }

        preg_match('/boundary=(.*)$/', $contentType, $matches);
        if (empty($matches)) {
            return;
        }
        $boundary = $matches[1];

        $rawContent = $request->getContent();
        $parts = preg_split('/-+' . preg_quote($boundary, '/') . '/', $rawContent);

        $inputs = [];
        $files = [];

        foreach ($parts as $part) {
            if (empty(trim($part)) || $part === '--' || $part === "--\r\n") {
                continue;
            }

            $parts2 = explode("\r\n\r\n", $part, 2);
            if (count($parts2) < 2) {
                continue;
            }
            $headersStr = $parts2[0];
            $body = substr($parts2[1], 0, -2); // remove trailing \r\n

            preg_match('/name="([^"]+)"/', $headersStr, $nameMatch);
            if (empty($nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];

            preg_match('/filename="([^"]+)"/', $headersStr, $filenameMatch);
            if (!empty($filenameMatch)) {
                $filename = $filenameMatch[1];
                preg_match('/Content-Type:\s*([^\s;]+)/', $headersStr, $typeMatch);
                $mimeType = $typeMatch[1] ?? 'application/octet-stream';

                $tempPath = tempnam(sys_get_temp_dir(), 'laravel_upload_');
                file_put_contents($tempPath, $body);

                $files[$name] = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    $filename,
                    $mimeType,
                    null,
                    true // test mode
                );
            } else {
                $inputs[$name] = $body;
            }
        }

        $request->merge($inputs);
        $request->files->add($files);
    }

    /**
     * Send an email notification to all HR and Admin users.
     *
     * @param string $requestType  Human-readable request type label (e.g. "Leave Request")
     * @param string $action       'created' or 'updated'
     * @param mixed  $requestModel The Eloquent model instance that was created/updated
     */
    private function notifyHrAdmins(string $requestType, string $action, $requestModel): void
    {
        try {
            $hrAdmins = User::whereIn('type', ['hr', 'admin'])
                ->whereNotNull('email')
                ->get();

            foreach ($hrAdmins as $recipient) {
                Mail::to($recipient->email)
                    ->send(new RequestNotificationMail($requestType, $action, $requestModel));
            }
        } catch (\Throwable $e) {
            // Log silently — do not break the request flow
            \Illuminate\Support\Facades\Log::error('RequestNotificationMail failed: ' . $e->getMessage());
        }
    }
}
