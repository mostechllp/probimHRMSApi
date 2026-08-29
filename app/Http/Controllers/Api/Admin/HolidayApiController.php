<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $holidays = Holiday::when($request->year, function ($query) use ($request) {
            $query->whereYear('holiday_date', $request->year);
        })
            ->when($request->month, function ($query) use ($request) {
                $query->whereMonth('holiday_date', $request->month);
            })
            ->orderBy('holiday_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $holidays
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string',
            'is_optional' => 'nullable|boolean',
        ]);

        $holiday = Holiday::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Holiday created successfully.',
            'data' => $holiday
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Holiday $holiday)
    {
        return response()->json([
            'success' => true,
            'data' => $holiday
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string',
            'is_optional' => 'nullable|boolean',
        ]);

        $holiday->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully.',
            'data' => $holiday
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday deleted successfully.'
        ]);
    }

}
