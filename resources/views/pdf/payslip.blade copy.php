<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    @php
        $data = $payroll->data ?? [];
        $employee = $payroll->employee;
        $currency = $payroll->currency ?? 'AED';

        $monthName = \Carbon\Carbon::createFromFormat(
            'm',
            $payroll->pay_period_month
        )->format('F');

        $yearFull = $payroll->pay_period_year;

        $paymentDate = $payroll->updated_at
            ? $payroll->updated_at->format('Y-m-d')
            : date('Y-m-d');

        $paymentId =
            'PS' .
            $payroll->pay_period_year .
            sprintf('%02d', $payroll->pay_period_month) .
            $payroll->id;

        $empName = trim(
            ($employee->first_name ?? '') . ' ' .
            ($employee->last_name ?? '')
        );

        $eid = $employee->employee_id
            ?? $employee->user_id
            ?? 'N/A';

        $designation = $employee->user->designation->name ?? 'N/A';

        $doj = $employee->joining_date ?? 'N/A';

        $bankDetails = $employee->bankDetails->first() ?? null;

        $accountNo = $bankDetails->account_number ?? 'N/A';
        $bankName = $bankDetails->bank_name ?? 'N/A';

        $ifsc = $bankDetails->ifsc_code
            ?? $bankDetails->swift_code
            ?? 'N/A';

        $branch = $bankDetails->branch_name ?? 'N/A';

        /*
        |--------------------------------------------------------------------------
        | Days In Month
        |--------------------------------------------------------------------------
        */

        $daysInMonth = \Carbon\Carbon::create(
            $payroll->pay_period_year,
            $payroll->pay_period_month,
            1
        )->daysInMonth;

        /*
        |--------------------------------------------------------------------------
        | Earnings
        |--------------------------------------------------------------------------
        */

        $locationBreakdown =
            $data['step_2']['location_breakdown']
            ?? $data['step_1']['location_breakdown']
            ?? [];

        $packagesEarnings = [];

        foreach ($locationBreakdown as $loc) {

            $pkgName =
                $loc['package']['name']
                ?? $loc['location_name']
                ?? 'Unknown';

            $pkgCurrency =
                $loc['currency']['code']
                ?? $currency;

            $pkgComponents = [];

            foreach ($loc['salary_components'] ?? [] as $comp) {

                $componentName =
                    $comp['name']
                    ?? 'Unknown';

                $pkgComponents[$componentName] =
                    $comp['amount'] ?? 0;
            }

            $packagesEarnings[] = [
                'name' => $pkgName,
                'currency' => $pkgCurrency,
                'components' => $pkgComponents,
                'worked_days' => $loc['worked_days'] ?? 0,
                'subtotal' =>
                    $loc['subtotal']
                    ?? array_sum($pkgComponents),
            ];
        }

        $totalWorkedDays =
            array_sum(
                array_column(
                    $locationBreakdown,
                    'worked_days'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Deductions
        |--------------------------------------------------------------------------
        */

        $deductionsList = [];

        $deductionsDetails =
            $data['step_4']['deductions'] ?? [];

        foreach ($deductionsDetails as $ded) {

            $name =
                $ded['type']
                ?? $ded['name']
                ?? $ded['component_name']
                ?? 'Unknown';

            $deductionsList[$name] =
                ($deductionsList[$name] ?? 0)
                + ($ded['amount'] ?? 0);
        }

        $totalDeductions =
            $data['step_6']['total_deductions']
            ?? array_sum($deductionsList);

        /*
        |--------------------------------------------------------------------------
        | Leave Details
        |--------------------------------------------------------------------------
        */

        $leaveDetails = $leaveDetails ?? [];
        $totalLeaveDays = $totalLeaveDays ?? 0;

        /*
        |--------------------------------------------------------------------------
        | Mask Bank Details
        |--------------------------------------------------------------------------
        */

        $maskedAccount =
            !empty($accountNo) && $accountNo !== 'N/A'
            ? substr($accountNo, 0, 4)
            . str_repeat(
                'X',
                max(0, strlen($accountNo) - 6)
            )
            . substr($accountNo, -2)
            : 'N/A';

        $maskedIfsc =
            !empty($ifsc) && $ifsc !== 'N/A'
            ? substr($ifsc, 0, 4)
            . str_repeat(
                'X',
                max(0, strlen($ifsc) - 4)
            )
            : 'N/A';

        /*
        |--------------------------------------------------------------------------
        | Final Salary
        |--------------------------------------------------------------------------
        */

        $netPay = (float) ($payroll->net_pay ?? 0);
    @endphp

    <title>Payslip - {{ $empName }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 4mm 6mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #ffffff;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.15;
            color: #111827;
        }

        /* =========================================
   MAIN WRAPPER - FULL A4 HEIGHT
   ========================================= */

        .payslip-wrapper {
            position: relative;
            width: 100%;
            background: #ffffff;
            border: 1px solid #d1d5db;
            margin: 0;
            padding: 0;
            padding-bottom: 8mm;
        }

        /* =========================================
           TOP ACCENT
           ========================================= */

        .header-accent {
            height: 3px;
            background: #047857;
        }

        /* =========================================
           HEADER
           ========================================= */

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .header-table td {
            border: none;
            vertical-align: top;
            padding: 6px 10px 4px 10px;
        }

        .company-details {
            width: 65%;
        }

        .doc-title {
            width: 35%;
            text-align: right;
            font-size: 20px;
        }

        .company-details h1 {
            margin: 0;
            padding: 0;
            font-size: 24px;
            line-height: 1.1;
            font-weight: bold;
            color: #064e3b;
            text-transform: uppercase;
        }

        .company-details p {
            margin: 1px 0 0 0;
            padding: 0;
            font-size: 6px;
            color: #6b7280;
        }

        .doc-title h2 {
            margin: 0;
            padding: 0;
            font-size: 12px;
            line-height: 1.1;
            font-weight: bold;
            color: #047857;
        }

        .doc-title p {
            margin: 1px 0 0 0;
            padding: 0;
            font-size: 5.5px;
            color: #6b7280;
        }

        /* =========================================
           MAIN CONTENT
           ========================================= */

        .main-content {
            padding: 0 10px 4px 10px;
        }

        /* =========================================
           PAY SUMMARY
           ========================================= */

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            background: #f0fdf4;
            border-left: 2px solid #047857;
            margin-bottom: 20px;
        }

        .summary-table td {
            width: 25%;
            border: none;
            padding: 4px 6px;
            vertical-align: middle;
        }

        .strip-label {
            display: block;
            font-size: 5.5px;
            line-height: 1.1;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: bold;
        }

        .strip-value {
            display: block;
            margin-top: 1px;
            font-size: 7.5px;
            line-height: 1.1;
            font-weight: bold;
            color: #064e3b;
        }

        /* =========================================
           EMPLOYEE / BANK INFORMATION
           ========================================= */

        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 0;
            margin: 0 -4px 5px -4px;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            background: #ffffff;
        }

        .col-title {
            font-size: 12px;
            line-height: 1.1;
            font-weight: bold;
            text-transform: uppercase;
            color: #047857;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 2px;
            margin-bottom: 3px;
        }

        .info-row {
            width: 100%;
            margin-bottom: 2px;
            font-size: 6.2px;
            line-height: 1.1;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-row-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-row-table td {
            border: none;
            padding: 0;
            background: transparent;
        }

        .info-label {
            width: 40%;
            color: #6b7280;
        }

        .info-val {
            width: 60%;
            text-align: right;
            font-weight: bold;
            color: #111827;
            word-wrap: break-word;
        }

        /* =========================================
           TABLE CONTAINER
           ========================================= */

        .table-container {
            width: 100%;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }

        /* =========================================
           SALARY TABLE
           ========================================= */

        .salary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8px;
            margin-top:20px;
        }

        .salary-table th {
            background: #047857;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 4px 6px;
            font-size: 6.5px;
            line-height: 1.1;
            border: 1px solid #047857;
        }

        .salary-table th.amount-col {
            width: 25%;
            text-align: right;
        }

        .salary-table td {
            padding: 3.5px 6px;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
            line-height: 1.1;
        }

        .salary-table td.amount-col {
            text-align: right;
            white-space: nowrap;
        }

        /* =========================================
           PACKAGE HEADER
           ========================================= */

        .pkg-header-row td {
            background: #d1fae5;
            font-weight: bold;
            color: #064e3b;
            padding-top: 3px;
            padding-bottom: 3px;
            border-bottom: 1px solid #a7f3d0;
        }

        .indent-item {
            padding-left: 12px !important;
            color: #374151;
        }

        /* =========================================
           SUBTOTAL
           ========================================= */

        .subtotal-row td {
            background: #f9fafb;
            font-weight: bold;
            border-bottom: 1px solid #d1d5db;
            padding-top: 3px;
            padding-bottom: 3px;
        }

        /* =========================================
           LEAVE SUMMARY
           ========================================= */

        .leave-summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
            font-size: 8px;
            margin-top:20px;
        }

        .leave-summary-table th {
            background: #047857;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 4px 6px;
            font-size: 6.5px;
            line-height: 1.1;
            border: 1px solid #047857;
        }

        .leave-summary-table td {
            padding: 3.5px 6px;
            border-bottom: 1px solid #e5e7eb;
            line-height: 1.1;
        }

        .leave-summary-table .leave-type {
            width: 35%;
        }

        .leave-summary-table .leave-date {
            width: 20%;
        }

        .leave-summary-table .leave-days {
            width: 15%;
            text-align: right;
            white-space: nowrap;
        }

        .leave-total-row td {
            background: #f9fafb;
            font-weight: bold;
            border-bottom: 1px solid #d1d5db;
        }

        /* =========================================
           NET PAY
           ========================================= */

        .net-pay-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px dashed #047857;
            background: #f0fdf4;
            margin-top: 20px;
        }

        .net-pay-table td {
            border: none;
            padding: 5px 8px;
            vertical-align: middle;
        }

        .net-pay-text {
            width: 60%;
            font-size: 14px;
            font-weight: bold;
            color: #064e3b;
        }

        .net-pay-val {
            width: 40%;
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            white-space: nowrap;
        }

        /* =========================================
           FOOTER
           ========================================= */

        /* =========================================
   FOOTER - BOTTOM OF A4 PAGE
   ========================================= */

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #e5e7eb;
            padding: 4px;
            text-align: center;
            font-size: 5.5px;
            line-height: 1.1;
            color: #6b7280;
            background: #f9fafb;
        }

        /* =========================================
           DOMPDF PAGE BREAK CONTROL
           ========================================= */

        .header-table,
        .summary-table,
        .info-table,
        .salary-table,
        .leave-summary-table,
        .net-pay-table {
            page-break-inside: avoid;
        }

        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        /* =========================================
           PRINT
           ========================================= */

        @media print {

            @page {
                size: A4 portrait;
                margin: 6mm;
            }

            html,
            body {
                margin: 0;
                padding: 0;
            }

        }
    </style>

