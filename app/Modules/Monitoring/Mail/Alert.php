<?php

namespace App\Modules\Monitoring\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A technical alert in plain text. It goes out at once and never through the queue, because the queue may be
 * the thing that stopped.
 */
class Alert extends Mailable
{
    public function __construct(public string $summary, public string $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').' · '.app()->environment().'] '.$this->summary,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'monitoring::mail.alert-text');
    }
}
