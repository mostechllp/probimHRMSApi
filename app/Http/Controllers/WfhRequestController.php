<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WfhRequest;
use Illuminate\Http\Request;

class WfhRequestController extends Controller
{
    public function index()
    {
        $requests = WfhRequest::with('employee')->latest()->get();
        return view('wfh_requests.index', compact('requests'));
    }

    public function updateStatus(Request $request, WfhRequest $wfhRequest)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected'
        ]);

        $wfhRequest->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status updated successfully.');
    }
}
