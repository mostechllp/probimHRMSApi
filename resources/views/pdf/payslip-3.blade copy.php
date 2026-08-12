<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 0; }
        
        table { page-break-inside: auto; }
        tr    { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }

        .brand-bar { width: 100%; background-color: #1e293b; padding: 18px 24px; margin-bottom: 18px; }
        .brand-bar td { color: #ffffff; vertical-align: middle; }
        .brand-bar .company-name { font-size: 20px; font-weight: bold; }
        .brand-bar .company-sub { font-size: 10px; color: #cbd5e1; margin-top: 2px; }
        .brand-bar .payslip-tag { font-size: 16px; font-weight: bold; text-align: right; letter-spacing: 1px; }
        .brand-bar .payslip-ref { font-size: 10px; color: #cbd5e1; text-align: right; margin-top: 2px; }

        .stats-strip { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .stats-strip td { width: 25%; background-color: #f1f5f9; border: 1px solid #e2e8f0; padding: 12px 14px; text-align: center; }
        .stats-strip .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stats-strip .stat-value { font-size: 14px; font-weight: bold; color: #1e293b; margin-top: 4px; }
        .stats-strip .stat-value.highlight { color: #15803d; }

        .details-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .details-grid td { width: 50%; vertical-align: top; padding: 0 6px; }
        .details-grid td:first-child { padding-left: 0; }
        .details-grid td:last-child { padding-right: 0; }

        .detail-card { border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 14px; }
        .detail-card h4 { margin: 0 0 8px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #1e293b; border-bottom: 2px solid #1e293b; padding-bottom: 5px; }
        .detail-row { padding: 3px 0; }
        .detail-row .label { color: #64748b; display: inline-block; width: 100px; }
        .detail-row .value { font-weight: bold; color: #1f2937; }

        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: bold; padding: 6px 0; margin-bottom: 4px; border-bottom: 2px solid; }
        .section-title.earnings { color: #2e573e; border-color: #7f9487; }
        .section-title.deductions { color: #505c66; border-color: #888591; }

        .line-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .line-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .line-table td.amt { text-align: right; }
        .line-table tr.subtotal td { border-top: 2px solid #1e293b; border-bottom: none; font-weight: bold; padding-top: 8px; }

        .net-bar { width: 100%; background-color: #5f757c; padding: 14px 24px; margin-top: 6px; }
        .net-bar td { color: #ffffff; }
        .net-bar .net-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .net-bar .net-value { font-size: 20px; font-weight: bold; text-align: right; }

        .footer-note { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 20px; }
    </style>
</head>
<body>

    @php
        $data      = $payroll->data ?? [];
        $employee  = $payroll->employee;
        $currency  = $payroll->currency ?? 'AED';

        $monthName = \Carbon\Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F');
        $yearFull  = $payroll->pay_period_year;
        
        $paymentDate = $payroll->updated_at ? $payroll->updated_at->format('Y-m-d') : date('Y-m-d');
        $paymentId   = 'PS' . $payroll->pay_period_year . sprintf('%02d', $payroll->pay_period_month) . $payroll->id;

        $empName     = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        $eid         = $employee->employee_id ?? $employee->user_id ?? 'N/A';
        $designation = $employee->user->designation->name ?? 'N/A';
        $doj         = $employee->joining_date ?? 'N/A';

        $bankDetails = $employee->bankDetails->first() ?? null;
        $accountNo   = $bankDetails->account_number ?? 'N/A';
        $bankName    = $bankDetails->bank_name ?? 'N/A';
        $ifsc        = $bankDetails->ifsc_code ?? $bankDetails->swift_code ?? 'N/A';
        $branch      = $bankDetails->branch_name ?? 'N/A';
        $upiId       = $bankDetails->upi_id ?? 'N/A';
        $upiNo       = $bankDetails->upi_number ?? 'N/A';

        $workedDays  = $data['step_2']['total_worked_days'] ?? $data['step_1']['total_worked_days'] ?? $data['step_5']['total_worked_days'] ?? 0;
        $daysInMonth = 30;

        // --- Earnings (Table wise per package) ---
        $packagesEarnings = [];
        $locationBreakdown = $data['step_2']['location_breakdown'] ?? $data['step_1']['location_breakdown'] ?? [];
        
        foreach ($locationBreakdown as $loc) {
            $pkgName = $loc['package']['name'] ?? $loc['location_name'] ?? 'Unknown';
            $pkgCurrency = $loc['currency']['code'] ?? 'AED';
            
            $pkgComponents = [];
            foreach ($loc['salary_components'] ?? [] as $comp) {
                $pkgComponents[$comp['name'] ?? 'Unknown'] = $comp['amount'] ?? 0;
            }
            
            $packagesEarnings[] = [
                'name' => $pkgName,
                'currency' => $pkgCurrency,
                'components' => $pkgComponents,
                'worked_days' => $loc['worked_days'] ?? 0,
                'subtotal' => $loc['subtotal'] ?? array_sum($pkgComponents)
            ];
        }

        $totalWorkedDays = array_sum(array_column($locationBreakdown, 'worked_days'));

        // --- Deductions ---
        $deductionsList    = [];
        $deductionsDetails = $data['step_4']['deductions'] ?? [];
        foreach ($deductionsDetails as $ded) {
            $name = $ded['type'] ?? $ded['name'] ?? $ded['component_name'] ?? 'Unknown';
            $deductionsList[$name] = ($deductionsList[$name] ?? 0) + ($ded['amount'] ?? 0);
        }

        $totalDeductions = $data['step_6']['total_deductions'] ?? array_sum($deductionsList);
    @endphp
    @php
        $maskedAccount = !empty($accountNo)
            ? substr($accountNo, 0, 4) .
            str_repeat('X', max(0, strlen($accountNo) - 6)) .
            substr($accountNo, -2)
            : '-';

        $maskedIfsc = !empty($ifsc)
            ? substr($ifsc, 0, 4) .
            str_repeat('X', max(0, strlen($ifsc) - 4))
            : '-';
    @endphp

    <!-- Header -->
    <table class="brand-bar">
        <tr>
            <td>
                <div class="company-name">Probim LLC</div>
                <div class="company-sub">Payroll &amp; HR Services</div>
            </td>
            <td>
                <div class="payslip-tag">PAYSLIP</div>
                <div class="payslip-ref">Payment ID: #{{ $paymentId }}</div>
            </td>
        </tr>
    </table>

    <!-- Stats strip -->
    <table class="stats-strip">
        <tr>
            <td>
                <div class="stat-label">Pay Period</div>
                <div class="stat-value">{{ $monthName }} {{ $yearFull }}</div>
            </td>
            <td>
                <div class="stat-label">Payment Date</div>
                <div class="stat-value">{{ $paymentDate }}</div>
            </td>
            <td>
                <div class="stat-label">Worked Days</div>
                <div class="stat-value">{{ $totalWorkedDays }} / {{ $daysInMonth }}</div>
            </td>
            <td>
                <div class="stat-label">Net Pay</div>
                <div class="stat-value highlight">{{ $currency }} {{ number_format($payroll->net_pay, 2) }}</div>
            </td>
        </tr>
    </table>

    <!-- Employee + Bank details -->
    <table class="details-grid">
        <tr>
            <td>
                <div class="detail-card">
                    <h4>Employee Details</h4>
                    <div class="detail-row"><span class="label">Name:</span> <span class="value">{{ $empName }}</span></div>
                    <div class="detail-row"><span class="label">EID:</span> <span class="value">{{ $eid }}</span></div>
                    <div class="detail-row"><span class="label">Designation:</span> <span class="value">{{ $designation }}</span></div>
                    <div class="detail-row"><span class="label">Date of Joining:</span> <span class="value">{{ $doj }}</span></div>
                </div>
            </td>
            <td>
                <div class="detail-card">
                    <h4>Bank Details</h4>
                    <div class="detail-row"><span class="label">Account #:</span> <span class="value">{{ $maskedAccount }}</span></div>
                    <div class="detail-row"><span class="label">Bank:</span> <span class="value">{{ $bankName }}</span></div>
                    <div class="detail-row"><span class="label">IFSC / SWIFT:</span> <span class="value">{{ $maskedIfsc }}</span></div>
                    <div class="detail-row"><span class="label">Branch:</span> <span class="value">{{ $branch }}</span></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Earnings Packages -->
    @foreach($packagesEarnings as $pkg)
        <div class="section-title earnings">Earnings: {{ $pkg['name'] }} Package ({{ $pkg['worked_days'] }} Days)</div>
        <table class="line-table">
            @foreach($pkg['components'] as $name => $amount)
                <tr>
                    <td>{{ $name }}</td>
                    <td class="amt">{{ $pkg['currency'] }} {{ number_format($amount, 2) }}</td>
                </tr>
            @endforeach
            @if(count($pkg['components']) === 0)
                <tr><td colspan="2" style="color:#94a3b8;">No earnings recorded in this package.</td></tr>
            @endif
            <tr class="subtotal">
                <td>Subtotal for {{ $pkg['name'] }} Package</td>
                <td class="amt">{{ $pkg['currency'] }} {{ number_format($pkg['subtotal'], 2) }}</td>
            </tr>
        </table>
    @endforeach

    @if(count($packagesEarnings) === 0)
        <div class="section-title earnings">Earnings</div>
        <table class="line-table">
            <tr><td colspan="2" style="color:#94a3b8;">No earnings packages recorded.</td></tr>
        </table>
    @endif

    @if($deductionsList)
    <!-- Deductions -->
    @if(count($deductionsList) > 0 || $totalDeductions > 0)
    <div class="section-title deductions">Global Deductions</div>
    <table class="line-table">
        @foreach($deductionsList as $name => $amount)
            <tr>
                <td>{{ $name }}</td>
                <td class="amt">{{ $currency }} {{ number_format($amount, 2) }}</td>
            </tr>
        @endforeach
        @if(count($deductionsList) === 0)
            <tr><td colspan="2" style="color:#94a3b8;">No deductions applied.</td></tr>
        @endif
        <tr class="subtotal">
            <td>Total Deductions (Converted)</td>
            <td class="amt">{{ $currency }} {{ number_format($totalDeductions, 2) }}</td>
        </tr>
    </table>
    @endif
    @endif

    <!-- Net pay bar -->
    <table class="net-bar">
        <tr>
            <td class="net-label">Final Net Pay</td>
            <td class="net-value">{{ $currency }} {{ number_format($payroll->net_pay, 2) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        This is a system generated payslip and does not require a signature.
    </div>

</body>
</html>