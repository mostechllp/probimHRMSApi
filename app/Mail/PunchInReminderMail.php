<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PunchInReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $date;
    public $scheduledStart;

    /**
     * Create a new message instance.
     */
    public function __construct($employee, $date, $scheduledStart)
    {
        $this->employee = $employee;
        $this->date = $date;
        $this->scheduledStart = $scheduledStart;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: You Have Not Punched In Today',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.punch_in_reminder',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
