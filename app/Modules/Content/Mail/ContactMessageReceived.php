<?php

namespace App\Modules\Content\Mail;

use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A message from the contact form, for Kasia. Replying writes straight to the sender.
 */
class ContactMessageReceived extends QueuedMail
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public ?string $topic,
        public string $body,
    ) {}

    public function description(): string
    {
        return 'Wiadomość z formularza kontaktowego — '.$this->senderName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Wiadomość ze strony'.($this->topic ? ': '.$this->topic : '').' — '.$this->senderName,
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'content::mail.contact-message',
            text: 'content::mail.contact-message-text',
        );
    }
}
