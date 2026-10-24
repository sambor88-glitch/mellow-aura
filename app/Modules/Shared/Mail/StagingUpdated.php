<?php

namespace App\Modules\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the reviewer what is new on staging and asks for a check.
 */
class StagingUpdated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $notes  Review-Note trailers, one change per item
     */
    public function __construct(
        public array $notes,
        public string $siteUrl,
        public string $signature = '',
        public string $replyToAddress = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'MellowAura – coś nowego na stronie testowej',
            replyTo: $this->replyToAddress !== '' ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        // HTML with an explicit <a href> next to plain text, so the address in the
        // e-mail is always APP_URL and never a link the mail client rewrote.
        return new Content(
            view: 'shared::mail.staging-updated-html',
            text: 'shared::mail.staging-updated',
        );
    }
}
