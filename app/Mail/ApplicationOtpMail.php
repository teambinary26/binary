<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $applicantName,
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your application verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.application-otp',
        );
    }
}
