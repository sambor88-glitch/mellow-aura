<?php

namespace App\Modules\CustomOrders\Mail;

use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An idea sent through the custom order form, for Kasia, with the customer's photos attached — until the list
 * in the panel (MA-143) the mail is the only place she sees them. Replying writes straight to the customer.
 */
class BriefReceived extends QueuedMail
{
    public function __construct(public CustomOrderRequest $customOrderRequest) {}

    public function description(): string
    {
        return 'Zamówienie indywidualne — '.$this->customOrderRequest->name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zamówienie indywidualne: '.$this->customOrderRequest->kind->label().' — '.$this->customOrderRequest->name,
            replyTo: [new Address($this->customOrderRequest->email, $this->customOrderRequest->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'custom-orders::mail.brief-received',
            text: 'custom-orders::mail.brief-received-text',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->customOrderRequest->getMedia('photos')
            ->map(fn (Media $photo) => Attachment::fromStorageDisk($photo->disk, $photo->getPathRelativeToRoot())
                ->as($photo->file_name)
                ->withMime($photo->mime_type))
            ->values()
            ->all();
    }
}
