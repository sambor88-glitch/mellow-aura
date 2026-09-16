<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * Issues the vouchers of a paid order: one for every voucher piece paid for, each with its own code and
 * the name and dedication from the order, valid for the months set in the panel from the day of payment.
 * Checkout calls it inside the payment transaction, so a paid order never lacks its vouchers.
 * Calling it again issues nothing new.
 */
class IssueVouchers
{
    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<int, Voucher> the vouchers issued by this call
     */
    public function __invoke(Order $order): Collection
    {
        $months = max(1, (int) $this->settings->get('voucher_validity_months', 12));
        $validUntil = ($order->paid_at ?? now())->copy()->addMonthsNoOverflow($months)->toDateString();
        $issued = collect();

        foreach ($order->items()->with('variant.product.category')->orderBy('id')->get() as $item) {
            if (! $item->variant?->product->isVoucher()) {
                continue;
            }

            $paidPieces = $item->quantity - $item->missing_quantity;

            for ($piece = Voucher::query()->where('order_item_id', $item->id)->count(); $piece < $paidPieces; $piece++) {
                $issued->push(Voucher::create([
                    'code' => $this->unusedCode(),
                    'order_item_id' => $item->id,
                    'recipient_name' => $item->recipient_name,
                    'dedication' => $item->dedication,
                    'valid_until' => $validUntil,
                ]));
            }
        }

        return $issued;
    }

    private function unusedCode(): string
    {
        do {
            $code = VoucherCode::generate();
        } while (Voucher::query()->where('code', $code)->exists());

        return $code;
    }
}
