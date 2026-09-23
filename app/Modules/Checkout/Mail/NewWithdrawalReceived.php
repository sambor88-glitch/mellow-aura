<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells Kasia a withdrawal came in: the statement, the order it belongs to and the refund deadline.
 * Replying writes straight to the customer.
 */
class NewWithdrawalReceived extends QueuedMail
{
    public function __construct(public Withdrawal $withdrawal) {}

    public function description(): string
    {
        return 'Powiadomienie dla Ciebie o odstąpieniu — zamówienie '.$this->withdrawal->order_number;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Odstąpienie od umowy: '.$this->withdrawal->order_number.' — '.$this->withdrawal->name,
            replyTo: [new Address($this->withdrawal->email, $this->withdrawal->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.new-withdrawal',
            text: 'checkout::mail.new-withdrawal-text',
        );
    }
}
