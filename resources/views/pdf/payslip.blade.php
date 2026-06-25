<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0 0 4px; font-size: 20px; }
        .meta { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .meta td { padding: 4px 8px; width: 50%; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .total td, .total th { font-weight: bold; background-color: #e8f5e9; }
        .conversion-note { font-size: 11px; color: #888; margin-bottom: 12px; }
        .section-title { font-weight: bold; margin: 14px 0 4px; font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Payslip</h2>
    </div>

    @php
        $data      = $payroll->data ?? [];
        $employee  = $payroll->employee;
        $currency  = $data['currency'] ?? 'AED';
        $convFrom  = $data['conversion_from'] ?? null;
        $convRate  = $data['conversion_rate'] ?? null;

        $step6 = $data['step_6'] ?? [];
        $step4 = $data['step_4'] ?? [];
        $step3 = $data['step_3'] ?? [];
    @endphp

    <table class="meta">
        <tr>
            <td><strong>Employee:</strong>
                {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ('ID: ' . $payroll->user_id) }}
            </td>
            <td><strong>Period:</strong> {{ $payroll->pay_period_month }} / {{ $payroll->pay_period_year }}</td>
        </tr>
        <tr>
            <td><strong>Currency:</strong> {{ $currency }}</td>
            <td><strong>Status:</strong> {{ ucfirst($payroll->status) }}</td>
        </tr>
    </table>

    @if($convFrom && $convRate && $convFrom !== $currency)
        <p class="conversion-note">
            * All amounts converted from {{ $convFrom }} to {{ $currency }} at a rate of 1 {{ $convFrom }} = {{ $convRate }} {{ $currency }}.
        </p>
    @endif

    {{-- Earnings --}}
    <div class="section-title">Earnings</div>
    <table class="table">
        <tr>
            <th>Description</th>
            <th>Amount ({{ $currency }})</th>
        </tr>
        <tr>
            <td>Gross Earnings</td>
            <td>{{ number_format($step6['gross_earnings'] ?? 0, 2) }}</td>
        </tr>
        @if(isset($step3['overtime_amount']) && $step3['overtime_amount'] > 0)
        <tr>
            <td>Overtime</td>
            <td>{{ number_format($step3['overtime_amount'], 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- Deductions --}}
    @if(!empty($step4))
    <div class="section-title">Deductions</div>
    <table class="table">
        <tr>
            <th>Description</th>
            <th>Amount ({{ $currency }})</th>
            @if(isset(array_values($step4)[0]['statutory'])) <th>Statutory</th> @endif
        </tr>
        @if(isset($step4['deductions']) && is_array($step4['deductions']))
            @foreach($step4['deductions'] as $ded)
            <tr>
                <td>{{ $ded['name'] ?? $ded['component_name'] ?? 'Deduction' }}</td>
                <td>{{ number_format($ded['amount'] ?? 0, 2) }}</td>
                @if(isset($ded['statutory']))<td>{{ $ded['statutory'] ? 'Yes' : 'No' }}</td>@endif
            </tr>
            @endforeach
        @else
            @foreach($step4 as $key => $item)
                @if(is_array($item) && isset($item['amount']))
                <tr>
                    <td>{{ $item['name'] ?? $item['component_name'] ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                    <td>{{ number_format($item['amount'], 2) }}</td>
                    @if(isset($item['statutory']))<td>{{ $item['statutory'] ? 'Yes' : 'No' }}</td>@endif
                </tr>
                @endif
            @endforeach
        @endif
        <tr>
            <td><strong>Total Deductions</strong></td>
            <td><strong>{{ number_format($step6['total_deductions'] ?? 0, 2) }}</strong></td>
            @if(isset(array_values($step4)[0]['statutory'])) <td></td> @endif
        </tr>
    </table>
    @endif

    {{-- Net Pay Summary --}}
    <table class="table">
        <tr class="total">
            <th>Final Net Pay</th>
            <td>{{ $currency }} {{ number_format($step6['final_net_pay'] ?? 0, 2) }}</td>
        </tr>
    </table>

</body>
</html>
