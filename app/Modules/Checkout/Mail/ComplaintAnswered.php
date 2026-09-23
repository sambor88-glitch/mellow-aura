<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The answer to a complaint on a durable medium, in the words Kasia saw in the panel before sending.
 */
class ComplaintAnswered extends Mailable
{
    public function __construct(public ComplaintAnswer $answer) {}

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Odpowiedź na reklamację'.($this->answer->order_number ? ' — '.$this->answer->order_number : ''),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.complaint-answered',
            text: 'checkout::mail.complaint-answered-text',
            with: ['paragraphs' => explode("\n\n", $this->answer->letter)],
        );
    }
}
