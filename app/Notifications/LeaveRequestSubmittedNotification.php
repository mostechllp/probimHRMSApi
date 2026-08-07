<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\LeaveRequest;

class LeaveRequestSubmittedNotification extends Notification
{
    use Queueable;

    protected LeaveRequest $leaveRequest;
    protected string $employeeName;
    protected string $leaveTypeName;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
        $this->employeeName = $leaveRequest->employee ? trim("{$leaveRequest->employee->first_name} {$leaveRequest->employee->last_name}") : 'Unknown Employee';
        $this->leaveTypeName = $leaveRequest->leaveType ? $leaveRequest->leaveType->name : 'Leave';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $startDate = $this->leaveRequest->start_date ? $this->leaveRequest->start_date->format('Y-m-d') : 'N/A';
        $endDate = $this->leaveRequest->end_date ? $this->leaveRequest->end_date->format('Y-m-d') : 'N/A';

        return (new MailMessage)
                    ->subject("New Leave Request: {$this->employeeName}")
                    ->greeting("Hello {$notifiable->name},")
                    ->line("{$this->employeeName} has submitted a new leave request.")
                    ->line("**Leave Type:** {$this->leaveTypeName}")
                    ->line("**Duration:** {$startDate} to {$endDate} ({$this->leaveRequest->duration_days} days)")
                    ->line("**Reason:** {$this->leaveRequest->reason}")
                    ->action('Review Leave Request', url('/dashboard/leaves'))
                    ->line('Please review this request in the portal.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $startDate = $this->leaveRequest->start_date ? $this->leaveRequest->start_date->format('Y-m-d') : 'N/A';
        $endDate = $this->leaveRequest->end_date ? $this->leaveRequest->end_date->format('Y-m-d') : 'N/A';

        return [
            'type'          => 'leave_request',
            'leave_id'      => $this->leaveRequest->id,
            'employee'      => $this->employeeName,
            'employee_id'   => $this->leaveRequest->employee_id,
            'leave_type'    => $this->leaveTypeName,
            'duration_days' => $this->leaveRequest->duration_days,
            'date_range'    => "{$startDate} to {$endDate}",
            'message'       => "{$this->employeeName} applied for {$this->leaveRequest->duration_days} days of {$this->leaveTypeName}.",
        ];
    }
}
