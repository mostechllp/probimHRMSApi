<?php

namespace App\Console\Commands;

use App\Mail\PunchInReminderMail;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPunchInReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:send-punchin-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email employees who have not punched in yet, once 1 hour has passed since their scheduled working-hour start time.';

    /**
     * How wide the reminder window is. The command is expected to run on
     * this same cadence (see bootstrap/app.php), so each day's window is
     * only ever entered once.
     */
    private const WINDOW_MINUTES = 30;

    public function handle(): int
    {
        $timezone = config('app.timezone');
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $dayName = $now->format('l');

        $workingHour = WorkingHour::where('day', $dayName)
            ->where('is_enabled', true)
            ->first();

        if (!$workingHour || empty($workingHour->start_time)) {
                Log::info('Punch-in reminder: outside reminder window');
            $this->info("No working hours configured for {$dayName}. Nothing to do.");
            return self::SUCCESS;
        }

        if (Holiday::whereDate('holiday_date', $today)->exists()) {
            $this->info('Today is a holiday. Nothing to do.');
            return self::SUCCESS;
        }

        $scheduledStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$today} {$workingHour->start_time}", $timezone);
        $windowStart = $scheduledStart->copy()->addHour();
        $windowEnd = $windowStart->copy()->addMinutes(self::WINDOW_MINUTES);

        $nowIndia = Carbon::now('Asia/Kolkata');
        $indiaWindowStart = Carbon::createFromTime(10, 30, 0, 'Asia/Kolkata');
        $indiaWindowEnd = $indiaWindowStart->copy()->addMinutes(self::WINDOW_MINUTES);

        $nowDubai = Carbon::now('Asia/Dubai');
        $dubaiWindowStart = Carbon::createFromTime(9, 5, 0, 'Asia/Dubai');
        $dubaiWindowEnd = $dubaiWindowStart->copy()->addMinutes(self::WINDOW_MINUTES);

        Log::info('Punch-in reminder check', [
            'now' => $now->format('Y-m-d H:i:s T'),
            'today' => $today,
            'day' => $dayName,
            'scheduled_start' => $scheduledStart->format('Y-m-d H:i:s T'),
            'window_start' => $windowStart->format('Y-m-d H:i:s T'),
            'window_end' => $windowEnd->format('Y-m-d H:i:s T'),
        ]);

        $defaultInWindow = $now->gte($windowStart) && $now->lt($windowEnd);
        $indiaInWindow = $nowIndia->gte($indiaWindowStart) && $nowIndia->lt($indiaWindowEnd);
        $dubaiInWindow = $nowDubai->gte($dubaiWindowStart) && $nowDubai->lt($dubaiWindowEnd);

        if (!$defaultInWindow && !$indiaInWindow && !$dubaiInWindow) {
            Log::info('Outside the 1-hour-late reminder window. Nothing to do.');
            $this->info('Outside the 1-hour-late reminder window. Nothing to do.');
            return self::SUCCESS;
        }

        $punchedInUserIds = AttendanceLog::whereDate('log_date', $today)->pluck('userid');

        $onLeaveEmployeeIds = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id');

        $employees = Employee::whereHas('user', fn($q) => $q->where('status', 'active'))
            ->whereNotIn('user_id', $punchedInUserIds)
            ->whereNotIn('id', $onLeaveEmployeeIds)
            ->get();

        $employees = $employees->filter(function ($employee) use ($indiaInWindow, $dubaiInWindow, $defaultInWindow) {
            $isIndia = $employee->currency === 'INR';
            $isDubai = $employee->currency === 'AED';

            if ($isIndia) return $indiaInWindow;
            if ($isDubai) return $dubaiInWindow;
            return $defaultInWindow;
        });

        if ($employees->isEmpty()) {
            $this->info('No employees to remind.');
            return self::SUCCESS;
        }

        $defaultScheduledStartFormatted = $scheduledStart->format('h:i A');
        $sent = 0;

        foreach ($employees as $employee) {
            $isIndia = $employee->currency === 'INR';
            $isDubai = $employee->currency === 'AED';

            $empScheduledStartFormatted = $defaultScheduledStartFormatted;
            if ($isIndia) {
                $empScheduledStartFormatted = $indiaWindowStart->copy()->subHour()->format('h:i A');
            } elseif ($isDubai) {
                $empScheduledStartFormatted = $dubaiWindowStart->copy()->subHour()->format('h:i A');
            }

            $recipient = $employee->personal_email ?: $employee->company_email;

            if (!$recipient) {
                continue;
            }

            try {
                Mail::to($recipient)->send(new PunchInReminderMail($employee, $today, $empScheduledStartFormatted));
                $sent++;
            } catch (\Exception $e) {
                Log::error("Failed to send punch-in reminder to employee {$employee->id}: " . $e->getMessage());
            }
        }

        $this->info("Punch-in reminders sent: {$sent} / {$employees->count()}");
        Log::info("SendPunchInReminders: sent {$sent} reminder(s) for {$today}.");

        return self::SUCCESS;
    }
}
