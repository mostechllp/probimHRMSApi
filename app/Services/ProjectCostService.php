<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProjectCostService
{
    /**
     * Approximate exchange rates to AED (1 Unit of Currency = X AED).
     */
    private const EXCHANGE_RATES_TO_AED = [
        'AED' => 1.0,
        'USD' => 3.6725,   // 1 USD = 3.6725 AED (fixed peg)
        'INR' => 0.038373, // 1 AED = 26.06 INR => 1 INR = 0.038373 AED
        'EUR' => 3.97,
        'GBP' => 4.63,
        'SAR' => 0.98,
        'QAR' => 1.01,
        'KWD' => 12.02,
    ];

    /**
     * Map attendance timezones to salary package currencies.
     */
    private const TIMEZONE_CURRENCY_MAP = [
        'asia/kolkata' => 'INR',
        'asia/calcutta' => 'INR',
        '+05:30' => 'INR',
        'utc+05:30' => 'INR',
        '+5:30' => 'INR',
        'ist' => 'INR',

        'asia/dubai' => 'AED',
        '+04:00' => 'AED',
        'utc+04:00' => 'AED',
        '+4:00' => 'AED',
        '+04' => 'AED',
        'gst' => 'AED',
    ];

    /**
     * UAE Location keywords to match against attendance work_location.
     */
    private const UAE_KEYWORDS = [
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
        'onsite',
    ];

    /**
     * Standard working hours per day.
     */
    private const DEFAULT_WORKING_HOURS_PER_DAY = 9;

    /**
     * Resolve date range and label from request parameters.
     *
     * Supported filters:
     * - this_month (default)
     * - last_month
     * - this_quarter
     * - project_to_date
     * - custom (requires start_date & end_date or from_date & to_date)
     * - month & year query params
     *
     * @param array $params
     * @return array [startDate, endDate, filterKey, filterLabel]
     */
    public function resolveDateRange(array $params): array
    {
        $now = Carbon::now();

        // 1. Direct month & year
        if (!empty($params['month']) && !empty($params['year'])) {
            $month = (int) $params['month'];
            $year = (int) $params['year'];
            $date = Carbon::createFromDate($year, $month, 1);
            return [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
                'month_year',
                $date->format('F Y'),
            ];
        }

        $filter = strtolower(trim($params['filter'] ?? $params['date_range'] ?? 'this_month'));
        $filter = str_replace(' ', '_', $filter);

        switch ($filter) {
            case 'last_month':
                $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $now->copy()->subMonthNoOverflow()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'last_month',
                    'Last Month (' . $start->format('F Y') . ')',
                ];

            case 'this_quarter':
                $start = $now->copy()->firstOfQuarter();
                $end = $now->copy()->lastOfQuarter();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_quarter',
                    'This Quarter (Q' . $now->quarter . ' ' . $now->year . ')',
                ];

            case 'project_to_date':
                // Search for the earliest time log date, or default to 5 years ago
                $earliestLog = ProjectTimeLog::min('date');
                $start = $earliestLog ? Carbon::parse($earliestLog) : $now->copy()->subYears(5)->startOfYear();
                $end = $now->copy()->endOfDay();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'project_to_date',
                    'Project to Date',
                ];

            case 'custom':
            case 'custom_range':
                $from = $params['start_date'] ?? $params['from_date'] ?? null;
                $to = $params['end_date'] ?? $params['to_date'] ?? null;

                if ($from && $to) {
                    $start = Carbon::parse($from);
                    $end = Carbon::parse($to);
                    return [
                        $start->toDateString(),
                        $end->toDateString(),
                        'custom',
                        $start->format('d M Y') . ' - ' . $end->format('d M Y'),
                    ];
                }

                // Fallback to this month if custom dates are missing
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_month',
                    'This Month (' . $now->format('F Y') . ')',
                ];

            case 'this_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                return [
                    $start->toDateString(),
                    $end->toDateString(),
                    'this_month',
                    'This Month (' . $now->format('F Y') . ')',
                ];
        }
    }

    /**
     * Convert an amount in any supported currency to AED.
     */
    public function convertToAed(float $amount, ?string $currency): float
    {
        $curr = strtoupper(trim($currency ?: 'AED'));

        if ($curr === 'AED') {
            return $amount;
        }

        $rate = self::EXCHANGE_RATES_TO_AED[$curr] ?? 1.0;
        return $amount * $rate;
    }

    /**
     * Format minutes into human-readable hours and minutes string (e.g., '1240h 30m', '160h').
     */
    public function formatMinutes(float|int $minutes): string
    {
        $minutes = (int) round($minutes);
        $hours = (int) floor($minutes / 60);
        $rem = $minutes % 60;

        if ($hours > 0 && $rem > 0) {
            return "{$hours}h {$rem}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } elseif ($rem > 0) {
            return "{$rem}m";
        }

        return "0h";
    }

    /**
     * Format decimal hours to formatted string (e.g. 1.5 -> '1h 30m').
     */
    public function formatHours(float $hours): string
    {
        return $this->formatMinutes($hours * 60);
    }

    /**
     * Calculate and return complete Project Cost report data.
     *
     * @param array $params Query parameters (filter, start_date, end_date, project_id, search, etc.)
     * @return array
     */
    public function getProjectCostReport(array $params = []): array
    {
        [$startDate, $endDate, $filterKey, $filterLabel] = $this->resolveDateRange($params);
        $projectId = $params['project_id'] ?? null;
        $search = $params['search'] ?? null;

        // 1. Fetch Projects:
        // If a specific project_id is requested, find that project.
        // If no project_id is requested, only fetch projects that actually have time logs in the date range.
        if ($projectId && $projectId !== 'all') {
            $targetProject = Project::find($projectId);
            if (!$targetProject) {
                return [
                    'period' => [
                        'filter' => $filterKey,
                        'label' => $filterLabel,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'summary' => [
                        'total_projects' => 0,
                        'total_employees' => 0,
                        'total_hours' => 0,
                        'total_hours_formatted' => '0h',
                        'total_cost' => 0,
                        'total_cost_formatted' => '0.00',
                        'total_budget' => null,
                        'total_budget_formatted' => 'No budget set',
                        'total_variance' => null,
                        'total_variance_formatted' => '-',
                        'is_over_budget' => false,
                    ],
                    'projects' => [],
                ];
            }
            $projects = collect([$targetProject]);
            $projectIds = [$targetProject->id];
        } else {
            // Find project IDs that have time logs within the date range.
            $activeProjectIds = ProjectTimeLog::whereBetween('date', [$startDate, $endDate])
                ->distinct()
                ->pluck('project_id')
                ->filter()
                ->toArray();

            if (empty($activeProjectIds)) {
                // No time logs at all in this period — return empty report
                return [
                    'period' => [
                        'filter' => $filterKey,
                        'label' => $filterLabel,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'summary' => [
                        'total_projects' => 0,
                        'total_employees' => 0,
                        'total_hours' => 0,
                        'total_hours_formatted' => '0h',
                        'total_cost' => 0,
                        'total_cost_formatted' => '0.00',
                        'total_budget' => null,
                        'total_budget_formatted' => 'No budget set',
                        'total_variance' => null,
                        'total_variance_formatted' => '-',
                        'is_over_budget' => false,
                    ],
                    'projects' => [],
                ];
            }

            $projectQuery = Project::whereIn('id', $activeProjectIds);

            if (!empty($search)) {
                $projectQuery->where('name', 'like', '%' . trim($search) . '%');
            }

            $projects = $projectQuery->orderBy('name')->get();
            $projectIds = $projects->pluck('id')->toArray();
        }

        // 2. Fetch all time logs for these projects in date range
        $timeLogs = ProjectTimeLog::whereIn('project_id', $projectIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Group time logs by project_id
        $timeLogsByProject = $timeLogs->groupBy('project_id');

        // Collect all distinct user_ids
        $allUserIds = $timeLogs->pluck('user_id')->unique()->filter()->values()->toArray();

        // 3. Pre-load Employees with designations and active Salary Packages + Components
        $employeesByUserId = Employee::withTrashed()
            ->whereIn('user_id', $allUserIds)
            ->with([
                'user.designation',
                'salaryPackages' => function ($q) {
                    $q->with('salaryComponents');
                },
            ])
            ->get()
            ->keyBy('user_id');

        // Also collect employee string codes (e.g. EMP001) for attendance lookup fallback
        $allEmployeeCodes = $employeesByUserId->pluck('employee_id')->filter()->unique()->toArray();
        $allLookupIds = array_values(array_unique(array_merge($allUserIds, $allEmployeeCodes)));

        // 4. Pre-load Attendance Logs for date range keyed by "userId|date"
        $attendanceLogs = AttendanceLog::whereIn('userid', $allLookupIds)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->get(['userid', 'log_date', 'timezone', 'work_location']);

        $attendanceByUserDate = [];
        foreach ($attendanceLogs as $att) {
            $dateKey = Carbon::parse($att->log_date)->toDateString();
            $attendanceByUserDate[$att->userid . '|' . $dateKey] = $att;
        }

        // Cache for employee package evaluation
        $employeePackageContext = [];

        // Prepare date sequence for daily timeline (if range is reasonable, e.g. <= 92 days)
        $dateList = [];
        $temp = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);
        $generateDailyTimeline = $temp->diffInDays($endCarbon) <= 93; // 1 quarter max for granular daily bins

        if ($generateDailyTimeline) {
            while ($temp->lte($endCarbon)) {
                $dateList[] = $temp->toDateString();
                $temp->addDay();
            }
        }

        $projectReports = [];
        $totalProjectsEmployees = [];
        $grandTotalMinutes = 0;
        $grandTotalCost = 0.0;
        $grandTotalBudget = 0.0;
        $grandBudgetedProjectCost = 0.0;

        foreach ($projects as $project) {
            $projectLogs = $timeLogsByProject->get($project->id, collect());

            // Skip projects with no time logs in this period when viewing all projects.
            // (When viewing a specific project, we keep it so its budget/variance still render).
            if ($projectLogs->isEmpty() && (!$projectId || $projectId === 'all')) {
                continue;
            }

            $logsByUser = $projectLogs->groupBy('user_id');

            $projectTotalMinutes = 0;
            $projectTotalCost = 0.0;
            $employeeBreakdown = [];
            $projectDailyMinutes = array_fill_keys($dateList, 0);
            $projectDailyCost = array_fill_keys($dateList, 0.0);

            foreach ($logsByUser as $userId => $userLogs) {
                $employee = $employeesByUserId->get($userId);
                $totalProjectsEmployees[$userId] = true;

                // Build / fetch employee salary context
                if (!isset($employeePackageContext[$userId])) {
                    $employeePackageContext[$userId] = $this->buildEmployeePackageContext($employee);
                }
                $ctx = $employeePackageContext[$userId];

                $userMinutes = 0;
                $userCost = 0.0;
                $userDailyHours = [];

                foreach ($userLogs as $log) {
                    $minutes = (float) $log->time_taken_minutes;
                    $dateStr = Carbon::parse($log->date)->toDateString();

                    // Find attendance record for timezone / location
                    $attLog = $attendanceByUserDate[$userId . '|' . $dateStr]
                        ?? ($employee ? ($attendanceByUserDate[$employee->employee_id . '|' . $dateStr] ?? null) : null);

                    $hourlyRateAed = $this->calculateHourlyRateAedForDate($ctx, $dateStr, $attLog);

                    $logCost = ($minutes / 60) * $hourlyRateAed;

                    $userMinutes += $minutes;
                    $userCost += $logCost;

                    if ($generateDailyTimeline && isset($projectDailyMinutes[$dateStr])) {
                        $projectDailyMinutes[$dateStr] += $minutes;
                        $projectDailyCost[$dateStr] += $logCost;
                    }

                    if (!isset($userDailyHours[$dateStr])) {
                        $userDailyHours[$dateStr] = [
                            'date' => $dateStr,
                            'minutes' => 0,
                            'hours' => 0.0,
                            'formatted_hours' => '0h',
                            'cost' => 0.0,
                            'timezone' => $attLog?->timezone,
                            'work_location' => $attLog?->work_location,
                        ];
                    }

                    $userDailyHours[$dateStr]['minutes'] += $minutes;
                    $userDailyHours[$dateStr]['cost'] += round($logCost, 2);
                    $userDailyHours[$dateStr]['hours'] = round($userDailyHours[$dateStr]['minutes'] / 60, 2);
                    $userDailyHours[$dateStr]['formatted_hours'] = $this->formatMinutes($userDailyHours[$dateStr]['minutes']);
                }

                ksort($userDailyHours);

                $empName = $employee
                    ? trim($employee->first_name . ' ' . $employee->last_name)
                    : ($userLogs->first()?->user ? trim($userLogs->first()->user->first_name . ' ' . $userLogs->first()->user->last_name) : 'Unknown User');

                $designation = $employee?->user?->designation?->name ?? 'N/A';
                $avatar = $employee?->avatar_url ?? null;

                $userHours = round($userMinutes / 60, 2);
                $userCostRounded = round($userCost, 2);

                $projectTotalMinutes += $userMinutes;
                $projectTotalCost += $userCost;

                $employeeBreakdown[] = [
                    'employee_id' => $employee?->id,
                    'user_id' => $userId,
                    'code' => $employee?->employee_id ?? 'N/A',
                    'name' => $empName,
                    'designation' => $designation,
                    'avatar' => $avatar,
                    'total_hours' => $userHours,
                    'total_hours_formatted' => $this->formatMinutes($userMinutes),
                    'total_cost' => $userCostRounded,
                    'total_cost_formatted' => 'AED ' . number_format($userCostRounded, 2),
                    'daily_hours' => array_values($userDailyHours),
                ];
            }

            // Project Daily Hours array for chart
            $dailyTimeline = [];
            if ($generateDailyTimeline) {
                foreach ($dateList as $d) {
                    $dCarbon = Carbon::parse($d);
                    $mins = $projectDailyMinutes[$d] ?? 0;
                    $dailyTimeline[] = [
                        'date' => $d,
                        'day' => $dCarbon->format('d'),
                        'label' => $dCarbon->format('d M'),
                        'hours' => round($mins / 60, 2),
                        'formatted_hours' => $this->formatMinutes($mins),
                        'cost' => round($projectDailyCost[$d] ?? 0, 2),
                    ];
                }
            }

            // Project Budget in AED
            $budgetAed = null;
            if ($project->total_cost !== null && is_numeric($project->total_cost)) {
                $budgetAed = round($this->convertToAed((float) $project->total_cost, $project->currency), 2);
                $grandTotalBudget += $budgetAed;
                $grandBudgetedProjectCost += $projectTotalCost;
            }

            $projectTotalCostRounded = round($projectTotalCost, 2);
            $varianceAed = $budgetAed !== null ? round($budgetAed - $projectTotalCostRounded, 2) : null;
            $isOverBudget = $varianceAed !== null ? ($varianceAed < 0) : false;

            $grandTotalMinutes += $projectTotalMinutes;
            $grandTotalCost += $projectTotalCost;

            $projectReports[] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'currency' => 'AED',
                'original_currency' => $project->currency ?: 'AED',
                'status' => $project->status ?? 'active',
                'employees_count' => count($employeeBreakdown),
                'total_hours' => round($projectTotalMinutes / 60, 2),
                'total_hours_formatted' => $this->formatMinutes($projectTotalMinutes),
                'total_cost' => $projectTotalCostRounded,
                'total_cost_formatted' => number_format($projectTotalCostRounded, 2),
                'budget' => $budgetAed,
                'budget_formatted' => $budgetAed !== null ? number_format($budgetAed, 2) : 'No budget set',
                'variance' => $varianceAed,
                'variance_formatted' => $varianceAed !== null ? number_format($varianceAed, 2) : '-',
                'is_over_budget' => $isOverBudget,
                'employees' => $employeeBreakdown,
                'daily_hours' => $dailyTimeline,
            ];
        }

        $grandTotalCostRounded = round($grandTotalCost, 2);
        $grandTotalBudgetRounded = round($grandTotalBudget, 2);
        $grandTotalVariance = $grandTotalBudgetRounded > 0
            ? round($grandTotalBudgetRounded - $grandBudgetedProjectCost, 2)
            : null;

        $totalSummary = [
            'total_projects' => count($projects),
            'total_employees' => count($totalProjectsEmployees),
            'total_hours' => round($grandTotalMinutes / 60, 2),
            'total_hours_formatted' => $this->formatMinutes($grandTotalMinutes),
            'total_cost' => $grandTotalCostRounded,
            'total_cost_formatted' => number_format($grandTotalCostRounded, 2),
            'total_budget' => $grandTotalBudgetRounded > 0 ? $grandTotalBudgetRounded : null,
            'total_budget_formatted' => $grandTotalBudgetRounded > 0 ? number_format($grandTotalBudgetRounded, 2) : 'No budget set',
            'total_variance' => $grandTotalVariance,
            'total_variance_formatted' => $grandTotalVariance !== null ? number_format($grandTotalVariance, 2) : '-',
            'is_over_budget' => $grandTotalVariance !== null ? ($grandTotalVariance < 0) : false,
        ];

        return [
            'period' => [
                'filter' => $filterKey,
                'label' => $filterLabel,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $totalSummary,
            'projects' => $projectReports,
        ];
    }

    /**
     * Get details for a single project including employee breakdown and daily hours.
     */
    public function getProjectCostDetails(int $projectId, array $params = []): ?array
    {
        $params['project_id'] = $projectId;
        $report = $this->getProjectCostReport($params);

        $project = collect($report['projects'])->firstWhere('id', $projectId);
        if (!$project) {
            return null;
        }

        return [
            'period' => $report['period'],
            'project' => $project,
        ];
    }

    /**
     * Build an employee's salary package context.
     * Categorizes Dubai/AED packages and Home/INR packages.
     */
    private function buildEmployeePackageContext(?Employee $employee): array
    {
        if (!$employee || !$employee->salaryPackages) {
            return [
                'byCurrency' => collect(),
                'dubaiPackage' => null,
                'homePackage' => null,
                'fallback' => null,
                'packageGrossAed' => [],
            ];
        }

        $packages = $employee->salaryPackages;
        $activePackages = $packages->where('is_active', true)->values();
        if ($activePackages->isEmpty()) {
            $activePackages = $packages;
        }

        $dubaiPackage = null;
        $homePackage = null;
        $byCurrency = [];
        $packageGrossAed = [];

        foreach ($activePackages as $pkg) {
            $curr = strtoupper(trim($pkg->currency ?: 'AED'));
            $nameLower = strtolower($pkg->name ?: '');
            $gross = (float) $pkg->salaryComponents->sum('value');
            $grossAed = $this->convertToAed($gross, $curr);

            $packageGrossAed[$pkg->id] = $grossAed;
            $byCurrency[$curr] = $pkg;

            if ($curr === 'AED' || str_contains($nameLower, 'dubai') || str_contains($nameLower, 'onsite')) {
                if (!$dubaiPackage) {
                    $dubaiPackage = $pkg;
                }
            } else {
                if (!$homePackage) {
                    $homePackage = $pkg;
                }
            }
        }

        $fallback = $dubaiPackage ?? $homePackage ?? $activePackages->first();

        return [
            'byCurrency' => collect($byCurrency),
            'dubaiPackage' => $dubaiPackage,
            'homePackage' => $homePackage,
            'fallback' => $fallback,
            'packageGrossAed' => $packageGrossAed,
        ];
    }

    /**
     * Calculate hourly rate in AED for a specific date, taking into account
     * the attendance timezone / location of that day.
     */
    private function calculateHourlyRateAedForDate(array $ctx, string $dateStr, ?AttendanceLog $attLog): float
    {
        $package = null;

        if ($attLog) {
            $tz = strtolower(trim($attLog->timezone ?? ''));
            $loc = strtolower(trim($attLog->work_location ?? ''));

            // 1. Check if location or timezone indicates UAE / Dubai
            $isUae = false;
            if ($tz === 'asia/dubai' || $tz === 'gst' || str_contains($tz, '+04') || str_contains($tz, 'utc+4')) {
                $isUae = true;
            } else {
                foreach (self::UAE_KEYWORDS as $kw) {
                    if (str_contains($loc, $kw)) {
                        $isUae = true;
                        break;
                    }
                }
            }

            if ($isUae && $ctx['dubaiPackage']) {
                $package = $ctx['dubaiPackage'];
            }

            // 2. Check if timezone or location indicates Home / India
            if (!$package) {
                $isIndia = false;
                if ($tz === 'asia/kolkata' || $tz === 'asia/calcutta' || $tz === 'ist' || str_contains($tz, '+05:30')) {
                    $isIndia = true;
                } elseif (str_contains($loc, 'india') || str_contains($loc, 'calicut') || str_contains($loc, 'kerala') || str_contains($loc, 'wfh')) {
                    $isIndia = true;
                }

                if ($isIndia && $ctx['homePackage']) {
                    $package = $ctx['homePackage'];
                }
            }

            // 3. Check direct currency map from timezone
            if (!$package && $tz) {
                $mappedCurrency = self::TIMEZONE_CURRENCY_MAP[$tz] ?? null;
                if ($mappedCurrency && $ctx['byCurrency']->has($mappedCurrency)) {
                    $package = $ctx['byCurrency']->get($mappedCurrency);
                }
            }
        }

        // 4. Fallback to employee's default package
        $package = $package ?? $ctx['fallback'];

        if (!$package) {
            return 0.0;
        }

        $grossAed = $ctx['packageGrossAed'][$package->id] ?? 0.0;
        if ($grossAed <= 0) {
            return 0.0;
        }

        // Monthly hours for the specific month of the log date
        $dateCarbon = Carbon::parse($dateStr);
        $daysInMonth = $dateCarbon->daysInMonth;
        $monthlyHours = $daysInMonth * self::DEFAULT_WORKING_HOURS_PER_DAY;

        return $monthlyHours > 0 ? ($grossAed / $monthlyHours) : 0.0;
    }
}

