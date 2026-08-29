<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = now()->year;

        $holidays = [
            [
                'title' => 'New Year',
                'holiday_date' => Carbon::create($year, 1, 1)->toDateString(),
                'description' => 'New Year Holiday',
                'is_optional' => false,
            ],
            [
                'title' => 'Republic Day',
                'holiday_date' => Carbon::create($year, 1, 26)->toDateString(),
                'description' => 'Republic Day',
                'is_optional' => false,
            ],
            [
                'title' => 'Good Friday',
                'holiday_date' => Carbon::parse('good friday ' . $year)->toDateString(),
                'description' => 'Good Friday',
                'is_optional' => false,
            ],
            [
                'title' => 'Labour Day',
                'holiday_date' => Carbon::create($year, 5, 1)->toDateString(),
                'description' => 'International Workers Day',
                'is_optional' => false,
            ],
            [
                'title' => 'Independence Day',
                'holiday_date' => Carbon::create($year, 8, 15)->toDateString(),
                'description' => 'Independence Day',
                'is_optional' => false,
            ],
            [
                'title' => 'Gandhi Jayanti',
                'holiday_date' => Carbon::create($year, 10, 2)->toDateString(),
                'description' => 'Gandhi Jayanti',
                'is_optional' => false,
            ],
            [
                'title' => 'Christmas',
                'holiday_date' => Carbon::create($year, 12, 25)->toDateString(),
                'description' => 'Christmas Day',
                'is_optional' => false,
            ],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                [
                    'holiday_date' => $holiday['holiday_date'],
                ],
                $holiday
            );
        }
    }
}