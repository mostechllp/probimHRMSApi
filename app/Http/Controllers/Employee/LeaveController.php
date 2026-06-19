<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function index()
    {
        $employee = auth('employee')->user();
        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->latest()
            ->paginate(10);

        return view('employee.leaves.index', compact('leaveRequests'));
    }

    public function create()
    {
        $employee = auth('employee')->user();
        $leaveTypes = LeaveType::where('status', true)->get();

        // Calculate current balance
        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('duration_days');

        $remainingBalance = $employee->total_leaves_allocated - $leavesTaken;

        return view('employee.leaves.create', compact('leaveTypes', 'remainingBalance'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $leaveType = LeaveType::find($request->leave_type_id);

        // Manual validation for sick leave document if not already handled by JS/required attribute
        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return back()->withErrors(['document' => 'The medical certificate is required for sick leave.'])->withInput();
        }

        // Calculate duration days
        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $durationDays = $start->diffInDays($end) + 1;

        // Leave balance check
        $employee = auth('employee')->user();

        // Count already taken/pending leaves
        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('duration_days');

        $remainingBalance = $employee->total_leaves_allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return back()->withErrors(['error' => "Insufficient leave balance. You have only $remainingBalance days remaining."])->withInput();
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        LeaveRequest::create([
            'employee_id' => auth('employee')->id(),
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary,
            'document' => $documentPath,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()->route('employee.leaves.index')->with('success', 'Leave request submitted successfully.');
    }
}
