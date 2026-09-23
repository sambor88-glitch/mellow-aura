<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Localization\Support\Locales;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order with copies of what the customer saw — names, variant labels, prices, the text
 * for the mug and the features accepted with their own checkbox — so later changes in the shop never rewrite it. Each cart line says which items it becomes.
 * Stock is not touched here: it comes off only when the payment is confirmed.
 */
class PlaceOrder
{
    /**
     * @param  Collection<string, CartLine>  $lines
     * @param  array<string, mixed>  $data  validated checkout fields
     */
    public function __invoke(Collection $lines, array $data, int $shippingGross): Order
    {
        return DB::transaction(function () use ($lines, $data, $shippingGross) {
            $address = array_filter(Arr::only($data, ['street', 'postal_code', 'city']));

            $order = Order::create([
                'status' => OrderStatus::New,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => substr((string) preg_replace('/\D+/', '', $data['phone']), -9),
                'shipping_method' => $data['shipping_method'],
                'shipping_address' => $address ?: null,
                'shipping_gross' => $shippingGross,
                'total_gross' => $lines->sum(fn (CartLine $line) => $line->total()) + $shippingGross,
                // The cart counts in the currency of the page, so the order is paid in it and speaks its language.
                'currency' => Locales::currency(),
                'locale' => Locales::current(),
                'payment_method' => $data['payment_method'],
                'payment_status' => PaymentStatus::Pending,
                'note' => $data['note'] ?? null,
                'invoice_nip' => $data['invoice_nip'] ?? null,
                'terms_version' => $data['terms_version'] ?? null,
                'terms_accepted_at' => isset($data['terms_version']) ? now() : null,
            ]);

            $order->update(['number' => 'MA-'.$order->created_at->year.'-'.(1000 + $order->id)]);

            foreach ($lines->flatMap(fn (CartLine $line) => $line->orderItems()) as $item) {
                $order->items()->create([
                    'product_variant_id' => $item->variantId,
                    'is_made_to_order' => $item->variantId === null,
                    'product_name' => $item->name,
                    'variant_label' => $item->label,
                    'quantity' => $item->quantity,
                    'unit_price_gross' => $item->unitPrice,
                    'custom_text' => $item->customText,
                    'custom_glaze' => $item->customGlaze,
                    // The checkout only lets an order through once each such feature is accepted.
                    'accepted_deviation' => $item->deviation,
                    'recipient_name' => $item->recipientName,
                    'dedication' => $item->dedication,
                    'sender_name' => $item->senderName,
                ]);
            }

            return $order;
        });
    }
}
