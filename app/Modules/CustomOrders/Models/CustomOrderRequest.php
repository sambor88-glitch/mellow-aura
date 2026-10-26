<?php

namespace App\Modules\CustomOrders\Models;

use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An idea for a custom piece sent through the form, with the customer's photos. Kasia works through them
 * in the panel „Zamówienia indywidualne”, where she writes her price and a note only she sees.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $phone
 * @property BriefKind $kind
 * @property ?string $quantity
 * @property ?string $needed_by
 * @property ?string $budget
 * @property string $description
 * @property ?int $quote
 * @property ?string $notes
 * @property CustomOrderStatus $status
 */
class CustomOrderRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    /**
     * The customer's photos stay on the private disk: they can show her home, her family or someone's keepsake.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('local')->acceptsMimeTypes(['image/webp']);
    }

    /**
     * The links Kasia sent for this idea, newest first.
     *
     * @return HasMany<PaymentLink, $this>
     */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class)->latest('id');
    }

    /**
     * Every try to pay one of its links, paid or not.
     *
     * @return HasManyThrough<CustomOrderPayment, PaymentLink, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(CustomOrderPayment::class, PaymentLink::class, secondKey: 'custom_order_payment_link_id');
    }

    /**
     * What came in through the links, in grosze.
     */
    public function paidAmount(): int
    {
        return (int) $this->payments()->whereNotNull('custom_order_payments.paid_at')->sum('custom_order_payments.amount');
    }

    /**
     * What is left of the quote, in grosze: the default amount of the next link. Null without a quote.
     */
    public function amountDue(): ?int
    {
        return $this->quote === null ? null : max(0, $this->quote - $this->paidAmount());
    }

    /**
     * The lines both mails list, label => value; an empty one is left out.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        $photos = $this->getMedia('photos')->count();

        return array_filter([
            'Co to ma być' => $this->kind->label(),
            'Ile sztuk' => $this->quantity,
            'Na kiedy' => $this->needed_by,
            'Budżet' => $this->budget,
            'Zdjęcia' => $photos > 0 ? (string) $photos : null,
        ], fn (?string $value) => $value !== null);
    }

    protected function casts(): array
    {
        return [
            'kind' => BriefKind::class,
            'quote' => 'integer',
            'status' => CustomOrderStatus::class,
        ];
    }
}
