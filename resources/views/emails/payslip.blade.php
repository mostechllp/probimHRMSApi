<!DOCTYPE html>
<html>
<head>
    <title>Payslip</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 0 auto; padding: 24px;">

        <p>Dear {{ $payroll->employee->first_name ?? 'Team Member' }},</p>

        <p>
            Your payslip for
            <strong>{{ \Carbon\Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F') }} {{ $payroll->pay_period_year }}</strong>
            has been generated and is attached to this email as a PDF.
        </p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
            <tr>
                <td style="padding: 12px 16px; font-size: 13px; color: #666;">Pay Period</td>
                <td style="padding: 12px 16px; font-size: 13px; font-weight: bold; text-align: right;">
                    {{ \Carbon\Carbon::createFromFormat('m', $payroll->pay_period_month)->format('F') }} {{ $payroll->pay_period_year }}
                </td>
            </tr>
            <tr style="background-color: #eef1f4;">
                <td style="padding: 12px 16px; font-size: 13px; color: #666;">Net Pay</td>
                <td style="padding: 12px 16px; font-size: 16px; font-weight: bold; text-align: right; color: #1a7f37;">
                    {{ $payroll->currency ?? '' }} {{ number_format($payroll->net_pay ?? 0, 2) }}
                </td>
            </tr>
        </table>

        <p>
            Please review your payslip carefully. If you notice any discrepancy or have questions about
            your salary, deductions, or working days for this period, reach out to the HR department
            so we can look into it promptly.
        </p>

        <p>Thank you for your continued contribution to the team.</p>

        <p style="margin-top: 24px;">
            Warm regards,<br>
            <strong>HR Department</strong>
        </p>

        <hr style="border: none; border-top: 1px solid #e5e5e5; margin: 24px 0;">
        <p style="font-size: 11px; color: #999;">
            This is an automated email. Please do not reply directly to this message — contact HR through your usual channel for any queries.
        </p>
    </div>
</body>
</html>