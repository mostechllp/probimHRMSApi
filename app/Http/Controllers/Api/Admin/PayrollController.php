<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalaryPackage;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\AttendanceLog;
use App\Models\WorkingHour;
use App\Models\ProjectTimeLog;
use App\Models\EmployeeSalaryComponent;

class PayrollController extends Controller
{
    /**
     * List all payrolls with employee name, month/year, net pay, status, and payment date.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with('employee')
            ->orderBy('pay_period_year', 'desc')
            ->orderBy('pay_period_month', 'desc');

        // Optional filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Optional filter by employee_id
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        $payrolls = $query->get()->map(fn($payroll) => $this->formatPayroll($payroll));

        return response()->json([
            'success' => true,
            'data' => $payrolls,
        ]);
    }

    /**
     * Get stats for payrolls: total generated, total amount, total paid, and pending payrolls.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Payroll::query();

        if ($request->has('pay_period_month')) {
            $query->where('pay_period_month', (int) $request->query('pay_period_month'));
        }
        if ($request->has('pay_period_year')) {
            $query->where('pay_period_year', (int) $request->query('pay_period_year'));
        }
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        $payrolls = $query->get()->map(fn($payroll) => $this->formatPayroll($payroll));

        $totalGenerated = 0;
        $totalPending = 0;
        $amountsByCurrency = [];

        foreach ($payrolls as $payroll) {
            $status = strtolower($payroll['status'] ?? '');
            $currency = strtoupper($payroll['currency'] ?? 'AED');

            if (!isset($amountsByCurrency[$currency])) {
                $amountsByCurrency[$currency] = [
                    'total_amount' => 0,
                    'total_paid' => 0,
                ];
            }

            if (in_array($status, ['draft', 'pending'])) {
                $totalPending++;
            }

            // Generated / Completed includes everything that is finalized
            if (in_array($status, ['completed', 'generated', 'paid'])) {
                $totalGenerated++;
                $netPay = (float) ($payroll['net_pay'] ?? 0);

                $amountsByCurrency[$currency]['total_amount'] += $netPay;

                if ($status === 'paid' || $status === 'completed') {
                    $amountsByCurrency[$currency]['total_paid'] += $netPay;
                }
            }
        }

        // Round amounts
        foreach ($amountsByCurrency as $curr => $amounts) {
            $amountsByCurrency[$curr]['total_amount'] = round($amounts['total_amount'], 2);
            $amountsByCurrency[$curr]['total_paid'] = round($amounts['total_paid'], 2);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_generated' => $totalGenerated,
                'total_pending' => $totalPending,
                'amounts_by_currency' => $amountsByCurrency,
            ]
        ]);
    }

    /**
     * Calculate monthly salary details for an employee based on attendance,
     * work locations, and assigned salary packages.
     */
    public function calculateMonthlySalary(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $employeeId = $request->employee_id;
        $month = $request->month;

        $employee = Employee::where('user_id', $employeeId)->first();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        // Fetch Attendance Logs for the month
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $attendanceLogs = AttendanceLog::where('userid', $employee->user_id) // AttendanceLog uses the string employee_id
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('log_status', 'out')
            ->get();

        if ($attendanceLogs->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No completed attendance records found for this month'], 404);
        }

        $groupedByLocation = $attendanceLogs->groupBy('work_location');
        $totalWorkedDays = $attendanceLogs->count();
        // Get working days for the payroll month
        $workingDays = Carbon::createFromFormat('Y-m', $month)
            ->startOfMonth()
            ->diffInWeekdays(
                Carbon::createFromFormat('Y-m', $month)->endOfMonth()
            ) + 1;

        $locationBreakdown = [];
        $totalEarnings = 0;

        // Load salary packages for this employee via employee_salary_components
        // (employee_salary_packages has no employee_id; the employee link is in employee_salary_components)
        $employeeSalaryPackages = EmployeeSalaryPackage::where('employee_id', $employee->id)
            ->with('salaryComponents')
            ->get();

        if ($employeeSalaryPackages->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No active salary packages found for this employee'], 404);
        }

        foreach ($employeeSalaryPackages as $employeeSalaryPackage) {
            if ($employeeSalaryPackage->currency == 'AED') {
                $dubaiPackageData = $employeeSalaryPackage;
            } else {
                $homePackageData = $employeeSalaryPackage;
            }
        }

        foreach ($groupedByLocation as $locationName => $logs) {
            $locationName = $locationName ?: 'Unknown';
            $workedDays = $logs->count();

            // Determine if location is Dubai-related
            $isDubaiLocation = false;
            $locLower = strtolower($locationName);
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
                'umm al quwain',
                'rak',
                'al ain'
            ];
            foreach ($uaeKeywords as $keyword) {
                if (str_contains($locLower, $keyword)) {
                    $isDubaiLocation = true;
                    break;
                }
            }

            // Select the correct package data (array with 'package' and 'components' keys)
            $selectedPackageData = $isDubaiLocation ? $dubaiPackageData : $homePackageData;
            $selectedPackage = $selectedPackageData;
            $selectedComponents = $selectedPackageData->salaryComponents;

            $componentsData = [];
            $subtotal = 0;

            foreach ($selectedComponents as $component) {
                // Daily amount of this salary component
                $dailyAmount = $component->value / $workingDays;

                // Salary earned for this work location
                $amount = round($dailyAmount * $workedDays, 2);
                $componentsData[] = [
                    'id' => $component->id,
                    'name' => $component->component_name,
                    'amount' => $amount,
                ];
                $subtotal += $amount;
            }

            $packageDetails = $selectedPackage->toArray();
            unset($packageDetails['salary_components']);

            $locationBreakdown[] = [
                'location_name' => $locationName,
                'package' => $packageDetails,
                'worked_days' => $workedDays,
                'currency' => [
                    'code' => $selectedPackage->currency,
                    'symbol' => $selectedPackage->currency,
                ],
                'salary_components' => $componentsData,
                'subtotal' => round($subtotal, 2),
            ];

            $totalEarnings += $subtotal;
        }

