<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskReport;
use Illuminate\Http\Request;

class TaskReportController extends Controller
{
    public function index()
    {
        $reports = TaskReport::with('employee')->latest()->get();
        return view('task_reports.index', compact('reports'));
    }
}
