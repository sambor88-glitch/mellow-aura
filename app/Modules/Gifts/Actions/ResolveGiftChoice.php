<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Enums\GiftChoiceResolution;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Kasia's decision on a card nobody chose on by its last day (MA-235), once. A plain mug she arranges with the buyer
 * herself; the card only remembers the decision. A voucher is an amount voucher worth what the card cost, valid
 * for the voucher months from the panel, for the names on the card; the buyer gets it as a PDF, like a bought one.
 * The card is locked, so a second click or a late choice never makes two of anything.
 */
class ResolveGiftChoice
{
    public function __construct(private Settings $settings) {}

    /**
     * @return bool false when the card was chosen or decided meanwhile, or is not past its last day
     */
    public function __invoke(GiftChoice $choice, GiftChoiceResolution $resolution): bool
    {
        $voucher = DB::transaction(function () use ($choice, $resolution) {
            $choice = GiftChoice::query()->issued()->lockForUpdate()->find($choice->id);

            if ($choice === null || $choice->chosen_at !== null || $choice->resolution !== null || ! $choice->isExpired()) {
                return false;
            }

            $voucher = $resolution === GiftChoiceResolution::Voucher ? $this->voucherFor($choice) : null;
            $choice->update(['resolution' => $resolution, 'resolved_at' => now(), 'voucher_id' => $voucher?->id]);

            return $voucher ?? true;
        });

        if ($voucher instanceof Voucher) {
            $order = $choice->orderItem->order;
            rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new VouchersIssued($order, collect([$voucher->load('orderItem')]))));
        }

        return $voucher !== false;
    }

    private function voucherFor(GiftChoice $choice): Voucher
    {
        do {
            $code = VoucherCode::generate();
        } while (Voucher::query()->where('code', $code)->exists() || GiftChoice::query()->where('code', $code)->exists());

        return Voucher::create([
            'code' => $code,
            'order_item_id' => $choice->order_item_id,
            'recipient_name' => $choice->recipient_name,
            'sender_name' => $choice->sender_name,
            'dedication' => $choice->dedication,
            'amount_minor' => $choice->price_gross,
            'balance_minor' => $choice->price_gross,
            'valid_until' => now()->addMonthsNoOverflow(max(1, (int) $this->settings->get('voucher_validity_months', 12)))->toDateString(),
        ]);
    }
}