        // Note: Deductions are handled manually per user request, so total_deductions = 0
        $totalEarnings = round($totalEarnings, 2);
        $totalDeductions = 0;
        $grossSalary = $totalEarnings;
        $netSalary = $totalEarnings - $totalDeductions;

        $employeeName = trim($employee->first_name . ' ' . $employee->last_name);

        return response()->json([
            'success' => true,
            'data' => [
                'employee_id' => $employee->id,
                'employee_name' => $employeeName ?: null,
                'month' => $month,
                'total_worked_days' => $totalWorkedDays,
                'location_breakdown' => $locationBreakdown,
                'total_earnings' => $totalEarnings,
                'total_deductions' => $totalDeductions,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
            ]
        ]);
    }

    /**
     * Calculate overtime for an employee for a specific month based on ProjectTimeLog and WorkingHour.
     */
    public function calculateOvertime(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $employeeId = $request->employee_id;
        $month = $request->month;

        $employee = Employee::where('user_id', $employeeId)->first();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        // Fetch Attendance Logs for the month to get the working dates
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $attendanceLogs = AttendanceLog::where('userid', $employee->user_id)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('log_status', 'out')
            ->get();

        $workingHoursList = WorkingHour::all();
        $uniqueDates = $attendanceLogs->pluck('log_date')->unique()->toArray();
        $projectLogsQuery = ProjectTimeLog::with('project')
            ->where('user_id', $employee->user_id)
            ->whereIn('date', $uniqueDates)
            ->get();

        $overtimeDetails = [];
        foreach ($uniqueDates as $date) {
            $dayOfWeek = Carbon::parse($date)->format('l');
            $workingHour = $workingHoursList->first(function ($wh) use ($dayOfWeek) {
                return strtolower($wh->day) === strtolower($dayOfWeek);
            });

            $requiredMinutes = 0;
            if ($workingHour && $workingHour->is_enabled) {
                $start = Carbon::createFromTimeString($workingHour->start_time);
                $end = Carbon::createFromTimeString($workingHour->end_time);
                $requiredMinutes = $start->diffInMinutes($end);
            }

            $logsForDate = $projectLogsQuery->where('date', $date);

            $totalProjectMinutes = $logsForDate->sum('time_taken_minutes');
            $overtimeMinutes = max(0, $totalProjectMinutes - $requiredMinutes);

            $projects = $logsForDate->map(function ($log) {
                return [
                    'project_name' => $log->project ? $log->project->name : 'Unknown',
                    'time_taken_hours' => round($log->time_taken_minutes / 60, 2),
                    'time_taken_minutes' => $log->time_taken_minutes,
                ];
            })->values()->toArray();

            $overtimeDetails[] = [
                'date' => $date,
                'day' => $dayOfWeek,
                'required_working_hours' => round($requiredMinutes / 60, 2),
                'total_logged_hours' => round($totalProjectMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'projects' => $projects,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $overtimeDetails,
        ]);
    }

    /**
     * Calculate total pay, overtime amount, and deductions based on saved step data.
     */
    public function calculateTotals(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'pay_period_month' => 'required|integer|min:1|max:12',
            'pay_period_year' => 'required|integer|min:2000|max:2100',
        ]);

        $employee = Employee::where('user_id', $request->user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $payroll = Payroll::where('user_id', $employee->user_id)
            ->where('pay_period_month', $request->pay_period_month)
            ->where('pay_period_year', $request->pay_period_year)
            ->first();

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found for this period'], 404);
        }

        $data = $payroll->data ?? [];

        // 1. Gross Salary (typically from step_2 or step_1)
        $grossSalary = 0;
        if (isset($data['step_2']['gross_salary'])) {
            $grossSalary = (float) $data['step_2']['gross_salary'];
        } elseif (isset($data['step_2']['total_earnings'])) {
            $grossSalary = (float) $data['step_2']['total_earnings'];
        } elseif (isset($data['step_1']['gross_salary'])) {
            $grossSalary = (float) $data['step_1']['gross_salary'];
        }

        // 2. Overtime Amount (from step_3, checking for approved status if it's an array)
        $overtimeAmount = 0;
        if (isset($data['step_3']['overtime_details']) && is_array($data['step_3']['overtime_details'])) {
            foreach ($data['step_3']['overtime_details'] as $ot) {
                if (isset($ot['status']) && strtolower($ot['status']) === 'approved' && isset($ot['amount'])) {
                    $overtimeAmount += (float) $ot['amount'];
                }
            }
        } elseif (isset($data['step_3']['days']) && is_array($data['step_3']['days'])) {
            foreach ($data['step_3']['days'] as $day) {
                if (isset($day['status']) && strtolower($day['status']) === 'approved' && isset($day['amount'])) {
                    $overtimeAmount += (float) $day['amount'];
                }
            }
        } else {
            // Fallback if manually calculated and just stored as 'overtime_amount'
            if (isset($data['step_3']['overtime_amount'])) {
                $overtimeAmount = (float) $data['step_3']['overtime_amount'];
            }
        }

        // 3. Deductions (from step_4)
        $totalDeductions = 0;
        if (isset($data['step_4']['deductions']) && is_array($data['step_4']['deductions'])) {
            foreach ($data['step_4']['deductions'] as $deduction) {
                if (isset($deduction['amount'])) {
                    $totalDeductions += (float) $deduction['amount'];
                }
            }
        } elseif (isset($data['step_4']['total_deductions'])) {
            $totalDeductions = (float) $data['step_4']['total_deductions'];
        } else {
            // Fallback: sum all array items that have an 'amount' in step_4
            if (isset($data['step_4']) && is_array($data['step_4'])) {
                foreach ($data['step_4'] as $key => $value) {
                    if (is_array($value) && isset($value['amount'])) {
                        $totalDeductions += (float) $value['amount'];
                    }
                }
            }
        }

        // 4. Net Pay Calculation
        $netPay = $grossSalary + $overtimeAmount - $totalDeductions;

        return response()->json([
            'success' => true,
            'data' => [
                'gross_salary' => round($grossSalary, 2),
                'overtime_amount' => round($overtimeAmount, 2),
                'deductions' => round($totalDeductions, 2),
                'net_pay' => round($netPay, 2),
            ]
        ]);
    }

    /**
     * Get the payroll draft for an employee, optionally filtered by month/year.
     */
    public function getDraft($user_id): JsonResponse
    {
        $employee = Employee::where('user_id', $user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found for this user'], 404);
        }

        $query = Payroll::where('user_id', $employee->user_id)
            ->where('status', 'draft');

        // Optionally narrow to a specific month/year if provided
        if (request()->has('pay_period_month')) {
            $query->where('pay_period_month', (int) request()->query('pay_period_month'));
        }
        if (request()->has('pay_period_year')) {
            $query->where('pay_period_year', (int) request()->query('pay_period_year'));
        }

        $draft = $query->latest()->first();

        if (!$draft) {
            return response()->json([
                'success' => true,
                'data' => [
                    'employee_id' => $employee->id,
                    'current_step' => 1,
                    'data' => []
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $draft
        ]);
    }

    /**
     * Save step data.
     * Looks up the payroll row by employee + pay_period_month + pay_period_year.
     * Creates the row if it does not exist; updates it if it does.
     */
    public function saveStep(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'step' => 'required|integer|min:1|max:6',
            'step_data' => 'required|array',
        ]);

        $user_id = $request->user_id;
        $step = (int) $request->step;
        $step_data = $request->step_data;

        // Attempt to get pay period month/year from step_data or request
        $pay_period_month = (int) ($request->step_data['pay_period_month'] ?? $request->pay_period_month ?? 0);
        $pay_period_year = (int) ($request->step_data['pay_period_year'] ?? $request->pay_period_year ?? 0);

        if (!$pay_period_month || !$pay_period_year) {
            return response()->json(['success' => false, 'message' => 'pay_period_month and pay_period_year are required in step_data or request'], 422);
        }

        $employee = Employee::where('user_id', $user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found for this user'], 404);
        }

        // Look up by employee + month + year (regardless of status — allows re-editing)
        $payroll = Payroll::where('user_id', $employee->user_id)
            ->where('pay_period_month', $pay_period_month)
            ->where('pay_period_year', $pay_period_year)
            ->first();

        if (!$payroll) {
            // No record yet for this employee/month/year — create a fresh one
            $payroll = new Payroll();
            $payroll->user_id = $employee->user_id;
            $payroll->pay_period_month = $pay_period_month;
            $payroll->pay_period_year = $pay_period_year;
            $payroll->status = 'draft';
            $payroll->current_step = 1;
            $payroll->data = [];
        }

        // Merge the incoming step data into the existing JSON blob
        $currentData = $payroll->data ?? [];
        $currentData["step_{$step}"] = $step_data;
        $payroll->data = $currentData;

        // Advance current_step pointer if moving forward
        if ($step >= ($payroll->current_step ?? 1)) {
            $payroll->current_step = min(6, $step + 1);
        }

        // Keep as draft while wizard is in progress (don't override a completed status accidentally)
        if ($payroll->status !== 'completed') {
            $payroll->status = 'draft';
        }

        $payroll->save();

        return response()->json([
            'success' => true,
            'message' => "Step {$step} saved successfully",
            'data' => $payroll,
        ]);
    }

    /**
     * Submit and finalize the payroll
     */
    public function submitPayroll(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'pay_period_month' => 'required|integer|min:1|max:12',
            'pay_period_year' => 'required|integer|min:2000|max:2100',
            'gross_salary' => 'required|numeric',
            'overtime' => 'required|numeric',
            'deductions' => 'required|numeric',
            'net_pay' => 'required|numeric',
            'currency' => 'required|string',
        ]);

        $user_id = $request->user_id;

        $employee = Employee::where('user_id', $user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found for this user'], 404);
        }

        $payroll = Payroll::where('user_id', $employee->user_id)
            ->where('pay_period_month', (int) $request->pay_period_month)
            ->where('pay_period_year', (int) $request->pay_period_year)
            ->first();

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'No payroll found for this employee and period'], 404);
        }


        if ($payroll->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Payroll already submitted for this employee and period'], 400);
        }

        // Mark as completed
        $payroll->gross_salary = $request->gross_salary ?? null;
        $payroll->net_pay = $request->net_pay ?? null;
        $payroll->deductions = $request->deductions ?? null;
        $payroll->overtime = $request->overtime ?? null;
        $payroll->currency = $request->currency ?? null;
        $payroll->status = 'completed';
        $payroll->save();

        // Generate PDF
        try {
            $payroll->load('employee');

            if (view()->exists('pdf.payslip')) {
                $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $payroll]);

                $path = storage_path('app/public/payslips');
                if (!file_exists($path)) {
                    mkdir($path, 0775, true);
                }

                $pdf->save($path . '/' . $payroll->id . '.pdf');
            }
        } catch (\Exception $e) {
            \Log::error("Failed to generate PDF for payroll {$payroll->id}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Payroll generated and submitted successfully',
            'data' => $payroll,
        ]);
    }

    /**
     * Calculate converted salary totals based on multiple currencies from different steps.
     */
    public function convertSalary(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'pay_period_month' => 'required|integer|min:1|max:12',
            'pay_period_year' => 'required|integer|min:2000|max:2100',
            'target_currency' => 'required|string',
            'conversion_rates' => 'sometimes|array',
        ]);

        $employee = Employee::where('user_id', $request->user_id)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $payroll = Payroll::where('user_id', $employee->user_id)
            ->where('pay_period_month', $request->pay_period_month)
            ->where('pay_period_year', $request->pay_period_year)
            ->first();

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        $data = $payroll->data ?? [];
        $targetCurrency = $request->target_currency;
        $customRates = $request->input('conversion_rates', []);

        $ratesMap = [];
        foreach ($customRates as $rate) {
            if (isset($rate['from_currency']) && isset($rate['rate'])) {
                $ratesMap[$rate['from_currency']] = (float) $rate['rate'];
            }
        }

        $getRate = function ($fromCurrency) use ($targetCurrency, $ratesMap) {
            if ($fromCurrency === $targetCurrency)
                return 1.0;
            if (isset($ratesMap[$fromCurrency])) {
                return $ratesMap[$fromCurrency];
            }

            // Fallback default rates (assuming AED base to other currencies if fromCurrency is AED)
            // If fromCurrency is AED, we use the default rate to targetCurrency
            $aedToX = [
                'AED' => 1.00,
                'INR' => 22.51,
                'USD' => 0.27,
                'EUR' => 0.25,
                'GBP' => 0.21,
                'PHP' => 15.74,
                'LKR' => 86.50,
            ];

            if ($fromCurrency === 'AED' && isset($aedToX[$targetCurrency])) {
                return $aedToX[$targetCurrency];
            }

            // If we're converting from e.g. INR to AED, and no rate provided, 
            // fallback could be inverse of AED->INR
            if ($targetCurrency === 'AED' && isset($aedToX[$fromCurrency])) {
                return 1.0 / $aedToX[$fromCurrency];
            }

            return 1.0;
        };

        $convertedGross = 0;
        // Check step_2 first, fallback to step_1
        $locationBreakdown = $data['step_2']['location_breakdown'] ?? $data['step_1']['location_breakdown'] ?? [];
        foreach ($locationBreakdown as $loc) {
            $subtotal = $loc['subtotal'] ?? 0;
            $currency = $loc['currency']['code'] ?? 'AED';
            $rate = $getRate($currency);
            $convertedGross += ($subtotal * $rate);
        }

        $convertedOvertime = 0;
        $overtimeDetails = $data['step_3']['overtime_details'] ?? $data['step_3']['days'] ?? [];
        foreach ($overtimeDetails as $ot) {
            $amount = $ot['amount'] ?? 0;
            $currency = $ot['currency'] ?? 'AED';
            $rate = $getRate($currency);
            $convertedOvertime += ($amount * $rate);
        }

        $convertedDeductions = 0;
        $deductionsDetails = $data['step_4']['deductions'] ?? [];
        foreach ($deductionsDetails as $deduction) {
            if (strtolower($deduction['is_statutory']) === 'yes') {
                $amount = $deduction['amount'] ?? 0;
                $currency = $deduction['currency'] ?? 'AED';
                $rate = $getRate($currency);
                $convertedDeductions += ($amount * $rate);
            }
        }

        $convertedNetPay = $convertedGross + $convertedOvertime - $convertedDeductions;

        return response()->json([
            'success' => true,
            'data' => [
                'target_currency' => $targetCurrency,
                'converted_gross_salary' => round($convertedGross, 2),
                'converted_overtime' => round($convertedOvertime, 2),
                'converted_deductions' => round($convertedDeductions, 2),
                'converted_net_pay' => round($convertedNetPay, 2)
            ]
        ]);
    }

    /**
     * Send payslip to employee via email
     */
    public function sendPayslip($id): JsonResponse
    {
        $payroll = Payroll::with('employee.user')->find($id);

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        if ($payroll->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Cannot send payslip for incomplete payroll'], 400);
        }

        $employee = $payroll->employee;
        $user = $employee->user;

        if (!$user || !$user->email) {
            return response()->json(['success' => false, 'message' => 'Employee email not found'], 404);
        }

        try {
            if (!view()->exists('pdf.payslip')) {
                return response()->json(['success' => false, 'message' => 'Payslip template not found'], 500);
            }

            $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $payroll]);
            $pdfContent = $pdf->output();

            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\PayslipMail($payroll, $pdfContent));

            return response()->json([
                'success' => true,
                'message' => 'Payslip sent successfully to ' . $user->email,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send payslip for payroll {$payroll->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download the payslip PDF on the fly
     */
    public function downloadPayslip($id)
    {
        $payroll = Payroll::with(['employee.user.designation', 'employee.bankDetails'])->find($id);

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        if ($payroll->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Cannot download payslip for incomplete payroll'], 400);
        }

        try {
            if (!view()->exists('pdf.payslip')) {
                return response()->json(['success' => false, 'message' => 'Payslip template not found'], 500);
            }

            $pdf = Pdf::loadView('pdf.payslip', ['payroll' => $payroll]);
            $monthName = Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F');
            $fileName = "Payslip_{$monthName}_{$payroll->pay_period_year}.pdf";

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error("Failed to generate PDF for payroll {$payroll->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get history of completed payrolls (with formatted fields).
     */
    public function history(Request $request): JsonResponse
    {
        $query = Payroll::with('employee')->where('status', 'completed');

        if ($request->has('employee_id')) {
            $empParam = $request->query('employee_id');
            // Try resolving Employee PK to user_id
            $emp = Employee::find($empParam);
            $userId = $emp ? $emp->user_id : $empParam;

            $query->where(function ($q) use ($userId, $empParam) {
                $q->where('user_id', $userId)
                    ->orWhere('employee_id', $empParam);
            });
        }

        $payrolls = $query->orderBy('pay_period_year', 'desc')
            ->orderBy('pay_period_month', 'desc')
            ->get()
            ->map(fn($payroll) => $this->formatPayroll($payroll));

        return response()->json([
            'success' => true,
            'data' => $payrolls,
        ]);
    }

    /**
     * Get salary summary & payment history for a specific employee.
     * Returns:
     * - total_earnings (sum of net_pay of completed payrolls)
     * - months_generated_count (number of completed payroll months)
     * - payment_history (list of completed payrolls formatted with Gross Pay, Deductions, Net Pay, Status, Payment Date, Month/Year)
     */
    public function employeeSalarySummary(Request $request, $employee_id): JsonResponse
    {
        // Resolve Employee record (by Employee primary key or user_id)
        $employee = Employee::find($employee_id);
        if (!$employee) {
            $employee = Employee::where('user_id', $employee_id)->first();
        }

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        // Fetch only completed payrolls for this employee
        $payrolls = Payroll::where(function ($q) use ($employee) {
            $q->where('user_id', $employee->user_id)
                ->orWhere('employee_id', $employee->id);
        })
            ->where('status', 'completed')
            ->orderBy('pay_period_year', 'desc')
            ->orderBy('pay_period_month', 'desc')
            ->get();

        $totalEarnings = 0;
        $monthsGeneratedCount = $payrolls->count();
        $paymentHistory = [];

        foreach ($payrolls as $payroll) {
            $formatted = $this->formatPayroll($payroll);
            $totalEarnings += (float) ($payroll->net_pay ?? 0);

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
                'payment_date' => $formatted['payment_date'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'employee_name' => trim($employee->first_name . ' ' . $employee->last_name),
                'total_earnings' => round($totalEarnings, 2),
                'months_generated_count' => $monthsGeneratedCount,
                'payment_history' => $paymentHistory,
            ],
        ]);
    }

    /**
     * Show a single payroll record by ID.
     */
    public function show($id): JsonResponse
    {
        $payroll = Payroll::with('employee')->find($id);

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatPayroll($payroll), ['step_data' => $payroll->data]),
        ]);
    }

    /**
     * Update a payroll record (step data, status, etc.) by ID.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:draft,completed',
            'step' => 'sometimes|integer|min:1|max:6',
            'step_data' => 'sometimes|array',
        ]);

        $payroll = Payroll::find($id);

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        // Update step data if provided
        if ($request->has('step') && $request->has('step_data')) {
            $step = (int) $request->step;
            $currentData = $payroll->data ?? [];
            $currentData["step_{$step}"] = $request->step_data;
            $payroll->data = $currentData;

            if ($step >= ($payroll->current_step ?? 1)) {
                $payroll->current_step = min(6, $step + 1);
            }
        }

        // Update status if provided (only allow rolling back to draft or completing)
        if ($request->has('status')) {
            $payroll->status = $request->status;
        }

        $payroll->save();

        return response()->json([
            'success' => true,
            'message' => 'Payroll updated successfully',
            'data' => array_merge($this->formatPayroll($payroll), ['step_data' => $payroll->data]),
        ]);
    }

    /**
     * Delete a payroll record by ID.
     */
    public function destroy($id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (!$payroll) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found'], 404);
        }

        $payroll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payroll deleted successfully',
        ]);
    }

    /**
     * Shared helper: resolve and format a single Payroll record.
     *
     * Also self-heals old records that were saved with employee_id = NULL
     * by reading the employee_id stored inside the JSON step data.
     */
    private function formatPayroll(Payroll $payroll): array
    {
        $data = $payroll->data ?? [];

        // ------------------------------------------------------------------
        // 1. Self-heal: if employee_id is null, try to recover it from the
        //    JSON data that the wizard stored (the frontend sends employee_id
        //    as part of step_1 or top-level step data).
        // ------------------------------------------------------------------
        if (is_null($payroll->employee_id)) {
            $recoveredId = null;

            // Check every step's data for an 'employee_id' key
            foreach ($data as $stepData) {
                if (is_array($stepData) && !empty($stepData['employee_id'])) {
                    $recoveredId = (int) $stepData['employee_id'];
                    break;
                }
            }

            // Also check a top-level 'employee_id' in the data blob
            if (!$recoveredId && !empty($data['employee_id'])) {
                $recoveredId = (int) $data['employee_id'];
            }

            if ($recoveredId) {
                $payroll->employee_id = $recoveredId;
                $payroll->saveQuietly();
                $payroll->load('employee');
            }
        }

        // ------------------------------------------------------------------
        // 2. Extract net pay — step_6 > final_net_pay is the authoritative
        //    key used by the payslip PDF template. Fall back to other common
        //    key names in case an older wizard used a different name.
        // ------------------------------------------------------------------
        $netPay = null;

        // Primary: step_6 > final_net_pay (matches payslip.blade.php)
        if (isset($data['step_6']['final_net_pay'])) {
            $netPay = $data['step_6']['final_net_pay'];
        }

        // Fallbacks: scan every step for alternative key names
        if (is_null($netPay)) {
            $fallbackKeys = ['net_pay', 'total_net_pay', 'net_salary', 'final_net_pay'];
            foreach ($data as $stepData) {
                if (!is_array($stepData)) {
                    continue;
                }
                foreach ($fallbackKeys as $key) {
                    if (isset($stepData[$key])) {
                        $netPay = $stepData[$key];
                        break 2;
                    }
                }
            }
        }

        // ------------------------------------------------------------------
        // 3. Resolve employee name
        // ------------------------------------------------------------------
        $employee = $payroll->employee;
        $employeeName = $employee
            ? trim($employee->first_name . ' ' . $employee->last_name)
            : null;

        $avatarUrl = $employee?->avatar_url;

        // ------------------------------------------------------------------
        // 4. Payment date = updated_at when status is completed
        // ------------------------------------------------------------------
        $paymentDate = ($payroll->status === 'completed' && $payroll->updated_at)
            ? $payroll->updated_at->toDateString()
            : null;

        return [
            'id' => $payroll->id,
            'employee_name' => $employeeName,
            'employee_id' => $payroll->user_id,
            'avatar' => $avatarUrl,
            'month' => $payroll->pay_period_month,
            'year' => $payroll->pay_period_year,
            'net_pay' => $payroll->net_pay,
            'gross_salary' => $payroll->gross_salary,
            'currency' => $payroll->currency,
            'overtime' => $payroll->overtime,
            'deductions' => $payroll->deductions,
            'status' => $payroll->status,
            'payment_date' => $paymentDate,
        ];
    }
}
