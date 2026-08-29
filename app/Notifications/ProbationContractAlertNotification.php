<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProbationContractAlertNotification extends Notification
{
    use Queueable;

    protected array $alertData;

    /**
     * Create a new notification instance.
     *
     * @param array $alertData
     *   Required keys:
     *     - type        : 'probation' | 'contract'
     *     - employee    : Employee full name
     *     - employee_id : Employee ID string
     *     - due_date    : Date string (Y-m-d)
     *     - days_left   : int
     *     - message     : Human-readable summary string
     */
    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    /**
     * Deliver via mail AND store in the database notifications table.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Build the email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = $this->alertData['type'] === 'probation'
            ? 'Probation Period Ending'
            : 'Contract Renewal Due';

        return (new MailMessage)
            ->subject("{$typeLabel}: {$this->alertData['employee']}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->alertData['message'])
            ->line("**Employee:** {$this->alertData['employee']} ({$this->alertData['employee_id']})")
            ->line("**Due Date:** {$this->alertData['due_date']}")
            ->line("**Days Remaining:** {$this->alertData['days_left']} day(s)")
            ->action('View Employee', url('/dashboard'))
            ->line('Please take the necessary HR action before the due date.');
    }

    /**
     * Store the notification data in the database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => $this->alertData['type'],          // 'probation' | 'contract'
            'employee'    => $this->alertData['employee'],
            'employee_id' => $this->alertData['employee_id'],
            'due_date'    => $this->alertData['due_date'],
            'days_left'   => $this->alertData['days_left'],
            'message'     => $this->alertData['message'],
        ];
    }
}
