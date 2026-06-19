<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::latest()->get();
        return view('leaves.types', compact('leaveTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name',
            'status' => 'boolean'
        ]);

        LeaveType::create([
            'name' => $request->name,
            'status' => $request->has('status') ? $request->status : true,
        ]);

        return redirect()->back()->with('success', 'Leave type created successfully.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name,' . $leaveType->id,
            'status' => 'boolean'
        ]);

        $leaveType->update([
            'name' => $request->name,
            'status' => $request->has('status') ? $request->status : $leaveType->status,
        ]);

        return redirect()->back()->with('success', 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType)
    {
        if ($leaveType->leaveRequests()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete leave type that has associated requests.');
        }

        $leaveType->delete();
        return redirect()->back()->with('success', 'Leave type deleted successfully.');
    }

    public function updateStatus(Request $request, LeaveType $leaveType)
    {
        $leaveType->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
}
