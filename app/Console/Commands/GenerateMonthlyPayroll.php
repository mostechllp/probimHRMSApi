<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\AttendanceLog;
use App\Models\EmployeeSalaryPackage;

class GenerateMonthlyPayroll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --month=Y-m  Override the target month (defaults to previous month).
     *
     * @var string
     */
    protected $signature = 'payroll:generate-monthly {--month= : Target month in Y-m format (defaults to previous month)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate payroll records for all active employees for the previous month.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $month = $this->option('month')
            ?? Carbon::now()->subMonth()->format('Y-m');

        // Validate month format
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $this->error("Invalid month format: {$month}. Expected Y-m (e.g. 2026-07).");
            return self::FAILURE;
        }

        $payPeriodMonth = (int) $monthDate->format('m');
        $payPeriodYear = (int) $monthDate->format('Y');

        $this->info("Generating payroll for month: {$month}");
        Log::info("GenerateMonthlyPayroll started for month: {$month}");

        // Fetch all active employees
        $employees = Employee::whereHas('user', fn($q) => $q->where('status', 'active'))->get();

        if ($employees->isEmpty()) {
            $this->warn('No active employees found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$employees->count()} employee(s)...");

        $generatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($employees as $employee) {
            $employeeName = trim($employee->first_name . ' ' . $employee->last_name)
                ?: "User #{$employee->user_id}";

            // ── Skip if payroll already exists ──────────────────────────────
            $exists = Payroll::where('user_id', $employee->user_id)
                ->where('pay_period_month', $payPeriodMonth)
                ->where('pay_period_year', $payPeriodYear)
                ->exists();

            if ($exists) {
                $this->line("  [SKIP] {$employeeName} — payroll already exists.");
                $skippedCount++;
                continue;
            }

            try {
                $result = $this->buildPayrollForEmployee($employee, $month, $monthDate);

                if (!$result['success']) {
                    $this->warn("  [SKIP] {$employeeName} — {$result['skip_reason']}");
                    $skippedCount++;
                    continue;
                }

                $payroll = new Payroll();
                $payroll->user_id = $employee->user_id;
                $payroll->pay_period_month = $payPeriodMonth;
                $payroll->pay_period_year = $payPeriodYear;
                $payroll->gross_salary = $result['gross_salary'];
                $payroll->net_pay = $result['net_pay'];
                $payroll->overtime = $result['overtime'];
                $payroll->deductions = $result['deductions'];
                $payroll->currency = $result['currency'];
                $payroll->status = 'generated';
                $payroll->current_step = 1;
                $payroll->data = $result['data'];
                $payroll->save();

                $this->info(
                    "  [OK]   {$employeeName} — ID #{$payroll->id}" .
                    " | Gross: {$result['currency']} {$result['gross_salary']}"
                );
                Log::info("Payroll generated: user_id={$employee->user_id}, payroll_id={$payroll->id}, month={$month}");

                $generatedCount++;
            } catch (\Throwable $e) {
                $this->error("  [FAIL] {$employeeName} — {$e->getMessage()}");
                Log::error("Payroll generation failed for user {$employee->user_id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("Done. Generated: {$generatedCount} | Skipped: {$skippedCount} | Failed: {$failedCount}");
        Log::info("GenerateMonthlyPayroll finished. Generated={$generatedCount}, Skipped={$skippedCount}, Failed={$failedCount}");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Core salary calculation — mirrors PayrollController::buildPayrollForEmployee
    // -------------------------------------------------------------------------

    private function buildPayrollForEmployee(Employee $employee, string $month, Carbon $monthDate): array
    {
        $startDate = $monthDate->copy()->startOfMonth()->toDateString();
        $endDate = $monthDate->copy()->endOfMonth()->toDateString();

        // 1. Attendance
        $attendanceLogs = AttendanceLog::where('userid', $employee->user_id)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('log_status', 'out')
            ->get();

        if ($attendanceLogs->isEmpty()) {
            return ['success' => false, 'skip_reason' => 'No attendance records found'];
        }

        // 2. Working days (Mon–Sat)
        $workingDays = max(
            1,
            $monthDate->copy()->startOfMonth()
                ->diffInWeekdays($monthDate->copy()->endOfMonth()) + 1
        );

        // 3. Salary packages
        $salaryPackages = EmployeeSalaryPackage::where('employee_id', $employee->id)
            ->with('salaryComponents')
            ->get();

        if ($salaryPackages->isEmpty()) {
            return ['success' => false, 'skip_reason' => 'No salary packages found'];
        }

        $dubaiPackage = null;
        $homePackage = null;

        foreach ($salaryPackages as $pkg) {
            if (strtoupper($pkg->currency) === 'AED') {
                $dubaiPackage = $pkg;
            } else {
                $homePackage = $pkg;
            }
        }

        // 4. Location-based salary calculation
        $uaeKeywords = [
            'united arab emirates',
            'uae',
            'dubai',
            'abu dhabi',
            'sharjah',
            'ajman',
            'fujairah',
            'ras al khaimah',
            'umm al quwain',
            'rak',
            'al ain',
        ];

        $grouped = $attendanceLogs->groupBy(fn($log) => $log->work_location ?: 'Unknown');
        $locationBreakdown = [];
        $totalEarnings = 0.0;
        $primaryCurrency = $dubaiPackage ? 'AED' : ($homePackage?->currency ?? 'AED');

        foreach ($grouped as $locationName => $logs) {
            $workedDays = $logs->count();
            $locLower = strtolower($locationName);

            $isUae = false;
            foreach ($uaeKeywords as $kw) {
                if (str_contains($locLower, $kw)) {
                    $isUae = true;
                    break;
                }
            }

            $selectedPackage = $isUae ? $dubaiPackage : $homePackage;
            if (!$selectedPackage) {
                continue;
            }

            $componentsData = [];
            $subtotal = 0.0;

            foreach ($selectedPackage->salaryComponents as $component) {
                $dailyAmount = (float) $component->value / $workingDays;
                $amount = round($dailyAmount * $workedDays, 2);

                $componentsData[] = [
                    'id' => $component->id,
                    'name' => $component->component_name,
                    'amount' => $amount,
                ];

                $subtotal += $amount;
            }

            $pkgDetails = $selectedPackage->toArray();
            unset($pkgDetails['salary_components']);

            $locationBreakdown[] = [
                'location_name' => $locationName,
                'package' => $pkgDetails,
                'worked_days' => $workedDays,
                'currency' => [
                    'code' => $selectedPackage->currency,
                    'symbol' => $selectedPackage->currency,
                ],
                'salary_components' => $componentsData,
                'subtotal' => round($subtotal, 2),
            ];

            if ($subtotal > 0) {
                $primaryCurrency = $selectedPackage->currency;
            }

            $totalEarnings += $subtotal;
        }

        if (empty($locationBreakdown)) {
            return ['success' => false, 'skip_reason' => 'No matching salary package for any worked location'];
        }

        // 5. Totals
        $grossSalary = round($totalEarnings, 2);
        $totalDeductions = 0.0;
        $overtime = 0.0;
        $netPay = round($grossSalary + $overtime - $totalDeductions, 2);
        $employeeName = trim($employee->first_name . ' ' . $employee->last_name);

        $dataBlob = [
            'step_1' => [
                'pay_period_month' => (int) $monthDate->format('m'),
                'pay_period_year' => (int) $monthDate->format('Y'),
                'period_start' => null,
                'period_end' => null,
                'payment_date' => null,
                'payment_mode' => null,
                'total_working_days' => $workingDays,
                'days_present' => $attendanceLogs->count(),
            ],
            'step_2' => [
                'pay_period_month' => (int) $monthDate->format('m'),
                'pay_period_year' => (int) $monthDate->format('Y'),
                'package_ids' => array_values(array_filter([$dubaiPackage?->id, $homePackage?->id])),
                'location_breakdown' => $locationBreakdown,
                'total_earnings' => $grossSalary,
                'total_deductions' => 0.0,
                'gross_salary' => $grossSalary,
                'net_salary' => $netPay,
            ],
            'step_3' => [
                'pay_period_month' => (int) $monthDate->format('m'),
                'pay_period_year' => (int) $monthDate->format('Y'),
                'overtime_details' => [],
                'total_overtime_amount' => 0.0,
            ],
            'step_4' => [
                'pay_period_month' => (int) $monthDate->format('m'),
                'pay_period_year' => (int) $monthDate->format('Y'),
                'deductions' => [],
                'total_deductions' => 0.0,
            ]
        ];

        return [
            'success' => true,
            'skip_reason' => null,
            'gross_salary' => $grossSalary,
            'net_pay' => $netPay,
            'overtime' => $overtime,
            'deductions' => $totalDeductions,
            'currency' => $primaryCurrency,
            'data' => $dataBlob,
        ];
    }
}
