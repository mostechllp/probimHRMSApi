<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\AttendanceUpload;
use App\Jobs\ProcessAttendanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class AttendanceApiController extends ApiController
{
    /**
     * Get Attendance Summary with Stats
     */
    // public function index(Request $request): JsonResponse
    // {
    //     $perPage = $request->get('per_page', 15);
    //     $companyId = $request->get('company_id');
    //     $employeeName = $request->get('employee_name');
    //     $datePreset = $request->get('date_preset', 'all');

    //     $query = AttendanceLog::with(['company', 'user'])
    //         ->select(
    //             'company_id',
    //             'userid',
    //             'log_date',
    //             DB::raw("MIN(punch_in) as punch_in"),
    //             DB::raw("MAX(punch_out) as punch_out")
    //         );

    //     if ($companyId) {
    //         $query->where('company_id', $companyId);
    //     }

    //     if ($employeeName) {
    //         $query->whereHas('user', function ($q) use ($employeeName) {
    //             $q->where('first_name', 'like', "%$employeeName%")
    //                 ->orWhere('last_name', 'like', "%$employeeName%");
    //         });
    //     }

    //     if ($datePreset != 'all') {
    //         $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));
    //     }


    //     $attendance = $query->groupBy('company_id', 'userid', 'log_date')
    //         ->orderBy('log_date', 'desc')
    //         ->paginate($perPage);

    //     return $this->success([
    //         'attendance' => $attendance,
    //         'stats' => $this->getStats()
    //     ]);
    // }
    
     public function index(Request $request): JsonResponse
    {
        $perPage = 100;
        $companyId = $request->get('company_id');
        $employeeName = $request->get('employee_name');
        $datePreset = $request->get('date_preset', 'all');

        $query = AttendanceLog::with(['company', 'user.employee', 'user.department'])
            ->select(
                'id',
                'company_id',
                'userid',
                'log_date',
                'working_hours',
                'punch_in_latitude',
                'punch_in_longitude',
                'punch_in_address',
                'punch_out_latitude',
                'punch_out_longitude',
                'punch_out_address',
                DB::raw("MIN(punch_in) as punch_in"),
                DB::raw("MAX(punch_out) as punch_out")
            );

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($employeeName) {
            $query->whereHas('user.employee', function ($q) use ($employeeName) {
                $q->where('first_name', 'like', "%$employeeName%")
                    ->orWhere('last_name', 'like', "%$employeeName%");
            });
        }

        if ($datePreset != 'all') {
            $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));
        }


        $attendance = $query->groupBy(
            'id',
            'company_id',
            'userid',
            'log_date',
            'working_hours',
            'punch_in_latitude',
            'punch_in_longitude',
            'punch_in_address',
            'punch_out_latitude',
            'punch_out_longitude',
            'punch_out_address',
        )
            ->orderBy('log_date', 'desc')
            ->paginate($perPage);

        $attendance->getCollection()->transform(function ($log) {
            $tz = config('app.timezone', 'Asia/Dubai');

            // Format date to dd/mm/yyyy
            $log->log_date = $log->log_date
                ? Carbon::parse($log->log_date)->format('d/m/Y')
                : '--';

            $log->punch_in = $log->punch_in
                ? Carbon::parse($log->punch_in)->setTimezone($tz)->format('h:i A')
                : '--';
            // Output → "08 Jun 2026, 07:29 AM"

            $log->punch_out = $log->punch_out
                ? Carbon::parse($log->punch_out)->setTimezone($tz)->format('h:i A')
                : '--';
            // Output → "08 Jun 2026, 12:32 PM"

            // Format working_hours → "8 hrs 30 mins"
            $minutes = $log->working_hours ?? 0;
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            if ($minutes == 0)
                $log->working_hours = '--';
            elseif ($hours == 0)
                $log->working_hours = "{$mins} mins";
            elseif ($mins == 0)
                $log->working_hours = "{$hours} hrs";
            else
                $log->working_hours = "{$hours} hrs {$mins} mins";

            return $log;
        });

        return $this->success([
            'attendance' => $attendance,
            'stats' => $this->getStats()
        ]);
    }

    /**
     * Upload Attendance File
     */

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:dat,csv,txt|max:2048',
            // 'company_id' => 'required|exists:companies,id'
        ]);

        try {
            $file = $request->file('file');

            // Ensure directory exists
            if (!Storage::disk('private')->exists('attendance')) {
                Storage::disk('private')->makeDirectory('attendance');
            }

            // Store file
            $path = $file->store('attendance', 'private');

            // Double check file actually exists
            if (!Storage::disk('private')->exists($path)) {
                Log::error('File not stored properly: ' . $path);
                return response()->json([
                    'message' => 'File upload failed'
                ], 500);
            }

            // Save in DB
            $upload = AttendanceUpload::create([
                'file_path' => $path,
                'status' => 'pending',
                'progress' => 0
            ]);

            $extension = $file->getClientOriginalExtension();

            if (in_array($extension, ['xlsx', 'csv'])) {
                // Direct import for Excel/CSV
                Excel::import(new \App\Imports\AttendanceImport($upload->id), $path, 'private');
                $upload->update(['status' => 'completed', 'progress' => 100]);

                return $this->success($upload, 'Attendance imported successfully');
            } 
            // Dispatch background job for .dat/.txt
            ProcessAttendanceJob::dispatch($upload->id);

            return $this->success($upload, 'Attendance file uploaded and processing started');
        } catch (\Exception $e) {
            Log::error('Upload Error: ' . $e->getMessage());
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check Upload Progress
     */
    public function uploadStatus($id): JsonResponse
    {
        $upload = AttendanceUpload::find($id);
        if (!$upload)
            return $this->error('Upload record not found', 404);
        return $this->success($upload);
    }

    /**
     * Get Punch-In Today
     */
    public function punchInToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_in');
    }

    /**
     * Get Punch-In Yesterday
     */
    public function punchInYesterday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'yesterday', 'punch_in');
    }

    /**
     * Get Punch-Out Today
     */
    public function punchOutToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_out');
    }

    /**
     * Get Late Comers
     */
    public function lateComers(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $datePreset = $request->get('date_preset', 'today');

        $query = AttendanceLog::with(['company', 'user']);
        $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));

        $query->select(
            'company_id',
            'userid',
            'log_date',
            DB::raw("MIN(punch_in) as punch_in"),
            DB::raw("MAX(punch_out) as punch_out")
        )
            ->groupBy('company_id', 'userid', 'log_date')
            ->havingRaw("TIME(MIN(punch_in)) > '08:10:59' AND TIME(MIN(punch_in)) <= '12:00:00'");

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return $this->success($query->paginate($perPage));
    }

    /**
     * Get Absentees
     */
    public function absentees(Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $companyId = $request->get('company_id');

        $presentUserIds = AttendanceLog::whereDate('log_date', $date)
            ->pluck('userid')
            ->unique();

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->whereNotIn('employee_id', $presentUserIds)
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
                $q->where('type', '!=', 'admin');
            });

        if ($companyId) {
            $query->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $this->success($query->paginate($request->get('per_page', 15)));
    }

    /**
     * Private Helpers
     */

    private function getFilteredAttendance(Request $request, $day, $type): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $date = ($day === 'today') ? Carbon::today()->toDateString() : Carbon::yesterday()->toDateString();

        $query = AttendanceLog::with(['company', 'user'])
            ->whereDate('log_date', $date)
            ->select(
                'company_id',
                'userid',
                'log_date',
                DB::raw("MIN(punch_in) as punch_in"),
                DB::raw("MAX(punch_out) as punch_out")
            );

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($type === 'punch_out') {
            $query->havingRaw("TIME(MAX(punch_out)) >= '12:00:00'");
        } else {
            $query->havingRaw("TIME(MIN(punch_in)) <= '12:00:00'");
        }

        return $this->success($query->groupBy('company_id', 'userid', 'log_date')->paginate($perPage));
    }

    private function applyDateFilter($query, $preset, $from = null, $to = null)
    {
        $now = Carbon::now();
        switch ($preset) {
            case 'today':
                $query->whereDate('log_date', $now->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('log_date', $now->subDay()->toDateString());
                break;
            case 'last_week':
                $query->whereBetween('log_date', [$now->subWeek()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'last_month':
                $query->whereBetween('log_date', [$now->subMonth()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('log_date', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay()
                    ]);
                }
                break;
        }
    }

    private function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $activeEmployeesCount = User::where('status', 'active')->count();

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            return $log->punch_in && Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
        })->count();

        $punchedLateCount = $todayLogs->filter(function ($log) {
            if (!$log->punch_in)
                return false;
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            return $time > '08:10:59' && $time <= '12:00:00';
        })->count();

        return [
            'total_active_employees' => $activeEmployeesCount,
            'present_today' => $todayLogs->count(),
            'absent_today' => max(0, $activeEmployeesCount - $todayLogs->count()),
            'punched_in_on_time' => $punchedInCount - $punchedLateCount,
            'punched_late' => $punchedLateCount,
            'punched_out_today' => $todayLogs->filter(fn($l) => $l->punch_out && Carbon::parse($l->punch_out)->format('H:i:s') >= '12:00:00')->count()
        ];
    }
}
