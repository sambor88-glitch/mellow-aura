<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's acknowledgement on a durable medium: the statement word for word, when it arrived,
 * and what happens with the return and the refund.
 */
class WithdrawalConfirmed extends Mailable
{
    public function __construct(public Withdrawal $withdrawal) {}

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Potwierdzenie odstąpienia od umowy — '.$this->withdrawal->order_number,
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(
            view: 'checkout::mail.withdrawal-confirmed',
            text: 'checkout::mail.withdrawal-confirmed-text',
            with: [
                'returnAddress' => $settings->get('return_address'),
                'contactEmail' => $settings->get('contact_email'),
                'city' => $settings->get('footer_city'),
            ],
        );
    }
}
