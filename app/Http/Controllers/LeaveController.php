<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\LeaveRequest;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $query = LeaveRequest::with(['employee', 'leaveType', 'approver']);

        if ($status) {
            $query->where('status', $status);
        }

        $leaveRequests = $query->latest()->paginate(15);

        return view('leaves.index', compact('leaveRequests', 'status'));
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_remark' => 'nullable|string'
        ]);

        $leaveRequest->update([
            'status' => $request->status,
            'admin_remark' => $request->admin_remark,
            'approved_by' => auth()->id()
        ]);

        return redirect()->back()->with('success', 'Leave request ' . $request->status . ' successfully.');
    }
}
