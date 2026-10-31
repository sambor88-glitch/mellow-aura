<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\AnalyticsClient;
use App\Modules\Checkout\Support\OrderTax;
use App\Modules\Gifts\Actions\HoldVoucherBalance;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Localization\Support\Locales;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order with copies of what the customer saw — names, variant labels, prices, the text
 * for the mug and the features accepted with their own checkbox — so later changes in the shop never rewrite it. Each cart line says which items it becomes.
 * Stock is not touched here: it comes off only when the payment is confirmed.
 * Amount vouchers pay first, from their balance (MA-211): the money is held in the same transaction, the order keeps
 * how much they paid, and an order they pay in full needs no other way to pay.
 * With the customer's yes to statistics the order also keeps Google Analytics' id of her browser and the items
 * as the browser counts them, for the purchase measured from the server once it is paid (MA-264).
 */
class PlaceOrder
{
    public function __construct(private OrderTax $tax, private HoldVoucherBalance $holdVoucherBalance, private AnalyticsClient $analytics) {}

    /**
     * @param  Collection<string, CartLine>  $lines
     * @param  array<string, mixed>  $data  validated checkout fields
     * @param  list<string>  $voucherCodes  amount vouchers to pay with, in the order typed
     *
     * @throws BalanceNotAccepted
     */
    public function __invoke(Collection $lines, array $data, int $shippingGross, array $voucherCodes = []): Order
    {
        // Every way to order — the checkout, a workshop, a custom order's payment, a gift choice — comes from the
        // customer's own browser, so the request holds her cookies; a command or a queued job holds none.
        $analytics = $this->analytics->fromRequest(app()->bound('request') ? request() : null);

        return DB::transaction(function () use ($lines, $data, $shippingGross, $voucherCodes, $analytics) {
            $address = array_filter(Arr::only($data, ['street', 'postal_code', 'city']));

            $order = Order::create([
                'status' => OrderStatus::New,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => substr((string) preg_replace('/\D+/', '', $data['phone']), -9),
                'shipping_method' => $data['shipping_method'],
                'shipping_address' => $address ?: null,
                'delivery_country' => OrderTax::DOMESTIC,
                'locker_code' => $data['shipping_method'] === 'parcel_locker' ? ($data['locker_code'] ?? null) : null,
                'shipping_gross' => $shippingGross,
                'total_gross' => $lines->sum(fn (CartLine $line) => $line->total()) + $shippingGross,
                'vouchers_gross' => 0,
                // The cart counts in the currency of the page, so the order is paid in it and speaks its language.
                'currency' => Locales::currency(),
                ...$this->tax->euroRate(),
                'locale' => Locales::current(),
                'payment_method' => $data['payment_method'],
                'payment_status' => PaymentStatus::Pending,
                'note' => $data['note'] ?? null,
                'invoice_nip' => $data['invoice_nip'] ?? null,
                'terms_version' => $data['terms_version'] ?? null,
                'terms_accepted_at' => isset($data['terms_version']) ? now() : null,
                ...$analytics,
                // The same items the confirmation page hands Google Analytics in the browser, so both count one purchase.
                'analytics_items' => $analytics['ga_client_id'] !== null
                    ? $lines->map(fn (CartLine $line) => $line->analyticsItem())->values()->all()
                    : null,
            ]);

            $order->update(['number' => 'MA-'.$order->created_at->year.'-'.(1000 + $order->id)]);

            // The same rate and basis for every item: one seller, one tax status at the moment of the order.
            $vatRate = $this->tax->vatRate();
            $vatExemption = $this->tax->vatExemption();

            foreach ($lines->flatMap(fn (CartLine $line) => $line->orderItems()) as $item) {
                $saved = $order->items()->create([
                    'product_variant_id' => $item->variantId,
                    'is_made_to_order' => $item->variantId === null,
                    'product_name' => $item->name,
                    'piece_name' => $item->pieceName,
                    'variant_label' => $item->label,
                    'quantity' => $item->quantity,
                    'unit_price_gross' => $item->unitPrice,
                    'currency' => $order->currency,
                    'vat_rate' => $vatRate,
                    'vat_exemption' => $vatExemption,
                    'custom_text' => $item->customText,
                    'custom_glaze' => $item->customGlaze,
                    // The checkout only lets an order through once each such feature is accepted.
                    'accepted_deviation' => $item->deviation,
                    'recipient_name' => $item->recipientName,
                    'dedication' => $item->dedication,
                    'sender_name' => $item->senderName,
                ]);

                if ($item->placed !== null) {
                    ($item->placed)($saved);
                }
            }

            $vouchersGross = ($this->holdVoucherBalance)($order, $voucherCodes);

            if ($vouchersGross > 0) {
                $order->update([
                    'vouchers_gross' => $vouchersGross,
                    'payment_method' => $vouchersGross === $order->total_gross ? PaymentMethod::Voucher : $order->payment_method,
                ]);
            }

            return $order;
        });
    }
}
