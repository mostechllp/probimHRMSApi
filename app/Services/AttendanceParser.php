<?php

namespace App\Services;

use Carbon\Carbon;

class AttendanceParser
{
    /**
     * Parse the given file content based on extension.
     *
     * @param string $content
     * @param string $extension
     * @return array
     */
    public function parse(string $content, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsv($content);
        }

        return $this->parseDat($content);
    }

    /**
     * Parse .dat file (space/tab separated).
     *
     * @param string $content
     * @return array
     */
    private function parseDat(string $content): array
    {
        $lines = explode("\n", str_replace("\r", "", trim($content)));
        $data = [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            
            // Expected: userid, date, time, status, ...
            if (count($parts) >= 3) {
                $userid = $parts[0];
                $date = $parts[1];
                $time = $parts[2];
                $status = $parts[3] ?? null;
                $deviceId = $parts[4] ?? null;

                try {
                    $timestamp = Carbon::createFromFormat('Y-m-d H:i:s', "$date $time");
                    $data[] = [
                        'userid' => $userid,
                        'timestamp' => $timestamp,
                        'status' => $status,
                        'device_id' => $deviceId,
                    ];
                } catch (\Exception $e) {
                    // Skip invalid lines
                    continue;
                }
            }
        }

        return $data;
    }

    /**
     * Parse .csv file.
     *
     * @param string $content
     * @return array
     */
    private function parseCsv(string $content): array
    {
        $lines = explode("\n", str_replace("\r", "", trim($content)));
        $data = [];
        $headers = [];

        foreach ($lines as $index => $line) {
            $row = str_getcsv($line);
            
            if ($index === 0) {
                $headers = array_map('strtolower', $row);
                continue;
            }

            if (count($row) < 2) continue;

            $rowMap = array_combine(array_slice($headers, 0, count($row)), $row);
            
            // Basic fallback if headers don't match exactly
            $userid = $rowMap['userid'] ?? $rowMap['user_id'] ?? $row[0];
            $timestampStr = $rowMap['timestamp'] ?? $rowMap['date_time'] ?? $row[1];

            try {
                $timestamp = Carbon::parse($timestampStr);
                $data[] = [
                    'userid' => $userid,
                    'timestamp' => $timestamp,
                    'status' => $rowMap['status'] ?? null,
                    'device_id' => $rowMap['device_id'] ?? null,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $data;
    }
}
