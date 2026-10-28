<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherPdf;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
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
class VouchersIssued extends QueuedMail implements HasEditableTexts
{
    /**
     * @param  Collection<int, Voucher>  $vouchers
     */
    public function __construct(public Order $order, public Collection $vouchers) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'vouchers',
            label: 'Vouchery w PDF',
            when: 'Gdy płatność za voucher dojdzie — PDF-y bez cen w załączniku. Twój tekst idzie i przy jednym, i przy kilku voucherach.',
            lang: 'gifts::mail-texts.vouchers',
            placeholders: ['kod' => 'kod vouchera (kilka po przecinku)'],
        );
    }

    public static function sample(string $locale): self
    {
        $order = SampleOrder::make('pl');
        $order->items->first()->product_name = 'Voucher na warsztat';

        return new self($order, collect([(new Voucher)->forceFill([
            'code' => 'MA7K2Q',
            'recipient_name' => 'Ola',
            'sender_name' => 'Anna',
            'valid_until' => now()->addYear(),
        ])->setRelation('orderItem', $order->items->first())]));
    }

    public function description(): string
    {
        return 'Vouchery z zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('vouchers', 'subject', ['kod' => $this->vouchers->pluck('code')->join(', ')], $this->vouchers->count() > 1 ? 'many' : null),
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
