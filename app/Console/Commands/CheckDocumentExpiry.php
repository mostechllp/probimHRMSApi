<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\DocumentExpiryNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class CheckDocumentExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for documents nearing expiry and notify admin users.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Document expiry check started at ' . now());
        $thresholdDays = 30; // Notify for documents expiring in the next 30 days
        $today = Carbon::today();
        $thresholdDate = $today->copy()->addDays($thresholdDays);

        $adminUsers = User::all();

        if ($adminUsers->isEmpty()) {
            $this->warn('No admin users found to notify.');
            return;
        }

        $notifiedCount = 0;

        // Check Documents table
        $expiringDocuments = Document::whereBetween('expiry_date', [$today, $thresholdDate])->get();

        foreach ($expiringDocuments as $doc) {
            $data = [
                'type' => 'document',
                'name' => $doc->name,
                'expiry_date' => $doc->expiry_date,
                'message' => "The document '{$doc->name}' is expiring on {$doc->expiry_date}."
            ];

            Notification::send($adminUsers, new DocumentExpiryNotification($data));
            $notifiedCount++;

            Log::info("Document notified: {$doc->name}");
        }

        // Check Employee documents
        $expiryFields = [
            'passport_expiry_date' => 'Passport',
            'visa_expiry_date' => 'Visa',
            'labor_expiry_date' => 'Labor Card',
            'eid_expiry_date' => 'EID'
        ];

        foreach ($expiryFields as $field => $label) {
            $expiringEmployees = Employee::whereBetween($field, [$today, $thresholdDate])->get();

            foreach ($expiringEmployees as $employee) {
                $data = [
                    'type' => 'employee_document',
                    'name' => "{$label} ({$employee->name})",
                    'expiry_date' => $employee->$field,
                    'owner' => $employee->name,
                    'message' => "The {$label} for employee '{$employee->name}' is expiring on {$employee->$field}."
                ];

                Notification::send($adminUsers, new DocumentExpiryNotification($data));
                $notifiedCount++;

                Log::info("Employee doc notified: {$emp->name} - {$label}");
            }
        }

        $this->info("Check complete. Sent {$notifiedCount} notifications.");
        Log::info("Employee doc notified: {$emp->name} - {$label}");
    }
}
