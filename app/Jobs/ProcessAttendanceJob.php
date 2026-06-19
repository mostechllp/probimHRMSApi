<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AttendanceUpload;

class ProcessAttendanceJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    protected $uploadId;

    public function __construct($uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle()
    {
        $upload = AttendanceUpload::find($this->uploadId);

        if (!$upload) return;

        $upload->update([
            'status' => 'processing',
            'progress' => 5
        ]);

        $path = storage_path('app/private/' . $upload->file_path);

        $handle = fopen($path, "r");

        $chunk = [];
        $chunkSize = 200;
        $total = 0;

        while (($line = fgets($handle)) !== false) {

            $line = trim($line);
            if (!$line) continue;

            $chunk[] = $line;
            $total++;

            if (count($chunk) >= $chunkSize) {
                ProcessAttendanceChunkJob::dispatch($chunk, $this->uploadId);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            ProcessAttendanceChunkJob::dispatch($chunk, $this->uploadId);
        }

        fclose($handle);

        $upload->update([
            'total_records' => $total,
            'progress' => 40
        ]);

        
    }
}
