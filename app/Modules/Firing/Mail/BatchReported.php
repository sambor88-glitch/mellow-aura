<?php

namespace App\Modules\Firing\Mail;

use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A batch reported through the firing form, for Kasia. Replying writes straight to the customer.
 */
class BatchReported extends QueuedMail
{
    public function __construct(public FiringRequest $firingRequest) {}

    public function description(): string
    {
        return 'Zgłoszenie wsadu do wypału — '.$this->firingRequest->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zgłoszenie wsadu: '.($this->firingRequest->service_label ?? 'do doradzenia').' — '.$this->firingRequest->name,
            replyTo: [new Address($this->firingRequest->email, $this->firingRequest->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'firing::mail.batch-reported',
            text: 'firing::mail.batch-reported-text',
        );
    }
}
