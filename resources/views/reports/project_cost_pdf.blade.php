<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Project Cost Report</title>
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
            padding: 8px 10px;
            text-align: center;
        }
        .kpi-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-top: 3px;
        }
        .kpi-variance-positive {
            color: #059669;
        }
        .kpi-variance-negative {
            color: #dc2626;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-top: 15px;
            margin-bottom: 8px;
            border-left: 3px solid #10b981;
            padding-left: 8px;
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
            padding: 7px 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        table.data-table td {
            padding: 7px 8px;
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
        .variance-pos {
            color: #059669;
            font-weight: bold;
        }
        .variance-neg {
            color: #dc2626;
            font-weight: bold;
        }

        .project-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .project-card-header {
            background-color: #f9fafb;
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: bold;
            font-size: 11px;
            color: #111827;
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
        <div class="header-title">Project Cost Report</div>
        <div class="header-subtitle">Project-wise employees, hours, cost, budget and variance in AED</div>
        <div class="period-badge">{{ $period['label'] ?? 'Selected Period' }}</div>
    </div>

    <!-- KPI Summary Block -->
    <div class="kpi-container">
        <table class="kpi-table">
            <tr>
                <td style="width: 16.6%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Projects</div>
                        <div class="kpi-value">{{ $summary['total_projects'] ?? 0 }}</div>
                    </div>
                </td>
                <td style="width: 16.6%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Employees</div>
                        <div class="kpi-value">{{ $summary['total_employees'] ?? 0 }}</div>
                    </div>
                </td>
                <td style="width: 16.6%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Total Hours</div>
                        <div class="kpi-value">{{ $summary['total_hours_formatted'] ?? '0h' }}</div>
                    </div>
                </td>
                <td style="width: 16.6%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Total Cost</div>
                        <div class="kpi-value">AED {{ $summary['total_cost_formatted'] ?? '0.00' }}</div>
                    </div>
                </td>
                <td style="width: 16.6%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Total Budget</div>
                        <div class="kpi-value">{{ $summary['total_budget_formatted'] ? 'AED ' . $summary['total_budget_formatted'] : '-' }}</div>
                    </div>
                </td>
                <td style="width: 17%; padding: 3px;">
                    <div class="kpi-box">
                        <div class="kpi-label">Variance</div>
                        <div class="kpi-value {{ ($summary['is_over_budget'] ?? false) ? 'kpi-variance-negative' : 'kpi-variance-positive' }}">
                            {{ $summary['total_variance_formatted'] !== '-' ? 'AED ' . $summary['total_variance_formatted'] : '-' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Project-wise Summary Table -->
    <div class="section-title">Project Summary</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 32%;">PROJECT</th>
                <th class="text-center" style="width: 12%;">EMPLOYEES</th>
                <th class="text-right" style="width: 14%;">HOURS</th>
                <th class="text-right" style="width: 14%;">COST (AED)</th>
                <th class="text-right" style="width: 14%;">BUDGET (AED)</th>
                <th class="text-right" style="width: 14%;">VARIANCE (AED)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projects as $p)
                <tr>
                    <td style="font-weight: 500;">{{ $p['name'] }}</td>
                    <td class="text-center">{{ $p['employees_count'] }}</td>
                    <td class="text-right">{{ $p['total_hours_formatted'] }}</td>
                    <td class="text-right">{{ $p['total_cost_formatted'] }}</td>
                    <td class="text-right" style="{{ empty($p['budget']) ? 'color: #9ca3af;' : '' }}">
                        {{ $p['budget_formatted'] }}
                    </td>
                    <td class="text-right {{ $p['is_over_budget'] ? 'variance-neg' : ($p['variance'] !== null ? 'variance-pos' : '') }}">
                        {{ $p['variance_formatted'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; color: #6b7280;">No projects found for the selected period.</td>
                </tr>
            @endforelse
            @if(!empty($projects))
                <tr class="total-row">
                    <td>TOTAL SUMMARY</td>
                    <td class="text-center">{{ $summary['total_employees'] ?? 0 }}</td>
                    <td class="text-right">{{ $summary['total_hours_formatted'] ?? '0h' }}</td>
                    <td class="text-right">{{ $summary['total_cost_formatted'] ?? '0.00' }}</td>
                    <td class="text-right">{{ $summary['total_budget_formatted'] ?? '-' }}</td>
                    <td class="text-right {{ ($summary['is_over_budget'] ?? false) ? 'variance-neg' : 'variance-pos' }}">
                        {{ $summary['total_variance_formatted'] ?? '-' }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Project Employee Breakdowns -->
    <div class="section-title">Employee Breakdown by Project</div>
    @foreach($projects as $p)
        @if(!empty($p['employees']))
            <div class="project-card">
                <div class="project-card-header">
                    {{ $p['name'] }} &nbsp;&bull;&nbsp;
                    <span style="font-weight: normal; color: #4b5563;">{{ $p['total_hours_formatted'] }} &nbsp;|&nbsp; AED {{ $p['total_cost_formatted'] }}</span>
                </div>
                <table class="data-table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">EMPLOYEE</th>
                            <th style="width: 30%;">DESIGNATION</th>
                            <th class="text-right" style="width: 15%;">HOURS</th>
                            <th class="text-right" style="width: 15%;">COST (AED)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($p['employees'] as $emp)
                            <tr>
                                <td>
                                    <strong>{{ $emp['name'] }}</strong>
                                    @if(!empty($emp['code']) && $emp['code'] !== 'N/A')
                                        <span style="color: #6b7280; font-size: 9px;">({{ $emp['code'] }})</span>
                                    @endif
                                </td>
                                <td>{{ $emp['designation'] }}</td>
                                <td class="text-right">{{ $emp['total_hours_formatted'] }}</td>
                                <td class="text-right">{{ $emp['total_cost_formatted'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    <div class="footer">
        Generated on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; Probim HRMS Project Cost Management
    </div>
</body>
</html>

