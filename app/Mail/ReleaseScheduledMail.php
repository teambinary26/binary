<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\ReleaseSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReleaseScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public ReleaseSchedule $schedule,
        public bool $rescheduled = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->rescheduled ? 'Release rescheduled — ' : 'Release scheduled — ').$this->application->application_no,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.release-scheduled',
        );
    }
}
