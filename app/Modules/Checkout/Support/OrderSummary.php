<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * What the order e-mails show, built from the order's own copies of names and prices,
 * so a later change in the shop never rewrites a sent confirmation.
 */
class OrderSummary
{
    public function __construct(private Settings $settings, private ShippingMethods $shipping) {}

    /**
     * @return array{
     *     order: Order,
     *     items: Collection<int, OrderItem>,
     *     missing: Collection<int, OrderItem>,
     *     refund: int,
     *     nothingLeft: bool,
     *     bankTransfer: ?array{account: ?string, recipient: ?string},
     *     subtotal: int,
     *     shippingLabel: string,
     *     parcel: bool,
     *     delivery: string,
     *     contactEmail: ?string,
     *     contactPhone: ?string,
     *     city: ?string,
     * }
     */
    public function for(Order $order): array
    {
        $items = $order->items->sortBy('id')->values();
        $missing = $items->filter(fn (OrderItem $item) => $item->missing_quantity > 0)->values();
        // Gift wrapping has no product and no text, so on its own it is nothing to send.
        $toSend = $items->filter(fn (OrderItem $item) => $item->quantity > $item->missing_quantity && ($item->product_variant_id !== null || $item->custom_text !== null));
        $nothingLeft = $missing->isNotEmpty() && $toSend->isEmpty();

        return [
            'order' => $order,
            'items' => $items,
            'missing' => $missing,
            // What comes back for the pieces someone else bought first; the whole payment when nothing is left to send.
            'refund' => $nothingLeft ? $order->total_gross : (int) $missing->sum(fn (OrderItem $item) => $item->unit_price_gross * $item->missing_quantity),
            'nothingLeft' => $nothingLeft,
            'bankTransfer' => $order->payment_method === PaymentMethod::BankTransfer
                ? ['account' => $this->settings->get('company_bank_account'), 'recipient' => $this->settings->get('company_name')]
                : null,
            'subtotal' => (int) $items->sum(fn (OrderItem $item) => $item->total()),
            'shippingLabel' => $this->shipping->label($order->shipping_method),
            'parcel' => $order->sendsParcel(),
            'delivery' => $this->delivery($order),
            'contactEmail' => $this->settings->get('contact_email'),
            'contactPhone' => $this->settings->get('contact_phone'),
            'city' => $this->settings->get('footer_city'),
        ];
    }

    /**
     * Where the parcel goes, in the words the checkout used. Never the studio address:
     * the place for a pickup goes out in a separate message.
     */
    private function delivery(Order $order): string
    {
        $address = array_filter((array) $order->shipping_address);

        if (isset($address['street'], $address['postal_code'], $address['city'])) {
            return $address['street'].', '.$address['postal_code'].' '.$address['city'];
        }

        return match ($order->shipping_method) {
            ShippingMethods::EMAIL => 'Na adres '.$order->email,
            'parcel_locker' => $order->locker_code
                ? 'Paczkomat '.$order->locker_code
                : 'Paczkomat dobiorę po numerze telefonu '.$order->phone,
            'courier' => 'Adres dostawy potwierdzę z Tobą przed wysyłką',
            default => 'Napiszę, kiedy i gdzie możesz odebrać zamówienie',
        };
    }
}
