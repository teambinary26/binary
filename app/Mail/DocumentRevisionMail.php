<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\DocumentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentRevisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public DocumentSubmission $document,
        public string $remarks,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Document revision required — '.$this->application->application_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document-revision',
        );
    }
}
