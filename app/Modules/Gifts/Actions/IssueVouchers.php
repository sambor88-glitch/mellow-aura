<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * Issues the vouchers of a paid order: one for every voucher piece paid for, each with its own code and
 * the names and dedication from the order, valid for the months set in the panel from the day of payment.
 * A workshop voucher takes its workshop from the product and its seats from the size (one when left empty).
 * An amount voucher is worth what was paid for it and starts with all of it on its balance (MA-211); vouchers pay
 * in złoty only, so one bought in another currency gets no balance and Kasia settles it by hand.
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
            $workshop = $item->variant->product->workshop_code;
            $amount = $workshop === null && $item->currency === 'PLN' ? $item->unit_price_gross : null;

            for ($piece = Voucher::query()->where('order_item_id', $item->id)->count(); $piece < $paidPieces; $piece++) {
                $issued->push(Voucher::create([
                    'code' => $this->unusedCode(),
                    'order_item_id' => $item->id,
                    'recipient_name' => $item->recipient_name,
                    'sender_name' => $item->sender_name,
                    'dedication' => $item->dedication,
                    'workshop_code' => $workshop,
                    'seats' => $workshop === null ? null : ($item->variant->voucher_seats ?? 1),
                    'amount_minor' => $amount,
                    'balance_minor' => $amount,
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