</head>

<body>

    <div class="payslip-wrapper">

        <!-- =========================================
         HEADER ACCENT
         ========================================= -->

        <div class="header-accent"></div>


        <!-- =========================================
         HEADER
         ========================================= -->

        <table class="header-table">

            <tr>

                <td class="company-details">

                    <h1>Probim LLC</h1>

                    <p>
                        Payroll &amp; HR Services
                    </p>

                </td>


                <td class="doc-title">

                    <h2>PAYSLIP</h2>

                    <p>
                        #{{ $paymentId }}
                    </p>

                </td>

            </tr>

        </table>


        <div class="main-content">

            <!-- =========================================
             PAY SUMMARY
             ========================================= -->

            <table class="summary-table">

                <tr>

                    <td>

                        <span class="strip-label">
                            Pay Period
                        </span>

                        <span class="strip-value">
                            {{ $monthName }} {{ $yearFull }}
                        </span>

                    </td>


                    <td>

                        <span class="strip-label">
                            Payment Date
                        </span>

                        <span class="strip-value">
                            {{ $paymentDate }}
                        </span>

                    </td>


                    <td>

                        <span class="strip-label">
                            Days Worked
                        </span>

                        <span class="strip-value">
                            {{ $totalWorkedDays }} / {{ $daysInMonth }}
                        </span>

                    </td>


                    <td>

                        <span class="strip-label">
                            Net Payable
                        </span>

                        <span class="strip-value">
                            {{ $currency }}
                            {{ number_format($netPay, 2) }}
                        </span>

                    </td>

                </tr>

            </table>


            <!-- =========================================
             EMPLOYEE & BANK DETAILS
             ========================================= -->

            <table class="info-table">

                <tr>

                    <!-- Employee Information -->

                    <td>

                        <div class="col-title">
                            Employee Information
                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Name
                                    </td>

                                    <td class="info-val">
                                        {{ $empName }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        EID
                                    </td>

                                    <td class="info-val">
                                        {{ $eid }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Designation
                                    </td>

                                    <td class="info-val">
                                        {{ $designation }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Joining Date
                                    </td>

                                    <td class="info-val">
                                        {{ $doj }}
                                    </td>

                                </tr>

                            </table>

                        </div>

                    </td>


                    <!-- Bank Details -->

                    <td>

                        <div class="col-title">
                            Bank Details
                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Bank
                                    </td>

                                    <td class="info-val">
                                        {{ $bankName }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Account No.
                                    </td>

                                    <td class="info-val">
                                        {{ $maskedAccount }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        SWIFT Code
                                    </td>

                                    <td class="info-val">
                                        {{ $maskedIfsc }}
                                    </td>

                                </tr>

                            </table>

                        </div>


                        <div class="info-row">

                            <table class="info-row-table">

                                <tr>

                                    <td class="info-label">
                                        Branch
                                    </td>

                                    <td class="info-val">
                                        {{ $branch }}
                                    </td>

                                </tr>

                            </table>

                        </div>

                    </td>

                </tr>

            </table>


            <!-- =========================================
             LEAVE SUMMARY
             ========================================= -->

            <div class="table-container">

                <table class="leave-summary-table">

                    <thead>

                        <tr>

                            <th class="leave-type">
                                Leave Type
                            </th>

                            <th class="leave-date">
                                From
                            </th>

                            <th class="leave-date">
                                To
                            </th>

                            <th class="leave-days">
                                Days
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @if(count($leaveDetails) > 0)

                            @foreach($leaveDetails as $leave)

                                <tr>

                                    <td>
                                        {{ $leave['leave_type'] }}
                                    </td>

                                    <td>
                                        {{ $leave['start_date'] }}
                                    </td>

                                    <td>
                                        {{ $leave['end_date'] }}
                                    </td>

                                    <td class="leave-days">
                                        {{ $leave['days'] }}
                                    </td>

                                </tr>

                            @endforeach


                            <tr class="leave-total-row">

                                <td colspan="3">
                                    Total Leave Taken
                                </td>

                                <td class="leave-days">
                                    {{ $totalLeaveDays }}
                                </td>

                            </tr>

                        @else

                            <tr>

                                <td colspan="4" style="text-align:center;">
                                    No leaves taken during this payroll period
                                </td>

                            </tr>

                        @endif

                    </tbody>

                </table>

            </div>


            <!-- =========================================
             EARNINGS BREAKDOWN
             ========================================= -->

            <div class="table-container">

                <table class="salary-table">

                    <thead>

                        <tr>

                            <th>
                                Earnings Breakdown
                            </th>

                            <th class="amount-col">
                                Amount
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @if(count($packagesEarnings) > 0)

                            @foreach($packagesEarnings as $index => $package)

                                                <!-- Package Header -->

                                                <tr class="pkg-header-row">

                                                    <td colspan="2">

                                                        Package {{ $index + 1 }}

                                                        -

                                                        {{ $package['name'] }}

                                                        ({{ $package['worked_days'] }} Days)

                                                    </td>

                                                </tr>


                                                <!-- Salary Components -->

                                                @if(count($package['components']) > 0)

                                                    @foreach($package['components'] as $componentName => $amount)

                                                        <tr>

                                                            <td class="indent-item">

                                                                {{ $componentName }}

                                                            </td>

                                                            <td class="amount-col">

                                                                {{ $package['currency'] }}

                                                                {{ number_format((float) $amount, 2) }}

                                                            </td>

                                                        </tr>

                                                    @endforeach

                                                @else

                                                    <tr>

                                                        <td class="indent-item">

                                                            No earnings recorded in this package

                                                        </td>

                                                        <td class="amount-col">

                                                            {{ $package['currency'] }}

                                                            0.00

                                                        </td>

                                                    </tr>

                                                @endif


                                                <!-- Package Subtotal -->

                                                <tr class="subtotal-row">

                                                    <td>
                                                        Subtotal Package {{ $index + 1 }}
                                                    </td>

                                                    <td class="amount-col">

                                                        {{ $package['currency'] }}

                                                        {{ number_format(
                                    (float) $package['subtotal'],
                                    2
                                ) }}

                                                    </td>

                                                </tr>

                            @endforeach

                        @else

                            <tr>

                                <td class="indent-item">
                                    No earnings recorded
                                </td>

                                <td class="amount-col">

                                    {{ $currency }} 0.00

                                </td>

                            </tr>

                        @endif

                    </tbody>

                </table>

            </div>


            <!-- =========================================
             DEDUCTIONS BREAKDOWN
             ========================================= -->

            <div class="table-container">

                <table class="salary-table">

                    <thead>

                        <tr>

                            <th>
                                Deductions Breakdown
                            </th>

                            <th class="amount-col">
                                Amount
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @if(count($deductionsList) > 0)

                            @foreach($deductionsList as $name => $amount)

                                                <tr>

                                                    <td>
                                                        {{ $name }}
                                                    </td>

                                                    <td class="amount-col">

                                                        {{ $currency }}

                                                        {{ number_format(
                                    (float) $amount,
                                    2
                                ) }}

                                                    </td>

                                                </tr>

                            @endforeach

                        @else

                            <tr>

                                <td>
                                    No deductions
                                </td>

                                <td class="amount-col">

                                    {{ $currency }} 0.00

                                </td>

                            </tr>

                        @endif


                        <!-- Total Deductions -->

                        <tr class="subtotal-row">

                            <td>
                                Total Deductions
                            </td>

                            <td class="amount-col">

                                {{ $currency }}

                                {{ number_format(
    (float) $totalDeductions,
    2
) }}

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>


            <!-- =========================================
             FINAL NET PAY
             ========================================= -->

            <table class="net-pay-table">

                <tr>

                    <td class="net-pay-text">

                        FINAL NET PAY

                    </td>


                    <td class="net-pay-val">

                        {{ $currency }}

                        {{ number_format(
    $netPay,
    2
) }}

                    </td>

                </tr>

            </table>

        </div>


        <!-- =========================================
         FOOTER
         ========================================= -->

        <div class="footer">

            This is a system generated payslip and does not require a signature.

        </div>

    </div>

</body>

</html>