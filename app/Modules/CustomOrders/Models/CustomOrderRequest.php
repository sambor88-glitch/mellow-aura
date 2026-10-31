<?php

namespace App\Modules\CustomOrders\Models;

use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
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
 * @property ?Carbon $shipped_at
 * @property ?Carbon $photos_deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomOrderRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** A shipped piece: the photos show what was agreed, so they stay as long as the warranty (rękojmia) runs. */
    public const KEEP_SHIPPED_YEARS = 2;

    /** Days from shipping until the parcel surely arrived — the two years count from handing the piece over. */
    public const DELIVERY_DAYS = 30;

    /** An idea that did not become an order: its photos stay this long after the last contact. */
    public const KEEP_UNORDERED_MONTHS = 3;

    protected $guarded = ['id'];

    /**
     * The date the piece went out comes with the status: set when an idea becomes „Wysłane”, cleared when Kasia
     * moves it back.
     */
    protected static function booted(): void
    {
        static::saving(function (self $request) {
            if (! $request->isDirty('status')) {
                return;
            }

            if ($request->status !== CustomOrderStatus::Shipped) {
                $request->shipped_at = null;
            } elseif (! $request->isDirty('shipped_at') && ($request->shipped_at === null || $request->getOriginal('status') !== CustomOrderStatus::Shipped)) {
                $request->shipped_at = now();
            }
        });
    }

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
     * The last time anything happened between Kasia and the customer about this idea: the idea saved in the panel,
     * a payment link sent or paid. A note Kasia saves counts too — it keeps the photos a little longer, never shorter.
     */
    public function lastContactAt(): Carbon
    {
        return collect([$this->created_at, $this->updated_at])
            ->merge($this->paymentLinks->pluck('created_at'))
            ->merge($this->paymentLinks->pluck('paid_at'))
            ->filter()
            ->map(fn ($date) => Carbon::instance($date))
            ->max();
    }

    /**
     * Until when the privacy policy keeps the photos (MA-312); null while the piece is being made — they are needed
     * for the work. A shipped piece keeps them two years from handing over, counted from shipping plus a month for
     * the delivery; an idea that did not become an order — declined or left in „Nowe”, „Wycena”, „Zaliczka” —
     * three months from the last contact.
     */
    public function photosKeptUntil(): ?Carbon
    {
        return match ($this->status) {
            CustomOrderStatus::InProgress => null,
            CustomOrderStatus::Shipped => ($this->shipped_at ?? $this->updated_at)->copy()->addDays(self::DELIVERY_DAYS)->addYearsNoOverflow(self::KEEP_SHIPPED_YEARS),
            default => $this->lastContactAt()->copy()->addMonthsNoOverflow(self::KEEP_UNORDERED_MONTHS),
        };
    }

    /**
     * Deletes the photos — files and media records — and notes when, so the panel says they are gone. The idea's
     * own dates stay as they were: the time it was last saved is the last contact.
     *
     * @return int how many photos went
     */
    public function deletePhotos(): int
    {
        $count = $this->getMedia('photos')->count();

        if ($count === 0) {
            return 0;
        }

        $this->clearMediaCollection('photos');

        $this->timestamps = false;
        $this->forceFill(['photos_deleted_at' => now()])->save();
        $this->timestamps = true;

        return $count;
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
            'shipped_at' => 'datetime',
            'photos_deleted_at' => 'datetime',
        ];
    }
}
