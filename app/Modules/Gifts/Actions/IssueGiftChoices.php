<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\GiftChoiceTerms;
use App\Modules\Gifts\Support\VoucherCode;
use Illuminate\Support\Collection;

/**
 * Gives the gift choice cards of a paid order their codes and their last day to choose (MA-235). The cards were
 * saved with the order; until now they had no code, so an unpaid card could not be used. Checkout calls it inside the
 * payment transaction, like IssueVouchers. Calling it again issues nothing new.
 */
class IssueGiftChoices
{
    public function __construct(private GiftChoiceTerms $terms) {}

    /**
     * @return Collection<int, GiftChoice> the cards issued by this call
     */
    public function __invoke(Order $order): Collection
    {
        $paidAt = $order->paid_at ?? now();

        return GiftChoice::query()
            ->whereIn('order_item_id', $order->items()->select('id'))
            ->whereNull('code')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (GiftChoice $choice) => $choice->update([
                'code' => $this->unusedCode(),
                'choose_by' => $this->terms->chooseBy($paidAt, $choice->send_on)->toDateString(),
            ]));
    }

    /** A code no voucher has either, so a code typed on the wrong page is never someone else's. */
    private function unusedCode(): string
    {
        do {
            $code = VoucherCode::generate();
        } while (GiftChoice::query()->where('code', $code)->exists() || Voucher::query()->where('code', $code)->exists());

        return $code;
    }
}
