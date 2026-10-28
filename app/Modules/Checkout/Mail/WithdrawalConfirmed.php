<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Enums\WithdrawalScope;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's acknowledgement on a durable medium: the statement word for word, when it arrived,
 * and what happens with the return and the refund.
 */
class WithdrawalConfirmed extends QueuedMail implements HasEditableTexts
{
    public function __construct(public Withdrawal $withdrawal) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'withdrawal_confirmed',
            label: 'Potwierdzenie odstąpienia od umowy',
            when: 'Zaraz po wysłaniu formularza „Odstąp od umowy tutaj”. Data oświadczenia i jego treść zostają w mailu zawsze.',
            lang: 'checkout::mail-texts.withdrawal_confirmed',
            parts: ['subject', 'outro'],
            placeholders: ['numer' => 'numer zamówienia'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self((new Withdrawal)->forceFill([
            'order_number' => SampleOrder::make($locale)->number,
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.com',
            'scope' => WithdrawalScope::Whole,
            'submitted_at' => now(),
        ]));
    }

    public function description(): string
    {
        return 'Potwierdzenie odstąpienia od umowy — zamówienie '.$this->withdrawal->order_number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('withdrawal_confirmed', 'subject', ['numer' => $this->withdrawal->order_number]),
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
