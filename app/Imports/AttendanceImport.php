<?php

namespace App\Imports;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $uploadId;

    public function __construct($uploadId = null)
    {
        $this->uploadId = $uploadId;
    }

    public function collection(Collection $rows)
    {
        $employees = Employee::with('user')->get()->pluck('user.company_id', 'employee_id');

        foreach ($rows as $row) {
            $employeeId = $row['employee_id'] ?? $row['id'] ?? $row['userid'] ?? null;
            $date = $row['date'] ?? $row['log_date'] ?? null;
            $punchIn = $row['punch_in'] ?? $row['check_in'] ?? $row['in'] ?? null;
            $punchOut = $row['punch_out'] ?? $row['check_out'] ?? $row['out'] ?? null;

            if (!$employeeId || !$date)
                continue;

            $companyId = $employees[$employeeId] ?? null;
            if (!$companyId)
                continue;

            try {
                $formattedDate = Carbon::parse($date)->format('Y-m-d');
                $formattedIn = $punchIn ? Carbon::parse($date . ' ' . $punchIn) : null;
                $formattedOut = $punchOut ? Carbon::parse($date . ' ' . $punchOut) : null;

                AttendanceLog::updateOrCreate(
                    [
                        'userid' => $employeeId,
                        'log_date' => $formattedDate,
                    ],
                    [
                        'company_id' => $companyId,
                        'punch_in' => $formattedIn,
                        'punch_out' => $formattedOut
                    ]
                );
            } catch (\Exception $e) {
                Log::error("Attendance Import Error for ID {$employeeId}: " . $e->getMessage());
            }
        }
    }
}
