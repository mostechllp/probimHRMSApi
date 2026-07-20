<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            background: #fff;
            padding: 10px 30px;
        }

        /* ── Header ─────────────────────────────────────────── */
        .report-header {
            padding: 18px 0 10px;
            border-bottom: 2px solid #2E7D32;
            margin-bottom: 14px;
        }

        .report-title {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .report-meta {
            font-size: 10px;
            color: #555;
            line-height: 1.8;
        }

        .report-meta span {
            display: block;
        }

        .report-summary {
            margin-top: 8px;
            font-size: 10.5px;
            font-weight: bold;
            color: #2E7D32;
        }

        /* ── Divider ─────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #ccc;
            margin: 14px 0;
        }

        /* ── Table ───────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        thead tr {
            background-color: #1B5E20;  /* deep green */
            color: #ffffff;
        }

        thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.3px;
            border: none;
        }

        tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        tbody tr:nth-child(even) {
            background-color: #f7f9f7;
        }

        tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        tbody tr:hover {
            background-color: #e8f5e9;
        }

        tbody td {
            padding: 6px 8px;
            font-size: 9.5px;
            color: #333;
            vertical-align: middle;
            border: none;
        }

        /* Status badges (text only in PDF) */
        td.status-full-day   { color: #1B5E20; font-weight: bold; }
        td.status-half-day   { color: #E65100; font-weight: bold; }
        td.status-present    { color: #2E7D32; font-weight: bold; }
        td.status-absent     { color: #B71C1C; font-weight: bold; }

        /* ── Footer ──────────────────────────────────────────── */
        .report-footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            font-size: 9px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── Header ─────────────────────────────── --}}
    <div class="report-header">
        <div class="report-title">{{ $title }}</div>
        <div class="report-meta">
            <span>Generated: {{ now()->format('n/j/Y, g:i:s A') }}</span>
            @if(!empty($period))
                <span>Period: {{ $period }}</span>
            @endif
        </div>
        @if(!empty($summary))
            <div class="report-summary">{{ $summary }}</div>
        @endif
    </div>

    <hr class="divider">

    {{-- ── Table ───────────────────────────────── --}}
    <table>
        <thead>
            <tr>
                @foreach($headings as $i => $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    @foreach($row as $i => $cell)
                        @php
                            $statusClass = '';
                            if ($i === count($row) - 1) {
                                $lower = strtolower($cell);
                                if ($lower === 'full day')   $statusClass = 'status-full-day';
                                elseif ($lower === 'half day') $statusClass = 'status-half-day';
                                elseif ($lower === 'present') $statusClass = 'status-present';
                                elseif ($lower === 'absent')  $statusClass = 'status-absent';
                            }
                        @endphp
                        <td class="{{ $statusClass }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}" style="text-align:center; color:#999; padding:20px;">
                        No records found for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Footer ─────────────────────────────── --}}
    <div class="report-footer">
        &copy; {{ date('Y') }} Probim HRMS. All rights reserved.
    </div>

</body>
</html>
