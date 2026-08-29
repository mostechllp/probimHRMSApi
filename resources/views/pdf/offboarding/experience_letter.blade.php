<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Experience Letter</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 20mm; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.6;
            color: #1a1a2e;
            margin: 0; padding: 0;
        }

        /* ── Header bar ── */
        .header-bar { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header-bar td { border: none; vertical-align: middle; }
        .company-name {
            font-size: 16pt; font-weight: bold;
            color: #0f3460; text-transform: uppercase; letter-spacing: 1px;
        }
        .company-sub { font-size: 8pt; color: #555; margin-top: 2px; }
        .doc-label {
            text-align: right;
            font-size: 13pt; font-weight: bold;
            color: #0f3460; letter-spacing: 2px; text-transform: uppercase;
        }
        .header-rule { border: none; border-top: 3px solid #0f3460; margin: 0 0 14px 0; }

        /* ── Meta row ── */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 9pt; }
        .meta-table td { border: none; padding: 1px 0; }
        .meta-label { color: #555; width: 140px; }
        .meta-val   { font-weight: bold; color: #1a1a2e; }
        .meta-right { text-align: right; }

        /* ── Body ── */
        .salutation { margin-bottom: 10px; }
        .body-para  { margin-bottom: 12px; text-align: justify; }

        /* ── Highlight block ── */
        .info-block {
            border-left: 4px solid #0f3460;
            background: #f0f4ff;
            padding: 8px 14px;
            margin: 16px 0;
        }
        .info-block table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .info-block table td { padding: 3px 0; border: none; }
        .ib-label { color: #555; width: 160px; }
        .ib-val   { font-weight: bold; }

        /* ── Signature ── */
        .sig-section { margin-top: 36px; }
        .sig-label   { font-size: 9pt; color: #555; }
        .sig-name    { font-weight: bold; margin-top: 28px; font-size: 10pt; }
        .sig-title   { font-size: 9pt; color: #555; }

        /* ── Footer ── */
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
    $employee       = $offboarding->employee;
    $user           = $employee?->user;
    $empName        = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $designation    = $user?->designation?->name ?? 'N/A';
    $department     = $user?->department?->name ?? 'N/A';
    $joiningDate    = $employee->joining_date
        ? \Carbon\Carbon::parse($employee->joining_date)->format('d M Y')
        : 'N/A';
    $lastWorkingDay = $offboarding->last_working_day
        ? \Carbon\Carbon::parse($offboarding->last_working_day)->format('d M Y')
        : 'N/A';
    $issueDate      = now()->format('d M Y');
    $refNo          = 'EXP/' . now()->format('Y') . '/' . str_pad($offboarding->id, 4, '0', STR_PAD_LEFT);

    $managerEmployee = $offboarding->reportingManager;
    $managerName     = trim(($managerEmployee?->first_name ?? '') . ' ' . ($managerEmployee?->last_name ?? ''));
    $managerDesig    = $managerEmployee?->user?->designation?->name ?? 'HR Manager';
@endphp

<!-- ============================================================
     HEADER
     ============================================================ -->
<table class="header-bar">
    <tr>
        <td>
            <div class="company-name">Probim LLC</div>
            <div class="company-sub">HR &amp; Payroll Services · Dubai, UAE</div>
        </td>
        <td class="doc-label">Experience Letter</td>
    </tr>
</table>
<hr class="header-rule">

<!-- ============================================================
     META
     ============================================================ -->
<table class="meta-table">
    <tr>
        <td class="meta-label">Reference No.</td>
        <td class="meta-val">{{ $refNo }}</td>
        <td class="meta-right">Date: <strong>{{ $issueDate }}</strong></td>
    </tr>
</table>

<!-- ============================================================
     SALUTATION
     ============================================================ -->
<p class="salutation">To Whom It May Concern,</p>

<!-- ============================================================
     BODY
     ============================================================ -->
<p class="body-para">
    This is to certify that <strong>{{ $empName }}</strong> was employed with
    <strong>Probim LLC</strong> and has served the organisation in the capacity
    mentioned below.
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
            <td class="ib-label">Date of Joining</td>
            <td class="ib-val">{{ $joiningDate }}</td>
        </tr>
        <tr>
            <td class="ib-label">Last Working Day</td>
            <td class="ib-val">{{ $lastWorkingDay }}</td>
        </tr>
    </table>
</div>

<p class="body-para">
    During their tenure, <strong>{{ $empName }}</strong> demonstrated
    professionalism, dedication, and a strong work ethic. We wish them great
    success in all future endeavours.
</p>

<p class="body-para">
    This letter is issued upon request and is for information purposes only.
</p>

<!-- ============================================================
     SIGNATURE
     ============================================================ -->
<div class="sig-section">
    <div class="sig-label">Authorised Signatory</div>
    <div class="sig-name">{{ $managerName ?: 'HR Department' }}</div>
    <div class="sig-title">{{ $managerDesig }} · Probim LLC</div>
</div>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<div class="footer">
    Probim LLC · This is a system-generated document and does not require a physical signature.
</div>

</body>
</html>
