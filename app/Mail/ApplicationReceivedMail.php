<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public ?string $password = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Application received — '.$this->application->application_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-received',
        );
    }
}
