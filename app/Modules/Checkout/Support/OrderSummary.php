<?php

namespace App\Modules\Checkout\Support;

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
     *     subtotal: int,
     *     shippingLabel: string,
     *     delivery: string,
     *     contactEmail: ?string,
     *     contactPhone: ?string,
     *     city: ?string,
     * }
     */
    public function for(Order $order): array
    {
        $items = $order->items->sortBy('id')->values();

        return [
            'order' => $order,
            'items' => $items,
            'missing' => $items->filter(fn (OrderItem $item) => $item->missing_quantity > 0)->values(),
            'subtotal' => (int) $items->sum(fn (OrderItem $item) => $item->total()),
            'shippingLabel' => $this->shipping->all()->get($order->shipping_method)['label'] ?? $order->shipping_method,
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
            'parcel_locker' => $order->locker_code
                ? 'Paczkomat '.$order->locker_code
                : 'Paczkomat dobiorę po numerze telefonu '.$order->phone,
            'courier' => 'Adres dostawy potwierdzę z Tobą przed wysyłką',
            default => 'Napiszę, kiedy i gdzie możesz odebrać zamówienie',
        };
    }
}
