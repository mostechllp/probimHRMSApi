<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Payslip</h2>
        <p><strong>Employee ID:</strong> {{ $payroll->employee_id }}</p>
        <p><strong>Period:</strong> {{ $payroll->pay_period_month }} / {{ $payroll->pay_period_year }}</p>
    </div>

    @if(isset($payroll->data['step_6']))
        <table class="table">
            <tr>
                <th>Gross Earnings</th>
                <td>{{ $payroll->data['step_6']['gross_earnings'] ?? '0.00' }}</td>
            </tr>
            <tr>
                <th>Total Deductions</th>
                <td>{{ $payroll->data['step_6']['total_deductions'] ?? '0.00' }}</td>
            </tr>
            <tr>
                <th class="total">Final Net Pay</th>
                <td class="total">{{ $payroll->data['step_6']['final_net_pay'] ?? '0.00' }}</td>
            </tr>
        </table>
    @else
        <p>No final summary data available.</p>
    @endif
</body>
</html>
