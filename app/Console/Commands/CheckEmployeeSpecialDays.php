<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\EmployeeSpecialDayNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class CheckEmployeeSpecialDays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:check-special-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for employee special days (Birthdays, Work Anniversaries, Custom Dates) and notify all users.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Log::info('HR special days check started at ' . now());

        $today = Carbon::today();
        $todayMonth = $today->month;
        $todayDay = $today->day;
        
        $notifiedCount = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<User> $allUsers */
        $allUsers = User::all();

        if ($allUsers->isEmpty()) {
            $this->warn('No users found to notify.');
            Log::warning('HR special days check: No users found.');
            return;
        }

        // Only active employees
        $employees = Employee::where('status', 'active')->orWhereNull('status')->get();

        foreach ($employees as $employee) {
            $fullName = trim($employee->first_name . ' ' . $employee->last_name);
            
            // Check Birthday
            if ($employee->dob) {
                try {
                    $dob = Carbon::parse($employee->dob);
                    if ($dob->month === $todayMonth && $dob->day === $todayDay) {
                        $this->sendNotification($allUsers, 'Birthday', $fullName, $today->format('Y-m-d'), "Today is {$fullName}'s Birthday!");
                        $notifiedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error parsing DOB for employee {$employee->id}: " . $e->getMessage());
                }
            }

            // Check Work Anniversary
            if ($employee->joining_date) {
                try {
                    $joiningDate = Carbon::parse($employee->joining_date);
                    // Ensure it's not their actual first day (0 years anniversary) unless desired, but usually anniversary means 1+ years.
                    // We'll just check month and day.
                    if ($joiningDate->month === $todayMonth && $joiningDate->day === $todayDay && $joiningDate->year < $today->year) {
                        $years = $today->year - $joiningDate->year;
                        $this->sendNotification($allUsers, 'Work Anniversary', $fullName, $today->format('Y-m-d'), "Today is {$fullName}'s {$years} year Work Anniversary!");
                        $notifiedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error parsing joining_date for employee {$employee->id}: " . $e->getMessage());
                }
            }

            // Check custom special days
            if (is_array($employee->special_days)) {
                foreach ($employee->special_days as $specialDay) {
                    $dateStr = is_array($specialDay) ? ($specialDay['date'] ?? null) : null;
                    $name = is_array($specialDay) ? ($specialDay['name'] ?? 'Special Event') : 'Special Event';
                    
                    if ($dateStr) {
                        try {
                            $customDate = Carbon::parse($dateStr);
                            if ($customDate->month === $todayMonth && $customDate->day === $todayDay) {
                                $this->sendNotification($allUsers, $name, $fullName, $today->format('Y-m-d'), "Today is {$fullName}'s {$name}!");
                                $notifiedCount++;
                            }
                        } catch (\Exception $e) {
                            Log::error("Error parsing special_days date for employee {$employee->id}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $this->info("HR special days check complete. Sent {$notifiedCount} notification event(s).");
        Log::info("HR special days check complete. Sent {$notifiedCount} notification event(s).");
    }

    private function sendNotification($users, $type, $employeeName, $date, $message)
    {
        $alertData = [
            'type' => $type,
            'employee' => $employeeName,
            'date' => $date,
            'message' => $message,
        ];

        Notification::send($users, new EmployeeSpecialDayNotification($alertData));
        Log::info("Special Day alert sent: {$employeeName} - {$type}");
    }
}
