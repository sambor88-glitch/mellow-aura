<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Orders in the panel. Paid ones come first; unpaid and failed payments wait under their own filter.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $unpaid = $request->query('platnosc') === 'nieoplacone';
        $paid = fn () => Order::query()->where('payment_status', PaymentStatus::Paid);

        $orders = ($unpaid ? Order::query()->where('payment_status', '!=', PaymentStatus::Paid) : $paid())
            ->with(['items', 'withdrawals'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('checkout::admin.orders.index', [
            'orders' => $orders,
            'unpaid' => $unpaid,
            'unpaidCount' => Order::query()->where('payment_status', '!=', PaymentStatus::Paid)->count(),
            'tiles' => [
                ['value' => $paid()->where('paid_at', '>=', today())->count(), 'label' => 'Dziś', 'hint' => 'opłacone od północy'],
                ['value' => $paid()->where('paid_at', '>=', now()->startOfWeek())->count(), 'label' => 'Ten tydzień', 'hint' => 'opłacone od poniedziałku'],
                ['value' => $paid()->where('status', OrderStatus::InProgress)->count(), 'label' => 'Do wysłania', 'hint' => 'opłacone, jeszcze w pracowni'],
            ],
        ]);
    }

    public function show(Order $order, ShippingMethods $shipping): View
    {
        return view('checkout::admin.orders.show', [
            'order' => $order->load(['items', 'withdrawals']),
            'shippingLabel' => $shipping->all()->get($order->shipping_method)['label'] ?? $order->shipping_method,
        ]);
    }
}
