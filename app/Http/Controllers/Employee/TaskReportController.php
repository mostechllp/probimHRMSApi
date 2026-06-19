<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskReportController extends Controller
{
    public function index()
    {
        $employee = Auth::guard('employee')->user();
        $reports = TaskReport::where('employee_id', $employee->id)->latest()->get();
        return view('employee.task_reports.index', compact('reports'));
    }
}
