<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeSpecialDayNotification extends Notification
{
    use Queueable;

    protected array $eventData;

    /**
     * Create a new notification instance.
     *
     * @param array $eventData
     *   Required keys:
     *     - type        : 'Birthday' | 'Work Anniversary' | 'Custom Event'
     *     - employee    : Employee full name
     *     - message     : Human-readable summary string
     *     - date        : The date of the event
     */
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
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
        return (new MailMessage)
                    ->subject("Special Day: {$this->eventData['type']} - {$this->eventData['employee']}")
                    ->greeting("Hello {$notifiable->name},")
                    ->line($this->eventData['message'])
                    ->line("**Event:** {$this->eventData['type']}")
                    ->line("**Employee:** {$this->eventData['employee']}")
                    ->line("**Date:** {$this->eventData['date']}")
                    ->action('View Dashboard', url('/dashboard'));
    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'special_day',
            'event_type'  => $this->eventData['type'],
            'employee'    => $this->eventData['employee'],
            'date'        => $this->eventData['date'],
            'message'     => $this->eventData['message'],
        ];
    }
}
