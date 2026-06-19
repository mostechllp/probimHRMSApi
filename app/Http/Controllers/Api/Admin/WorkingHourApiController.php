<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkingHourApiController extends ApiController
{
    /**
     * Get working hours settings
     */
    public function index(): JsonResponse
    {
        $workingHours = WorkingHour::all();
        
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        $formatted = collect($days)->map(function ($day) use ($workingHours) {
            $wh = $workingHours->firstWhere('day', $day);
            return [
                'day' => $day,
                'is_enabled' => $wh ? (bool) $wh->is_enabled : false,
                'start_time' => $wh ? $wh->start_time : '09:00:00',
                'end_time' => $wh ? $wh->end_time : '18:00:00',
            ];
        });

        return $this->success($formatted);
    }

    /**
     * Save working hours settings
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'working_hours' => 'required|array',
            'working_hours.*.day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'working_hours.*.is_enabled' => 'required|boolean',
            'working_hours.*.start_time' => 'nullable|string',
            'working_hours.*.end_time' => 'nullable|string',
        ]);

        foreach ($request->working_hours as $whData) {
            WorkingHour::updateOrCreate(
                ['day' => $whData['day']],
                [
                    'is_enabled' => $whData['is_enabled'],
                    'start_time' => $whData['start_time'] ?? null,
                    'end_time' => $whData['end_time'] ?? null,
                ]
            );
        }

        return $this->success(WorkingHour::all(), 'Working hours updated successfully');
    }
}
