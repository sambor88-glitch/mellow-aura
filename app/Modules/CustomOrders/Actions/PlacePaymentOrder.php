<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\CustomOrders\Support\PaymentLine;
use Illuminate\Support\Facades\DB;

/**
 * Places the order for one try to pay a link: one item, nothing to deliver, the customer's details from her idea.
 * ConfirmPayment counts the money once the gateway confirms it.
 */
class PlacePaymentOrder
{
    public function __construct(private PlaceOrder $placeOrder) {}

    /**
     * @param  array{payment_method: string, terms_version: ?string}  $data
     */
    public function __invoke(PaymentLink $link, array $data): Order
    {
        return DB::transaction(function () use ($link, $data) {
            $idea = $link->customOrderRequest;
            $line = new PaymentLine($link);

            $order = ($this->placeOrder)(collect([$line->key => $line]), [
                ...$data,
                'name' => $idea->name,
                'email' => $idea->email,
                'phone' => (string) $idea->phone,
                'shipping_method' => ShippingMethods::EMAIL,
            ], 0);

            $link->payments()->create(['order_id' => $order->id, 'amount' => $link->amount]);

            return $order;
        });
    }
}
