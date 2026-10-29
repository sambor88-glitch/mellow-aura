<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\ComplaintStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A complaint as it came in by e-mail (MA-261). The law gives 14 days from the day it arrived to answer it; without
 * an answer it counts as accepted.
 */
#[Fillable(['order_id', 'order_number', 'name', 'email', 'received_on', 'description', 'status'])]
class Complaint extends Model
{
    /** Days to answer, counted from the day the complaint arrived (art. 7a of the consumer rights act). */
    public const ANSWER_DAYS = 14;

    /** With this many days left or fewer the deadline is highlighted. */
    public const SOON_DAYS = 3;

    /** @var array<string, string> */
    protected $attributes = ['status' => 'open'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasOne<ComplaintAnswer, $this>
     */
    public function answer(): HasOne
    {
        return $this->hasOne(ComplaintAnswer::class);
    }

    /**
     * @param  Builder<Complaint>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', ComplaintStatus::Open);
    }

    /**
     * The last day to answer.
     */
    public function deadline(): CarbonInterface
    {
        return $this->received_on->copy()->addDays(self::ANSWER_DAYS);
    }

    /**
     * Whole days until the deadline; negative once it has passed, zero on the last day.
     */
    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->deadline()->copy()->startOfDay(), false);
    }

    public function overdue(): bool
    {
        return $this->daysLeft() < 0;
    }

    public function dueSoon(): bool
    {
        return $this->daysLeft() <= self::SOON_DAYS;
    }

    /**
     * „odpowiedz do 12.11 — zostało 5 dni”, as the list and „Na dziś” show it.
     */
    public function deadlineLabel(): string
    {
        $date = $this->deadline()->format('j.m');

        return match (true) {
            $this->overdue() => 'termin minął '.$date,
            $this->daysLeft() === 0 => 'odpowiedz do '.$date.' — to ostatni dzień',
            default => 'odpowiedz do '.$date.' — '.trans_choice('{1} został :count dzień|[2,4] zostały :count dni|[5,*] zostało :count dni', $this->daysLeft()),
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'status' => ComplaintStatus::class,
        ];
    }
}
