<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The summary the terms promise right after ordering (§5 ust. 4): what was ordered and that the contract comes
 * with the second mail, once the payment is in. A bank transfer gets the details to pay.
 */
class OrderAwaitingPayment extends QueuedMail
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public function description(): string
    {
        return 'Podsumowanie zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: __('checkout::mail.subject.awaiting', ['number' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('order-awaiting-payment'),
            text: $this->template('order-awaiting-payment-text'),
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
