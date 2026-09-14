<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public ?string $password = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->password ? 'Application accepted — ' : 'Application approved — ').$this->application->application_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-approved',
        );
    }
}
