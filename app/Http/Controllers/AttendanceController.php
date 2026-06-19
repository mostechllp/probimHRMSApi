<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AttendanceParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AttendanceUpload;
use App\Jobs\ProcessAttendanceJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    protected $parser;

    public function __construct(AttendanceParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Display a listing of attendance summary.
     */
    public function index(Request $request)
    {
        $companies = Company::all();

        $perPage = $request->get('per_page', 100);

        $query = AttendanceLog::with(['company', 'user'])
            ->select(
                'company_id',
                'userid',
                DB::raw("log_date as date"),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(punch_in)) <= '12:00:00' 
                            THEN MIN(punch_in) 
                            ELSE NULL
                        END as punch_in
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MAX(punch_out)) >= '12:00:00'
                            THEN MAX(punch_out) 
                            ELSE NULL
                        END as punch_out
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(punch_in)) >= '08:11:00' 
                                AND TIME(MIN(punch_in)) <= '12:00:00' 
                            THEN 'Late Comer' 
                            ELSE 'On Time' 
                        END as status
                    ")
            );

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name (searching through the user relationship - now linked to Employee model)
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        // Filter by Date
        if ($request->filled('date_preset')) {
            $now = Carbon::now();
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('punch_in', $now->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('punch_in', $now->subDay()->toDateString());
                    break;
                case 'last_week':
                    $query->whereBetween('punch_in', [$now->subWeek()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'last_month':
                    $query->whereBetween('punch_in', [$now->subMonth()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $query->whereBetween('log_date', [
                            Carbon::parse($request->from_date)->startOfDay(),
                            Carbon::parse($request->to_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->orderBy('date', 'desc')
            ->orderBy('punch_in', 'desc')
            ->paginate($perPage);

        // Stats for cards
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $activeEmployees = Employee::count();
        $inactiveEmployees = Employee::onlyInactive()->count();
        $totalEmployees = $activeEmployees + $inactiveEmployees;

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $yesterdaysLogs = AttendanceLog::whereDate('log_date', $yesterday)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            return Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
        })->count();

        $punchedLate = $todayLogs->filter(function ($log) {

            if (!$log->punch_in)
                return false;

            $time = substr($log->punch_in, 11, 8);

            return $time >= '08:11:00' && $time <= '12:00:00';
        })->count();

        $presentToday = $todayLogs->count();

        $absentToday = $activeEmployees - $presentToday;

        $punchedOutCount = $todayLogs->filter(function ($log) {
            return Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00';
        })->count();

        $punchedOutYesterday = $yesterdaysLogs->filter(function ($log) {
            return Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00';
        })->count();

        $stats = [
            'total' => $totalEmployees,
            'active' => $activeEmployees,
            'inactive' => $inactiveEmployees,
            'punched_in' => $punchedInCount,
            'punched_out' => $punchedOutCount,
            'punched_late' => $punchedLate,
            'absent_today' => $absentToday,
            'punch_out_yesterday' => $punchedOutYesterday
        ];

        return view('attendance.index', compact('attendance', 'companies', 'stats'));
    }


    public function indexPunchInToday(Request $request)
    {
        $companies = Company::all();

        $perPage = $request->get('per_page', 15);

        $query = AttendanceLog::with(['company', 'user'])
            ->select(
                'company_id',
                'userid',
                DB::raw("DATE(timestamp) as date"),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN MIN(timestamp) 
                            ELSE NULL
                        END as punch_in
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MAX(timestamp)) >= '12:00:00'
                            THEN MAX(timestamp) 
                            ELSE NULL
                        END as punch_out
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) >= '08:11:00' 
                                AND TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN 'Late Comer' 
                            ELSE 'On Time' 
                        END as status
                    ")
            );

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name (searching through the user relationship - now linked to Employee model)
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        // Filter by Date
        if ($request->filled('date_preset')) {
            $now = Carbon::now();
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('timestamp', $now->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('timestamp', $now->subDay()->toDateString());
                    break;
                case 'last_week':
                    $query->whereBetween('timestamp', [$now->subWeek()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'last_month':
                    $query->whereBetween('timestamp', [$now->subMonth()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $query->whereBetween('timestamp', [
                            Carbon::parse($request->from_date)->startOfDay(),
                            Carbon::parse($request->to_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        $today = Carbon::today()->toDateString();

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->orderBy('date', 'desc')
            ->orderBy('punch_in', 'desc')
            ->whereDate('timestamp', $today)
            ->get();

        // Stats for cards

        $todayLogs = AttendanceLog::whereDate('timestamp', $today)
            ->select('userid', DB::raw('MIN(timestamp) as punch_in'), DB::raw('MAX(timestamp) as punch_out'))
            ->groupBy('userid')
            ->get();

        return view('attendance.punch-in-today', compact('attendance', 'companies'));
    }

    public function indexPunchInYesterday(Request $request)
    {
        $companies = Company::all();

        $perPage = $request->get('per_page', 15);

        $query = AttendanceLog::with(['company', 'user'])
            ->select(
                'company_id',
                'userid',
                DB::raw("DATE(timestamp) as date"),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN MIN(timestamp) 
                            ELSE NULL
                        END as punch_in
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MAX(timestamp)) >= '12:00:00'
                            THEN MAX(timestamp) 
                            ELSE NULL
                        END as punch_out
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) >= '08:11:00' 
                                AND TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN 'Late Comer' 
                            ELSE 'On Time' 
                        END as status
                    ")
            );

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name (searching through the user relationship - now linked to Employee model)
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        // Filter by Date
        if ($request->filled('date_preset')) {
            $now = Carbon::now();
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('timestamp', $now->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('timestamp', $now->subDay()->toDateString());
                    break;
                case 'last_week':
                    $query->whereBetween('timestamp', [$now->subWeek()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'last_month':
                    $query->whereBetween('timestamp', [$now->subMonth()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $query->whereBetween('timestamp', [
                            Carbon::parse($request->from_date)->startOfDay(),
                            Carbon::parse($request->to_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        $yesterday = Carbon::yesterday()->toDateString();

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->orderBy('date', 'desc')
            ->orderBy('punch_in', 'desc')
            ->whereDate('timestamp', $yesterday)
            ->get();

        // Stats for cards

        $todayLogs = AttendanceLog::whereDate('timestamp', $yesterday)
            ->select('userid', DB::raw('MIN(timestamp) as punch_in'), DB::raw('MAX(timestamp) as punch_out'))
            ->groupBy('userid')
            ->get();

        return view('attendance.punch-in-yesterday', compact('attendance', 'companies'));
    }

    public function indexPunchOutToday(Request $request)
    {
        $companies = Company::all();

        $perPage = $request->get('per_page', 15);

        $query = AttendanceLog::with(['company', 'user'])
            ->select(
                'company_id',
                'userid',
                DB::raw("DATE(timestamp) as date"),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN MIN(timestamp) 
                            ELSE NULL
                        END as punch_in
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MAX(timestamp)) >= '12:00:00'
                            THEN MAX(timestamp) 
                            ELSE NULL
                        END as punch_out
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) >= '08:11:00' 
                                AND TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN 'Late Comer' 
                            ELSE 'On Time' 
                        END as status
                    ")
            );

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name (searching through the user relationship - now linked to Employee model)
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        // Filter by Date
        if ($request->filled('date_preset')) {
            $now = Carbon::now();
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('timestamp', $now->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('timestamp', $now->subDay()->toDateString());
                    break;
                case 'last_week':
                    $query->whereBetween('timestamp', [$now->subWeek()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'last_month':
                    $query->whereBetween('timestamp', [$now->subMonth()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $query->whereBetween('timestamp', [
                            Carbon::parse($request->from_date)->startOfDay(),
                            Carbon::parse($request->to_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        $today = Carbon::today()->toDateString();

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->havingRaw("TIME(MAX(timestamp)) >= '12:00:00'")
            ->orderBy('date', 'desc')
            ->orderBy('punch_out', 'desc')
            ->whereDate('timestamp', $today)
            ->get();

        // Stats for cards

        $todayLogs = AttendanceLog::whereDate('timestamp', $today)
            ->select('userid', DB::raw('MIN(timestamp) as punch_in'), DB::raw('MAX(timestamp) as punch_out'))
            ->groupBy('userid')
            ->get();

        return view('attendance.punch-out-today', compact('attendance', 'companies'));
    }

    public function indexPunchOutYesterday(Request $request)
    {
        $companies = Company::all();

        $perPage = $request->get('per_page', 15);

        $query = AttendanceLog::with(['company', 'user'])
            ->select(
                'company_id',
                'userid',
                DB::raw("DATE(timestamp) as date"),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN MIN(timestamp) 
                            ELSE NULL
                        END as punch_in
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MAX(timestamp)) >= '12:00:00'
                            THEN MAX(timestamp) 
                            ELSE NULL
                        END as punch_out
                    "),

                DB::raw("
                        CASE 
                            WHEN TIME(MIN(timestamp)) >= '08:11:00' 
                                AND TIME(MIN(timestamp)) <= '12:00:00' 
                            THEN 'Late Comer' 
                            ELSE 'On Time' 
                        END as status
                    ")
            );

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name (searching through the user relationship - now linked to Employee model)
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        // Filter by Date
        if ($request->filled('date_preset')) {
            $now = Carbon::now();
            switch ($request->date_preset) {
                case 'today':
                    $query->whereDate('timestamp', $now->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('timestamp', $now->subDay()->toDateString());
                    break;
                case 'last_week':
                    $query->whereBetween('timestamp', [$now->subWeek()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'last_month':
                    $query->whereBetween('timestamp', [$now->subMonth()->startOfDay(), Carbon::now()->endOfDay()]);
                    break;
                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $query->whereBetween('timestamp', [
                            Carbon::parse($request->from_date)->startOfDay(),
                            Carbon::parse($request->to_date)->endOfDay()
                        ]);
                    }
                    break;
            }
        }

        $yesterday = Carbon::yesterday()->toDateString();

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->havingRaw("TIME(MAX(timestamp)) >= '12:00:00'")
            ->orderBy('date', 'desc')
            ->orderBy('punch_out', 'desc')
            ->whereDate('timestamp', $yesterday)
            ->get();

        // Stats for cards

        $todayLogs = AttendanceLog::whereDate('timestamp', $yesterday)
            ->select('userid', DB::raw('MIN(timestamp) as punch_in'), DB::raw('MAX(timestamp) as punch_out'))
            ->groupBy('userid')
            ->get();

        return view('attendance.punch-out-yesterday', compact('attendance', 'companies'));
    }

    public function lateAttendance(Request $request)
    {
        $companies = Company::all();
        $perPage = $request->get('per_page', 15);

        $now = Carbon::now();

        $fromDate = Carbon::yesterday()->startOfDay();
        $toDate = Carbon::yesterday()->endOfDay();

        if ($request->filled('date_preset')) {
            switch ($request->date_preset) {
                case 'today':
                    $fromDate = $now->copy()->startOfDay();
                    $toDate = $now->copy()->endOfDay();
                    break;

                case 'yesterday':
                    $fromDate = $now->copy()->subDay()->startOfDay();
                    $toDate = $now->copy()->subDay()->endOfDay();
                    break;

                case 'last_week':
                    $fromDate = $now->copy()->subWeek()->startOfDay();
                    $toDate = Carbon::now()->endOfDay();
                    break;

                case 'last_month':
                    $fromDate = $now->copy()->subMonth()->startOfDay();
                    $toDate = Carbon::now()->endOfDay();
                    break;

                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $fromDate = Carbon::parse($request->from_date)->startOfDay();
                        $toDate = Carbon::parse($request->to_date)->endOfDay();
                    }
                    break;
            }
        }

        $query = AttendanceLog::with(['company', 'user'])
            ->whereBetween('timestamp', [$fromDate, $toDate])
            ->select(
                'company_id',
                'userid',
                DB::raw("DATE(timestamp) as date"),
                DB::raw("MIN(timestamp) as punch_in"),
                DB::raw("MAX(timestamp) as punch_out")
            );

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        $attendance = $query->groupBy('company_id', 'userid', 'date')
            ->havingRaw("TIME(MIN(timestamp)) BETWEEN '08:11:00' AND '12:00:00'")
            ->orderBy('date', 'desc')
            ->orderBy('punch_in', 'desc')
            ->get();

        return view('attendance.late', compact('attendance', 'companies'));
    }

    public function absentAttendance(Request $request)
    {
        $companies = Company::all();
        $perPage = $request->get('per_page', 15);

        $now = Carbon::now();

        $fromDate = Carbon::yesterday()->startOfDay();
        $toDate = Carbon::yesterday()->endOfDay();

        if ($request->filled('date_preset')) {
            switch ($request->date_preset) {
                case 'today':
                    $fromDate = $now->copy()->startOfDay();
                    $toDate = $now->copy()->endOfDay();
                    break;

                case 'yesterday':
                    $fromDate = $now->copy()->subDay()->startOfDay();
                    $toDate = $now->copy()->subDay()->endOfDay();
                    break;

                case 'last_week':
                    $fromDate = $now->copy()->subWeek()->startOfDay();
                    $toDate = Carbon::now()->endOfDay();
                    break;

                case 'last_month':
                    $fromDate = $now->copy()->subMonth()->startOfDay();
                    $toDate = Carbon::now()->endOfDay();
                    break;

                case 'custom':
                    if ($request->filled('from_date') && $request->filled('to_date')) {
                        $fromDate = Carbon::parse($request->from_date)->startOfDay();
                        $toDate = Carbon::parse($request->to_date)->endOfDay();
                    }
                    break;
            }
        }

        $presentUserIds = AttendanceLog::whereBetween('timestamp', [$fromDate, $toDate])
            ->pluck('userid')
            ->unique()
            ->toArray();

        $query = Employee::with(['company', 'department']);

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by Employee Name
        if ($request->filled('employee_name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->employee_name . '%');
            });
        }

        $attendance = $query->whereNotIn('employee_id', $presentUserIds) // adjust if needed
            ->get();

        return view('attendance.absent', compact('attendance', 'companies'));
    }

    /**
     * Show the form for uploading attendance.
     */
    public function create()
    {
        $companies = Company::all();
        return view('attendance.upload', compact('companies'));
    }

    /**
     * Store uploaded attendance logs.
     */
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'company_id' => 'required|exists:companies,id',
    //         'file' => 'required|file',
    //     ]);

    //     $companyId = $request->company_id;
    //     $file = $request->file('file');
    //     $content = file_get_contents($file->getRealPath());
    //     $extension = $file->getClientOriginalExtension();

    //     $parsedData = $this->parser->parse($content, $extension);

    //     // Group data by user and date to find first/last entries
    //     $grouped = collect($parsedData)->groupBy(function ($data) {
    //         return $data['userid'] . '_' . Carbon::parse($data['timestamp'])->format('Y-m-d');
    //     });

    //     $processed = 0;
    //     $skipped = 0;

    //     // foreach ($grouped as $logs) {
    //     //     $sorted = $logs->sortBy('timestamp');
    //     //     $first = $sorted->first();
    //     //     $last = $sorted->last();

    //     //     try {
    //     //         // Store Punch In (First Entry)
    //     //         AttendanceLog::firstOrCreate([
    //     //             'company_id' => $companyId,
    //     //             'userid' => $first['userid'],
    //     //             'punch_in' => $first['timestamp'],
    //     //             'punch_out' => $last['timestamp'],
    //     //         ], [
    //     //             'status' => $first['status'],
    //     //             'device_id' => $first['device_id'],
    //     //         ]);
    //     //         $processed++;

    //     // Store Punch Out (Last Entry if different)
    //     // if ($first['timestamp'] != $last['timestamp']) {
    //     //     AttendanceLog::firstOrCreate([
    //     //         'company_id' => $companyId,
    //     //         'userid' => $last['userid'],
    //     //         'timestamp' => $last['timestamp'],
    //     //     ], [
    //     //         'status' => $last['status'],
    //     //         'device_id' => $last['device_id'],
    //     //     ]);
    //     //     $processed++;
    //     // }
    //     //     } catch (\Exception $e) {
    //     //         $skipped++;
    //     //     }
    //     // }

    //     foreach ($grouped as $logs) {
    //         $sorted = $logs->sortBy('timestamp');
    //         $first = $sorted->first();
    //         $last = $sorted->last();

    //         $date = Carbon::parse($first['timestamp'])->format('Y-m-d');

    //         $punchIn = $first['timestamp'];
    //         $punchOut = ($first['timestamp'] != $last['timestamp'])
    //             ? $last['timestamp']
    //             : null;

    //         try {
    //             AttendanceLog::updateOrCreate(
    //                 [
    //                     'company_id' => $companyId,
    //                     'userid' => $first['userid'],
    //                     'date' => $date,
    //                 ],
    //                 [
    //                     'punch_in' => $punchIn,
    //                     'punch_out' => $punchOut,
    //                     'status' => $first['status'] ?? null,
    //                     'device_id' => $first['device_id'] ?? null,
    //                 ]
    //             );

    //             $processed++;
    //         } catch (\Exception $e) {
    //             $skipped++;
    //         }
    //     }

    //     // Trigger Late/Absent Checks
    //     $this->notifyHRAboutLatecomers($companyId);
    //     $this->notifyHRAboutAbsentees($companyId);

    //     return redirect()->route('attendance.index')->with('success', "Processed $processed records from " . count($grouped) . " daily logs.");
    // }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:dat,csv,txt|max:2048',
            'company_id' => 'required|exists:companies,id'
        ]);

        try {

            $path = $request->file('file')->store('attendance');

            $upload = AttendanceUpload::create([
                'file_path' => $path,
                'company_id' => $request->company_id, 
                'status' => 'pending',
                'progress' => 0
            ]);

            ProcessAttendanceJob::dispatch($upload->id, $request->company_id);

            return response()->json([
                'success' => true,
                'upload_id' => $upload->id
            ]);
        } catch (\Exception $e) {

            Log::error($e);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed'
            ], 500);
        }
    }

    public function progress($id)
    {
        $upload = AttendanceUpload::find($id);

        if (!$upload) {
            return response()->json([
                'status' => 'not_found',
                'progress' => 0
            ], 404);
        }

        return response()->json($upload);
    }

    private function notifyHRAboutLatecomers($companyId)
    {
        $hrUsers = \App\Models\User::all(); // Notify all admins
        $monthStart = Carbon::now()->startOfMonth();

        $lateEmployees = AttendanceLog::where('company_id', $companyId)
            ->whereBetween('punch_in', [$monthStart, Carbon::now()])
            ->whereRaw("TIME(punch_in) >= '08:11:00' AND TIME(punch_in) <= '12:00:00'")
            ->select('userid', DB::raw('count(*) as late_count'))
            ->groupBy('userid')
            ->having('late_count', '>=', 3)
            ->get();

        foreach ($lateEmployees as $lateRecord) {
            $employee = Employee::find($lateRecord->userid);
            if ($employee) {
                foreach ($hrUsers as $user) {
                    $user->notify(new \App\Notifications\LateWarningNotification($employee, $lateRecord->late_count));
                }
            }
        }
    }

    private function notifyHRAboutAbsentees($companyId)
    {
        $today = Carbon::today()->toDateString();
        $hrUsers = \App\Models\User::all();

        $activeEmployees = Employee::where('company_id', $companyId)
            ->get();

        foreach ($activeEmployees as $employee) {
            $punchedIn = AttendanceLog::where('userid', $employee->id)
                ->whereDate('log_date', $today)
                ->exists();

            if (!$punchedIn) {
                foreach ($hrUsers as $user) {
                    $user->notify(new \App\Notifications\AbsentNotification($employee));
                }
            }
        }
    }
}
