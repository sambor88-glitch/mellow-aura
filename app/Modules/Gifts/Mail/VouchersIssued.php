<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherPdf;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * The paid vouchers as PDFs, in a mail of their own, so the buyer can forward it to the person
 * they are for without the prices of the rest of the order.
 */
class VouchersIssued extends QueuedMail
{
    /**
     * @param  Collection<int, Voucher>  $vouchers
     */
    public function __construct(public Order $order, public Collection $vouchers) {}

    public function description(): string
    {
        return 'Vouchery z zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: $this->vouchers->count() === 1 ? 'Voucher '.$this->vouchers->first()->code : 'Vouchery z MellowAury',
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gifts::mail.vouchers',
            text: 'gifts::mail.vouchers-text',
            with: ['contactEmail' => app(Settings::class)->get('contact_email'), 'contactPhone' => app(Settings::class)->get('contact_phone')],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->vouchers
            ->map(fn (Voucher $voucher) => Attachment::fromData(fn () => app(VoucherPdf::class)->render($voucher), VoucherPdf::filename($voucher))->withMime('application/pdf'))
            ->values()
            ->all();
    }
}
