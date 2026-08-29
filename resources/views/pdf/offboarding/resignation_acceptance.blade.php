<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resignation Acceptance Letter</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 20mm; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.6;
            color: #1a1a2e;
            margin: 0; padding: 0;
        }

        .header-bar { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header-bar td { border: none; vertical-align: middle; }
        .company-name {
            font-size: 16pt; font-weight: bold;
            color: #4a1942; text-transform: uppercase; letter-spacing: 1px;
        }
        .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
        .doc-label {
            text-align: right;
            font-size: 12pt; font-weight: bold;
            color: #4a1942; letter-spacing: 2px; text-transform: uppercase;
        }
        .header-rule { border: none; border-top: 3px solid #4a1942; margin: 0 0 14px 0; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 9pt; }
        .meta-table td { border: none; padding: 1px 0; }
        .meta-label { color: #555; width: 140px; }
        .meta-val   { font-weight: bold; color: #1a1a2e; }
        .meta-right { text-align: right; }

        .addressed-to {
            margin-bottom: 18px;
        }
        .addressed-to .emp-name  { font-weight: bold; font-size: 11pt; }
        .addressed-to .emp-desig { color: #555; font-size: 9pt; }

        .subject-line {
            font-weight: bold; font-size: 10.5pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px; margin-bottom: 14px;
        }

        .body-para { margin-bottom: 12px; text-align: justify; }

        .info-block {
            border-left: 4px solid #4a1942;
            background: #fdf4ff;
            padding: 8px 14px;
            margin: 16px 0;
        }
        .info-block table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .info-block table td { padding: 3px 0; border: none; }
        .ib-label { color: #555; width: 180px; }
        .ib-val   { font-weight: bold; }

        .sig-section { margin-top: 36px; }
        .sig-label   { font-size: 9pt; color: #555; }
        .sig-name    { font-weight: bold; margin-top: 28px; font-size: 10pt; }
        .sig-title   { font-size: 9pt; color: #555; }

        .footer {
            position: absolute; bottom: 0; left: 0; right: 0;
            border-top: 1px solid #ccc;
            padding: 6px 20mm;
            font-size: 7.5pt; color: #888; text-align: center;
        }
    </style>
</head>
<body>

@php
    $employee        = $offboarding->employee;
    $user            = $employee?->user;
    $empName         = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $designation     = $user?->designation?->name ?? 'N/A';
    $department      = $user?->department?->name ?? 'N/A';

    $noticePeriodDays   = $offboarding->notice_period_days   ?? 0;
    $noticeStartDate    = $offboarding->notice_start_date
        ? \Carbon\Carbon::parse($offboarding->notice_start_date)->format('d M Y')
        : 'N/A';
    $lastWorkingDay     = $offboarding->last_working_day
        ? \Carbon\Carbon::parse($offboarding->last_working_day)->format('d M Y')
        : 'N/A';
    $reasonForLeaving   = $offboarding->reason_for_leaving ?? '';
    $issueDate          = now()->format('d M Y');
    $refNo              = 'RA/' . now()->format('Y') . '/' . str_pad($offboarding->id, 4, '0', STR_PAD_LEFT);

    $managerEmployee = $offboarding->reportingManager;
    $managerName     = trim(($managerEmployee?->first_name ?? '') . ' ' . ($managerEmployee?->last_name ?? ''));
    $managerDesig    = $managerEmployee?->user?->designation?->name ?? 'HR Manager';
@endphp

<table class="header-bar">
    <tr>
        <td>
            <div class="company-name">Probim LLC</div>
            <div class="company-sub">HR &amp; Payroll Services · Dubai, UAE</div>
        </td>
        <td class="doc-label">Resignation Acceptance Letter</td>
    </tr>
</table>
<hr class="header-rule">

<table class="meta-table">
    <tr>
        <td class="meta-label">Reference No.</td>
        <td class="meta-val">{{ $refNo }}</td>
        <td class="meta-right">Date: <strong>{{ $issueDate }}</strong></td>
    </tr>
</table>

<!-- Addressed to the employee -->
<div class="addressed-to">
    <div class="emp-name">{{ $empName }}</div>
    <div class="emp-desig">{{ $designation }} &mdash; {{ $department }}</div>
    <div class="emp-desig">Probim LLC, Dubai, UAE</div>
</div>

<div class="subject-line">
    Subject: Acceptance of Resignation
</div>

<p class="body-para">Dear {{ $empName }},</p>

<p class="body-para">
    We acknowledge receipt of your resignation letter and wish to inform you that
    <strong>Probim LLC</strong> has formally accepted your resignation from the position
    of <strong>{{ $designation }}</strong> in the <strong>{{ $department }}</strong> department,
    effective as per the notice period terms outlined below.
</p>

<div class="info-block">
    <table>
        <tr>
            <td class="ib-label">Employee Name</td>
            <td class="ib-val">{{ $empName }}</td>
        </tr>
        <tr>
            <td class="ib-label">Designation</td>
            <td class="ib-val">{{ $designation }}</td>
        </tr>
        <tr>
            <td class="ib-label">Department</td>
            <td class="ib-val">{{ $department }}</td>
        </tr>
        <tr>
            <td class="ib-label">Notice Period</td>
            <td class="ib-val">{{ $noticePeriodDays }} day(s)</td>
        </tr>
        <tr>
            <td class="ib-label">Notice Start Date</td>
            <td class="ib-val">{{ $noticeStartDate }}</td>
        </tr>
        <tr>
            <td class="ib-label">Last Working Day</td>
            <td class="ib-val">{{ $lastWorkingDay }}</td>
        </tr>
    </table>
</div>

<p class="body-para">
    You are required to complete the standard handover and clearance process
    before your last working day. The HR department will initiate the necessary
    offboarding procedures, including settlement of dues and return of company assets.
</p>

<p class="body-para">
    We appreciate the contributions you have made during your tenure at Probim LLC
    and wish you all the best in your future endeavours.
</p>

<div class="sig-section">
    <div class="sig-label">Authorised Signatory</div>
    <div class="sig-name">{{ $managerName ?: 'HR Department' }}</div>
    <div class="sig-title">{{ $managerDesig }} · Probim LLC</div>
</div>

<div class="footer">
    Probim LLC · This is a system-generated document and does not require a physical signature.
</div>

</body>
</html>
