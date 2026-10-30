<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Mail\GiftChoiceReminder;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Support\GiftChoiceTerms;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Every hour from 8 in the morning (MA-235): cards whose day has come go to the people they are for, and cards still
 * waiting get their reminders 14 and 3 days before their last day — by e-mail to the person it is for, or to the
 * buyer of a printed card. A card with less time left than a reminder's days gets only the later one.
 * Chosen cards, cards Kasia decided on and cards past their last day get nothing.
 */
class SendDueGiftChoices
{
    /** Nobody gets a gift at night. */
    public const FROM_HOUR = 8;

    public function __construct(private SendGiftChoice $send) {}

    /**
     * @return array{sent: int, reminded: int}
     */
    public function __invoke(): array
    {
        if (now()->hour < self::FROM_HOUR) {
            return ['sent' => 0, 'reminded' => 0];
        }

        $open = fn () => GiftChoice::query()->issued()->whereNull('chosen_at')->whereNull('resolution')->where('choose_by', '>=', today()->toDateString());

        $due = $open()->whereNotNull('recipient_email')->whereNull('sent_at')->where('send_on', '<=', today()->toDateString())->with('orderItem.order')->get();
        $due->each(fn (GiftChoice $choice) => ($this->send)($choice));

        $reminded = 0;
        [$first, $last] = GiftChoiceTerms::REMINDER_DAYS;

        $open()
            ->where('reminders_sent', '<', 2)
            ->where('choose_by', '<=', today()->addDays($first)->toDateString())
            ->where(fn ($query) => $query->whereNull('recipient_email')->orWhereNotNull('sent_at'))
            ->with('orderItem.order')
            ->get()
            ->each(function (GiftChoice $choice) use ($last, &$reminded) {
                $daysLeft = (int) today()->diffInDays($choice->choose_by, false);
                $step = $daysLeft <= $last ? 2 : 1;

                if ($choice->reminders_sent >= $step) {
                    return;
                }

                $order = $choice->orderItem->order;
                $mail = $choice->goesByEmail() ? new GiftChoiceReminder($choice) : new GiftChoiceReminder($choice, toBuyer: true);
                $to = $choice->goesByEmail() ? new Address($choice->recipient_email, (string) $choice->recipient_name) : new Address($order->email, $order->name);

                rescue(fn () => Mail::to($to)->send($mail));
                $choice->update(['reminders_sent' => $step]);
                $reminded++;
            });

        return ['sent' => $due->count(), 'reminded' => $reminded];
    }
}
