<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectCostExport implements WithMultipleSheets
{
    use Exportable;

    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function sheets(): array
    {
        return [
            new ProjectCostSummarySheet($this->reportData),
            new ProjectCostEmployeeSheet($this->reportData),
            new ProjectCostDailySheet($this->reportData),
        ];
    }
}

class ProjectCostSummarySheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Project Summary';
    }

    public function headings(): array
    {
        return [
            'Project ID',
            'Project Name',
            'Employees',
            'Total Hours',
            'Cost (AED)',
            'Budget (AED)',
            'Variance (AED)',
            'Status',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $projects = $this->reportData['projects'] ?? [];

        foreach ($projects as $project) {
            $rows[] = [
                $project['id'],
                $project['name'],
                $project['employees_count'],
                $project['total_hours_formatted'],
                $project['total_cost'],
                $project['budget'] ?? 'No budget set',
                $project['variance'] ?? '-',
                ucfirst($project['status'] ?? 'Active'),
            ];
        }

        // Summary row
        $summary = $this->reportData['summary'] ?? [];
        if (!empty($summary)) {
            $rows[] = [
                'TOTAL',
                'TOTAL SUMMARY',
                $summary['total_employees'] ?? 0,
                $summary['total_hours_formatted'] ?? '0h',
                $summary['total_cost'] ?? 0,
                $summary['total_budget'] ?? '-',
                $summary['total_variance'] ?? '-',
                '-',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->reportData['projects'] ?? []) + 2;

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

class ProjectCostEmployeeSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Employee Breakdown';
    }

    public function headings(): array
    {
        return [
            'Project Name',
            'Employee Code',
            'Employee Name',
            'Designation',
            'Hours Logged',
            'Cost (AED)',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $projects = $this->reportData['projects'] ?? [];

        foreach ($projects as $project) {
            foreach ($project['employees'] ?? [] as $emp) {
                $rows[] = [
                    $project['name'],
                    $emp['code'] ?? 'N/A',
                    $emp['name'],
                    $emp['designation'] ?? 'N/A',
                    $emp['total_hours_formatted'],
                    $emp['total_cost'],
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF3B82F6']],
            ],
        ];
    }
}

class ProjectCostDailySheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Daily Hours Log';
    }

    public function headings(): array
    {
        return [
            'Project Name',
            'Employee Code',
            'Employee Name',
            'Date',
            'Hours',
            'Attendance Timezone',
            'Cost (AED)',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $projects = $this->reportData['projects'] ?? [];

        foreach ($projects as $project) {
            foreach ($project['employees'] ?? [] as $emp) {
                foreach ($emp['daily_hours'] ?? [] as $daily) {
                    $rows[] = [
                        $project['name'],
                        $emp['code'] ?? 'N/A',
                        $emp['name'],
                        $daily['date'],
                        $daily['formatted_hours'],
                        $daily['timezone'] ?? 'Default',
                        $daily['cost'],
                    ];
                }
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF6366F1']],
            ],
        ];
    }
}

