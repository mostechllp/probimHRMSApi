<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\ProbationContractAlertNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class CheckProbationContractExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:check-probation-contract';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for employees with probation end or contract renewal within 30 days and notify admin users via email.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Log::info('HR probation/contract expiry check started at ' . now());

        $today = Carbon::today();
        $thresholdDate = $today->copy()->addDays(30);
        $notifiedCount = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<User> $adminUsers */
        $adminUsers = User::where('type', 'hr')->orWhere('type', 'admin')->get();

        if ($adminUsers->isEmpty()) {
            $this->warn('No admin users found to notify.');
            Log::warning('HR check: No admin users found.');
            return;
        }

        // ── Probation End ────────────────────────────────────────────────────────
        $probationEnding = Employee::whereBetween('probation_end_date', [$today, $thresholdDate])->get();

        foreach ($probationEnding as $employee) {
            $fullName = trim($employee->first_name . ' ' . $employee->last_name);
            $daysLeft = (int) Carbon::parse($employee->probation_end_date)->diffInDays($today);
            $alertData = [
                'type' => 'probation',
                'employee' => $fullName,
                'employee_id' => $employee->employee_id,
                'due_date' => $employee->probation_end_date,
                'days_left' => $daysLeft,
                'message' => "The probation period for employee '{$fullName}' ({$employee->employee_id}) is ending on {$employee->probation_end_date}.",
            ];

            Notification::send($adminUsers, new ProbationContractAlertNotification($alertData));
            $notifiedCount++;

            Log::info("Probation alert sent: {$fullName} ({$employee->employee_id}) – ends {$employee->probation_end_date}");
        }

        // ── Contract Renewal ─────────────────────────────────────────────────────
        $contractRenewals = Employee::whereBetween('contract_end_date', [$today, $thresholdDate])->get();

        foreach ($contractRenewals as $employee) {
            $fullName = trim($employee->first_name . ' ' . $employee->last_name);
            $daysLeft = (int) Carbon::parse($employee->contract_end_date)->diffInDays($today);
            $alertData = [
                'type' => 'contract',
                'employee' => $fullName,
                'employee_id' => $employee->employee_id,
                'due_date' => $employee->contract_end_date,
                'days_left' => $daysLeft,
                'message' => "The employment contract for employee '{$fullName}' ({$employee->employee_id}) is due for renewal on {$employee->contract_end_date}.",
            ];

            Notification::send($adminUsers, new ProbationContractAlertNotification($alertData));
            $notifiedCount++;

            Log::info("Contract renewal alert sent: {$fullName} ({$employee->employee_id}) – ends {$employee->contract_end_date}");
        }

        $this->info("HR check complete. Sent {$notifiedCount} notification(s).");
        Log::info("HR probation/contract check complete. Sent {$notifiedCount} notification(s).");
    }
}
