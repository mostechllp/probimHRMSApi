<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LateWarningNotification extends Notification
{
    use Queueable;

    public $employee;
    public $lateCount;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $lateCount)
    {
        $this->employee = $employee;
        $this->lateCount = $lateCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Late Warning Notification - ' . $this->employee->name)
                    ->line('The employee ' . $this->employee->name . ' has been late ' . $this->lateCount . ' times this month.')
                    ->action('View Employee', route('employees.show', $this->employee->id))
                    ->line('Please take necessary action.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'late_count' => $this->lateCount,
            'title' => 'Late',
            'message' => $this->employee->name . ' has been late ' . $this->lateCount . ' times this month.'
        ];
    }
}
