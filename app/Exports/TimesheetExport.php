<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    use Exportable;

    protected array $timesheetData;

    public function __construct(array $timesheetData)
    {
        $this->timesheetData = $timesheetData;
    }

    public function title(): string
    {
        return 'Timesheets and Hours';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Employee Code',
            'Employee Name',
            'Line Manager',
            'Designation',
            'Project Name',
            'Date',
            'Hours Worked',
            'Status',
            'Description',
            'Approved/Rejected By',
            'Approved/Rejected At',
            'Rejection Reason',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $items = $this->timesheetData['timesheets'] ?? [];

        foreach ($items as $item) {
            $rows[] = [
                $item['id'],
                $item['employee']['code'] ?? 'N/A',
                $item['employee']['name'] ?? 'N/A',
                $item['employee']['manager_name'] ?? 'N/A',
                $item['employee']['designation'] ?? 'N/A',
                $item['project']['name'] ?? 'N/A',
                $item['date'],
                $item['hours_formatted'],
                $item['status_label'],
                $item['description'] ?? '-',
                $item['approver']['name'] ?? '-',
                $item['approver']['approved_at'] ?? '-',
                $item['approver']['rejection_reason'] ?? '-',
            ];
        }

        // Summary row at bottom
        $kpis = $this->timesheetData['kpis'] ?? [];
        if (!empty($kpis)) {
            $rows[] = [
                'TOTAL',
                '-',
                'TOTAL HOURS: ' . ($kpis['total_logged_hours_formatted'] ?? '0h'),
                '-',
                '-',
                'APPROVED: ' . ($kpis['approved_hours_formatted'] ?? '0h'),
                'PENDING: ' . ($kpis['pending_approvals_count'] ?? 0),
                $kpis['total_logged_hours_formatted'] ?? '0h',
                '-',
                '-',
                '-',
                '-',
                '-',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $count = count($this->timesheetData['timesheets'] ?? []);
        $lastRow = $count + 2;

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF10B981']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF3F4F6']],
            ],
        ];
    }
}

