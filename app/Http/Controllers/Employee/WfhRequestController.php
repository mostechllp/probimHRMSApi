<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\WfhRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WfhRequestController extends Controller
{
    public function index()
    {
        $employee = Auth::guard('employee')->user();
        $requests = WfhRequest::where('employee_id', $employee->id)->latest()->get();
        return view('employee.wfh_requests.index', compact('requests'));
    }

    public function create()
    {
        return view('employee.wfh_requests.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $employee = Auth::guard('employee')->user();

        WfhRequest::create([
            'employee_id' => $employee->id,
            'date' => $request->date,
            'reason' => $request->reason,
            'notes' => $request->notes
        ]);

        return redirect()->route('employee.wfh.index')->with('success', 'WFH Request submitted successfully.');
    }
}
