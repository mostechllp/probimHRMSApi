<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Project ID',
            'Project Name',
            'Project Status',
            'Currency',
            'Planned Total Hours',
            'Planned Total Cost',
            'Actual Total Project Hours',
            'Actual Total Project Cost',
            'Total Employees',
            'Employee ID',
            'Employee Name',
            'Actual Total Employee Hours',
            'Actual Total Employee Cost',
            'Date',
            'Hours Worked'
        ];
    }

    public function title(): string
    {
        return 'Project Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF2196F3']],
            ],
        ];
    }
}
