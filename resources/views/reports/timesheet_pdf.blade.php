<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timesheets & Hours Report</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #10b981;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }
        .header-subtitle {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }
        .period-badge {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 4px;
            margin-top: 6px;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 18px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
        }
        .kpi-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .kpi-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            margin-top: 4px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        table.data-table td {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #fcfcfc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row td {
            background-color: #f3f4f6 !important;
            font-weight: bold;
            border-top: 2px solid #d1d5db;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #b45309;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-approved {
            background-color: #d1fae5;
            color: #047857;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }

        .footer {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">Timesheets & Hours Report</div>
        <div class="header-subtitle">Manage and approve employee hours logged against projects</div>
        <div class="period-badge">{{ $period['label'] ?? 'Selected Period' }}</div>
    </div>

    <!-- 3 KPI Cards -->
    <div class="kpi-container">
        <table class="kpi-table">
            <tr>
                <td style="width: 33.3%; padding: 4px;">
                    <div class="kpi-box">
                        <div class="kpi-label">TOTAL LOGGED HOURS</div>
                        <div class="kpi-value">{{ $kpis['total_logged_hours_formatted'] ?? '0h' }}</div>
                    </div>
                </td>
                <td style="width: 33.3%; padding: 4px;">
                    <div class="kpi-box">
                        <div class="kpi-label">PENDING APPROVALS</div>
                        <div class="kpi-value" style="color: #b45309;">{{ $kpis['pending_approvals_count'] ?? 0 }}</div>
                    </div>
                </td>
                <td style="width: 33.4%; padding: 4px;">
                    <div class="kpi-box">
                        <div class="kpi-label">APPROVED HOURS</div>
                        <div class="kpi-value" style="color: #047857;">{{ $kpis['approved_hours_formatted'] ?? '0h' }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Timesheets Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">EMPLOYEE</th>
                <th style="width: 25%;">PROJECT</th>
                <th style="width: 14%;">DATE</th>
                <th class="text-right" style="width: 12%;">HOURS</th>
                <th class="text-center" style="width: 12%;">STATUS</th>
                <th style="width: 12%;">APPROVER</th>
            </tr>
        </thead>
        <tbody>
            @forelse($timesheets as $t)
                <tr>
                    <td>
                        <strong>{{ $t['employee']['name'] }}</strong>
                        @if(!empty($t['employee']['manager_label']))
                            <br><span style="color: #6b7280; font-size: 9px;">{{ $t['employee']['manager_label'] }}</span>
                        @endif
                    </td>
                    <td>{{ $t['project']['name'] }}</td>
                    <td>{{ $t['date'] }}</td>
                    <td class="text-right"><strong>{{ $t['hours_formatted'] }}</strong></td>
                    <td class="text-center">
                        @if($t['status'] === 'approved')
                            <span class="badge-approved">Approved</span>
                        @elseif($t['status'] === 'rejected')
                            <span class="badge-rejected">Rejected</span>
                        @else
                            <span class="badge-pending">Pending</span>
                        @endif
                    </td>
                    <td>
                        @if(!empty($t['approver']['name']))
                            {{ $t['approver']['name'] }}
                        @else
                            <span style="color: #9ca3af;">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; color: #6b7280;">No timesheet entries found for the selected period.</td>
                </tr>
            @endforelse
            @if(!empty($timesheets))
                <tr class="total-row">
                    <td colspan="3">TOTAL SUMMARY</td>
                    <td class="text-right">{{ $kpis['total_logged_hours_formatted'] ?? '0h' }}</td>
                    <td class="text-center">{{ $kpis['pending_approvals_count'] ?? 0 }} Pending</td>
                    <td>-</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; Probim HRMS Timesheets & Hours
    </div>
</body>
</html>

