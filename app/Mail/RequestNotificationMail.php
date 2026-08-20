<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requestType;
    public $action;
    public $requestDetails;

    /**
     * Create a new message instance.
     *
     * @param string $requestType Name of the request type (e.g. "Attendance Request")
     * @param string $action "created" or "updated"
     * @param mixed $requestDetails The model instance that was created/updated
     */
    public function __construct($requestType, $action, $requestDetails)
    {
        $this->requestType = $requestType;
        $this->action = $action;
        $this->requestDetails = $requestDetails;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->requestType} has been {$this->action}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.request_notification',
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
