<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payroll;
    public $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct($payroll, $pdfContent)
    {
        $this->payroll = $payroll;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $monthName = \Carbon\Carbon::createFromFormat('m', $this->payroll->pay_period_month)->format('F');
        return new Envelope(
            subject: "Payslip for {$monthName} {$this->payroll->pay_period_year}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payslip',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $monthName = \Carbon\Carbon::createFromFormat('m', $this->payroll->pay_period_month)->format('F');
        $fileName = "Payslip_{$monthName}_{$this->payroll->pay_period_year}.pdf";

        return [
            Attachment::fromData(fn () => $this->pdfContent, $fileName)
                ->withMime('application/pdf'),
        ];
    }
}
