<?php

namespace App\Modules\Gifts\Models;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Gifts\Enums\GiftChoiceResolution;
use App\Modules\Gifts\Enums\GiftChoiceStatus;
use App\Modules\Gifts\Support\VoucherCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * A gift choice card (MA-235, „Podaruj wybór”): the buyer pays for a mug of one size, and the person it is for
 * chooses the text and the inside glaze and says where to send it. Saved with the order, one per piece; the code
 * comes with the payment (Actions\IssueGiftChoices), so an unpaid card has none and cannot be used.
 * A card goes by e-mail on the day the buyer picked, or the buyer prints it. The choice has to be made by choose_by;
 * after that Kasia decides in the panel: a plain mug or a voucher.
 *
 * @property ?string $code
 * @property ?string $recipient_email
 * @property ?CarbonImmutable $send_on
 * @property ?CarbonImmutable $sent_at
 * @property ?CarbonImmutable $choose_by
 * @property ?CarbonImmutable $chosen_at
 * @property ?GiftChoiceResolution $resolution
 */
#[Fillable([
    'code', 'order_item_id', 'size_label', 'size_name', 'price_gross', 'recipient_name', 'sender_name', 'dedication',
    'recipient_email', 'send_on', 'sent_at', 'choose_by', 'reminders_sent', 'chosen_at', 'fulfilment_order_id',
    'resolution', 'resolved_at', 'voucher_id',
])]
class GiftChoice extends Model
{
    /**
     * The card on the buyer's order; its order says who paid.
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The order for the chosen mug, with the address of the person it is for.
     *
     * @return BelongsTo<Order, $this>
     */
    public function fulfilmentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'fulfilment_order_id');
    }

    /**
     * The voucher Kasia gave instead, after the deadline.
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Paid cards only: an unpaid one has no code yet.
     *
     * @param  Builder<self>  $query
     */
    public function scopeIssued(Builder $query): void
    {
        $query->whereNotNull('code');
    }

    /**
     * Past the deadline, not chosen and not decided by Kasia: what waits for her in the panel.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingDecision(Builder $query): void
    {
        $query->issued()->whereNull('chosen_at')->whereNull('resolution')->where('choose_by', '<', today()->toDateString());
    }

    /**
     * What someone tells Kasia about a card (MA-289): its code however it was typed, the buyer's order number,
     * or the name or e-mail of the buyer or of the person it is for.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMatching(Builder $query, string $typed): void
    {
        $like = '%'.$typed.'%';

        $query->where(fn (Builder $query) => $query
            ->where('code', VoucherCode::normalize($typed))
            ->orWhere('recipient_name', 'like', $like)
            ->orWhere('recipient_email', 'like', $like)
            ->orWhereHas('orderItem.order', fn (Builder $order) => $order
                ->where('number', mb_strtoupper($typed))
                ->orWhere('name', 'like', $like)
                ->orWhere('email', 'like', $like)));
    }

    /** Goes by e-mail to the person it is for, rather than printed by the buyer. */
    public function goesByEmail(): bool
    {
        return $this->recipient_email !== null;
    }

    /** The choice can be made through the whole last day. */
    public function isExpired(): bool
    {
        return $this->choose_by !== null && $this->choose_by->endOfDay()->isPast();
    }

    public function status(): GiftChoiceStatus
    {
        return match (true) {
            $this->chosen_at !== null => GiftChoiceStatus::Chosen,
            $this->resolution !== null => GiftChoiceStatus::Resolved,
            $this->isExpired() => GiftChoiceStatus::Expired,
            $this->goesByEmail() && $this->sent_at === null => GiftChoiceStatus::Scheduled,
            default => GiftChoiceStatus::Waiting,
        };
    }

    /**
     * Where the person it is for chooses. Signed and without an end date: the card itself says until when it counts.
     */
    public function chooseUrl(): string
    {
        return URL::signedRoute('gift-choice.show', ['choice' => $this->code]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_gross' => 'integer',
            'send_on' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'choose_by' => 'immutable_date',
            'reminders_sent' => 'integer',
            'chosen_at' => 'immutable_datetime',
            'resolution' => GiftChoiceResolution::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
