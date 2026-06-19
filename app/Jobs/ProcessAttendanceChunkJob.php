<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\AttendanceUpload;
use Carbon\Carbon;

class ProcessAttendanceChunkJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;

    protected $lines;
    protected $uploadId;

    public function __construct($lines, $uploadId)
    {
        $this->lines = $lines;
        $this->uploadId = $uploadId;
    }

    public function handle()
    {
        $grouped = [];

        foreach ($this->lines as $line) {

            $parts = preg_split('/\s+/', $line);

            if (count($parts) < 3) continue;

            $userid = $parts[0];
            $timestamp = $parts[1] . ' ' . $parts[2];

            $date = Carbon::parse($timestamp)->format('Y-m-d');

            $key = $userid . '_' . $date;

            $grouped[$key][] = $timestamp;
        }

        $employees = Employee::with('user')
            ->get()
            ->pluck('user.company_id', 'employee_id');

        foreach ($grouped as $key => $timestamps) {

            sort($timestamps);

            [$userid, $date] = explode('_', $key);

            $punchIn = $timestamps[0];
            $punchOut = count($timestamps) > 1 ? end($timestamps) : null;

            $companyId = $employees[$userid] ?? null;

            if (!$companyId) continue;

            AttendanceLog::updateOrCreate(
                [
                    'userid' => $userid,
                    'log_date' => $date,
                ],
                [
                    'company_id' => $companyId,
                    'punch_in' => $punchIn,
                    'punch_out' => $punchOut
                ]
            );
        }

        // Update progress
        $upload = AttendanceUpload::find($this->uploadId);
        if ($upload) {
            $upload->increment('processed_records', count($this->lines));

            if ($upload->total_records > 0) {
                $progress = round(($upload->processed_records / $upload->total_records) * 100);
                $upload->update(['progress' => min($progress, 95)]);
            }

            if ($upload->processed_records == $upload->total_records) {
                $upload->update([
                    'status' => 'completed',
                    'progress' => 100
                ]);
            }
        }
    }
}
