<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $data = $payroll->data ?? [];
        $employee = $payroll->employee;
        $currency = $payroll->currency ?? 'AED';

        $monthName = \Carbon\Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F');
        $yearFull = $payroll->pay_period_year;

        $paymentDate = $payroll->updated_at ? $payroll->updated_at->format('Y-m-d') : date('Y-m-d');
        $paymentId = 'PS' . $payroll->pay_period_year . sprintf('%02d', $payroll->pay_period_month) . $payroll->id;

        $empName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        $eid = $employee->employee_id ?? $employee->user_id ?? 'N/A';
        $designation = $employee->user->designation->name ?? 'N/A';
        $doj = $employee->joining_date ?? 'N/A';

        $bankDetails = $employee->bankDetails->first() ?? null;
        $accountNo = $bankDetails->account_number ?? 'N/A';
        $bankName = $bankDetails->bank_name ?? 'N/A';
        $ifsc = $bankDetails->ifsc_code ?? $bankDetails->swift_code ?? 'N/A';
        $branch = $bankDetails->branch_name ?? 'N/A';

        $daysInMonth = 30;

        // --- Earnings (per package / location) ---
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
                'subtotal' => $loc['subtotal'] ?? array_sum($pkgComponents),
            ];
        }

        $totalWorkedDays = array_sum(array_column($locationBreakdown, 'worked_days'));

        // --- Deductions ---
        $deductionsList = [];
        $deductionsDetails = $data['step_4']['deductions'] ?? [];
        foreach ($deductionsDetails as $ded) {
            $name = $ded['type'] ?? $ded['name'] ?? $ded['component_name'] ?? 'Unknown';
            $deductionsList[$name] = ($deductionsList[$name] ?? 0) + ($ded['amount'] ?? 0);
        }
        $totalDeductions = $data['step_6']['total_deductions'] ?? array_sum($deductionsList);

        $maskedAccount = !empty($accountNo) && $accountNo !== 'N/A'
            ? substr($accountNo, 0, 4) . str_repeat('X', max(0, strlen($accountNo) - 6)) . substr($accountNo, -2)
            : 'N/A';

        $maskedIfsc = !empty($ifsc) && $ifsc !== 'N/A'
            ? substr($ifsc, 0, 4) . str_repeat('X', max(0, strlen($ifsc) - 4))
            : 'N/A';
    @endphp
    <title>Payslip - {{ $empName }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            line-height: 1.15;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        th,
        td {
            padding: 2px 3px;
            font-size: 7px;
            line-height: 1.1;
            word-wrap: break-word;
        }

        h1 {
            font-size: 14px;
            margin: 2px 0 4px;
        }

        h2 {
            font-size: 11px;
            margin: 3px 0;
        }

        h3 {
            font-size: 9px;
            margin: 2px 0;
        }

        p {
            margin: 2px 0;
        }

        .section {
            margin: 3px 0;
            padding: 0;
            page-break-inside: avoid;
        }

        .row {
            page-break-inside: avoid;
        }

        img {
            max-width: 100%;
            max-height: 35px;
            object-fit: contain;
        }

        * {
            box-sizing: border-box;
        }

        @media print {

            html,
            body {
                width: 210mm;
                height: 297mm;
                overflow: hidden;
            }

            .container {
                width: 194mm;
                height: 281mm;
                overflow: hidden;
            }
        }
    </style>
    <style>
        :root {
            --emerald-main: #047857;
            --emerald-dark: #064e3b;
            --emerald-light: #d1fae5;
            --emerald-tint: #f0fdf4;
            --gray-subtle: #f9fafb;
            --gray-border: #e5e7eb;
            --text-dark: #111827;
            --text-muted: #6b7280;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: #f3f4f6;
            color: var(--text-dark);
            padding: 40px 15px;
            display: flex;
            justify-content: center;
        }

        .payslip-wrapper {
            background: #ffffff;
            width: 100%;
            max-width: 820px;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-border);
            overflow: hidden;
        }

        table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        thead {
            display: table-header-group;
        }

        /* Top Bar Accent */
        .header-accent {
            background: var(--emerald-main);
            height: 10px;
        }

        /* Header Layout */
        .header {
            padding: 28px 36px 20px 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-details h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--emerald-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .company-details p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--emerald-main);
            letter-spacing: 1px;
        }

        .doc-title p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .main-content {
            padding: 0 36px 36px 36px;
        }

        /* Highlight Strip */
        .pay-summary-strip {
            background: var(--emerald-tint);
            border-left: 4px solid var(--emerald-main);
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 6px 6px 0;
            margin-bottom: 24px;
        }

        .strip-item {
            display: flex;
            flex-direction: column;
        }

        .strip-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .strip-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--emerald-dark);
            margin-top: 2px;
        }

        /* Two Column Info Section */
        .info-columns {
            display: flex;
            gap: 24px;
            margin-bottom: 28px;
        }

        .info-col {
            flex: 1;
            border: 1px solid var(--gray-border);
            border-radius: 6px;
            padding: 16px 20px;
            background-color: #ffffff;
        }

        .col-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--emerald-main);
            border-bottom: 1px solid var(--gray-border);
            padding-bottom: 8px;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: var(--text-muted);
        }

        .info-val {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Financial Table */
        .table-container {
            margin-bottom: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: var(--emerald-main);
            color: #ffffff;
            font-weight: 600;
            text-align: left;
            padding: 10px 16px;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        th.amount-col,
        td.amount-col {
            text-align: right;
        }

        td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--gray-border);
            color: var(--text-dark);
        }

        .pkg-header-row td {
            background-color: var(--emerald-light);
            font-weight: 700;
            color: var(--emerald-dark);
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .indent-item {
            padding-left: 32px;
        }

        .muted-row td {
            color: var(--text-muted);
            font-style: italic;
        }

        .subtotal-row td {
            background-color: var(--gray-subtle);
            font-weight: 700;
            border-bottom: 2px solid var(--gray-border);
        }

        /* Net Pay Banner */
        .net-pay-banner {
            border: 2px dashed var(--emerald-main);
            background: var(--emerald-tint);
            border-radius: 8px;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .net-pay-text {
            font-size: 15px;
            font-weight: 700;
            color: var(--emerald-dark);
        }

        .net-pay-val {
            font-size: 26px;
            font-weight: 800;
            color: var(--emerald-main);
        }

        /* Footer */
        .footer {
            border-top: 1px solid var(--gray-border);
            padding: 16px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            background: var(--gray-subtle);
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .payslip-wrapper {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="payslip-wrapper">
        <div class="header-accent"></div>

        <!-- Header Section -->
        <div class="header">
            <div class="company-details">
                <h1>{{ $company->name ?? 'Probim LLCdsdsa' }}</h1>
                <p>{{ $company->tagline ?? 'Payroll & HR Services' }}</p>
            </div>
            <div class="doc-title">
                <h2>PAYSLIP</h2>
                <p>#{{ $paymentId }}</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Highlight Bar -->
            <div class="pay-summary-strip">
                <div class="strip-item">
                    <span class="strip-label">Pay Period</span>
                    <span class="strip-value">{{ $monthName }} {{ $yearFull }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Payment Date</span>
                    <span class="strip-value">{{ $paymentDate }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Days Worked</span>
                    <span class="strip-value">{{ $totalWorkedDays }} / {{ $daysInMonth }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Net Payable</span>
                    <span class="strip-value">{{ $currency }} {{ number_format($payroll->net_pay, 2) }}</span>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="info-columns">
                <!-- Employee Info -->
                <div class="info-col">
                    <div class="col-title">Employee Information</div>
                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-val">{{ $empName }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">EID</span>
                        <span class="info-val">{{ $eid }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Designation</span>
                        <span class="info-val">{{ $designation }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Joining Date</span>
                        <span class="info-val">{{ $doj }}</span>
                    </div>
                </div>

                <!-- Disbursement Details -->
                <div class="info-col">
                    <div class="col-title">Disbursement Details</div>
                    <div class="info-row">
                        <span class="info-label">Bank</span>
                        <span class="info-val">{{ $bankName }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account No.</span>
                        <span class="info-val">{{ $maskedAccount }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">SWIFT Code</span>
                        <span class="info-val">{{ $maskedIfsc }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Branch</span>
                        <span class="info-val">{{ $branch }}</span>
                    </div>
                </div>
            </div>

            <!-- Earnings Breakdown Table (single, dynamic) -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Earnings Breakdown</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packagesEarnings as $pkg)
                            <tr class="pkg-header-row">
                                <td colspan="2">{{ $pkg['name'] }} ({{ $pkg['worked_days'] }} Days)</td>
                            </tr>
                            @forelse($pkg['components'] as $name => $amount)
                                <tr>
                                    <td class="indent-item">{{ $name }}</td>
                                    <td class="amount-col">{{ $pkg['currency'] }} {{ number_format($amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="muted-row">
                                    <td class="indent-item">No earnings recorded in this package</td>
                                    <td class="amount-col">{{ $pkg['currency'] }} 0.00</td>
                                </tr>
                            @endforelse
                            <tr class="subtotal-row">
                                <td>Subtotal — {{ $pkg['name'] }}</td>
                                <td class="amount-col">{{ $pkg['currency'] }} {{ number_format($pkg['subtotal'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="muted-row">
                                <td colspan="2">No earnings packages recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Deductions Table (single, dynamic) -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Deductions Breakdown</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deductionsList as $name => $amount)
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="amount-col">{{ $currency }} {{ number_format($amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr class="muted-row">
                                <td>No deductions applied</td>
                                <td class="amount-col">{{ $currency }} 0.00</td>
                            </tr>
                        @endforelse
                        <tr class="subtotal-row">
                            <td>Total Deductions (Converted)</td>
                            <td class="amount-col">{{ $currency }} {{ number_format($totalDeductions, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Final Summary -->
            <div class="net-pay-banner">
                <div class="net-pay-text">FINAL NET PAY</div>
                <div class="net-pay-val">{{ $currency }} {{ number_format($payroll->net_pay, 2) }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            This is a system generated payslip and does not require a signature.
        </div>
    </div>

</body>

</html>