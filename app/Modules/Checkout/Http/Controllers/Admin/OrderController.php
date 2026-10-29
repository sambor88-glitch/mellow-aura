<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\ChangeOrderStatus;
use App\Modules\Checkout\Actions\ConfirmBankTransfer;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Http\Requests\Admin\ChangeOrderStatusRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderWait;
use App\Modules\Checkout\Support\ShippingMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Orders in the panel. The list opens on what waits to be sent, oldest first (MA-250); the other statuses, all paid orders
 * and the unpaid ones are filters of their own.
 */
class OrderController extends Controller
{
    /** The status filters in the address, in Polish, and the status each one shows. */
    private const STATUS_FILTERS = [
        'do-wyslania' => OrderStatus::InProgress,
        'wyslane' => OrderStatus::Shipped,
        'zakonczone' => OrderStatus::Completed,
        'problem' => OrderStatus::Problem,
    ];

    /** The filter for every paid order, whatever its status. */
    private const ALL_PAID = 'wszystkie';

    public function index(Request $request, OrderWait $wait): View
    {
        $unpaid = $request->query('platnosc') === 'nieoplacone';
        $allPaid = ! $unpaid && $request->query('status') === self::ALL_PAID;
        $status = $unpaid || $allPaid ? null : (self::STATUS_FILTERS[(string) $request->query('status')] ?? OrderStatus::InProgress);
        $paid = fn () => Order::query()->where('payment_status', PaymentStatus::Paid);

        $orders = ($unpaid ? Order::query()->where('payment_status', '!=', PaymentStatus::Paid) : $paid())
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['items', 'withdrawals'])
            // What waits to be sent is worked through from the oldest; everything else reads from the newest.
            ->when($status === OrderStatus::InProgress, fn ($query) => $query->orderBy('paid_at')->orderBy('id'), fn ($query) => $query->latest('id'))
            ->paginate(30)
            ->withQueryString();

        $counts = $paid()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('checkout::admin.orders.index', [
            'orders' => $orders,
            'unpaid' => $unpaid,
            'allPaid' => $allPaid,
            'status' => $status,
            'wait' => $wait,
            'unpaidCount' => Order::query()->where('payment_status', '!=', PaymentStatus::Paid)->count(),
            'filters' => collect(self::STATUS_FILTERS)->map(fn (OrderStatus $filter, string $slug) => [
                'slug' => $slug,
                'label' => $filter === OrderStatus::InProgress ? 'Do wysłania' : $filter->label(),
                'count' => (int) ($counts[$filter->value] ?? 0),
                'active' => $filter === $status,
            ])->values(),
            'tiles' => [
                ['value' => $paid()->where('paid_at', '>=', today())->count(), 'label' => 'Dziś', 'hint' => 'opłacone od północy'],
                ['value' => $paid()->where('paid_at', '>=', now()->startOfWeek())->count(), 'label' => 'Ten tydzień', 'hint' => 'opłacone od poniedziałku'],
                ['value' => (int) ($counts[OrderStatus::InProgress->value] ?? 0), 'label' => 'Do wysłania', 'hint' => 'opłacone, jeszcze w pracowni'],
            ],
        ]);
    }

    public function show(Order $order, ShippingMethods $shipping): View
    {
        return view('checkout::admin.orders.show', [
            'order' => $order->load(['items.variant.product.category', 'items.certificates', 'withdrawals']),
            'shippingLabel' => $shipping->label($order->shipping_method),
        ]);
    }

    public function status(ChangeOrderStatusRequest $request, Order $order, ChangeOrderStatus $change): RedirectResponse
    {
        $status = OrderStatus::from($request->validated('status'));
        $sentBefore = $order->shipped_at !== null;
        $change($order, $status, $request->validated('tracking_number'), $request->validated('problem_note'));

        return to_route('admin.orders.show', $order)->with('panel_status', match ($status) {
            OrderStatus::Shipped => $sentBefore ? 'Znowu wysłane — klientka dostała maila za pierwszym razem, drugi nie wyjdzie.' : ($order->tracking_number ? 'Wysłane — klientka dostanie maila z numerem przesyłki.' : 'Wysłane — klientka dostanie maila.'),
            OrderStatus::Completed => 'Zakończone — '.$order->number.' nie czeka już na Ciebie.',
            OrderStatus::Problem => 'Problem zapisany — widać go na liście zamówień.',
            default => 'Z powrotem w realizacji.',
        });
    }

    /** „Wpłata doszła” on an order paid by bank transfer (MA-216). Any other way to pay is confirmed by its gateway. */
    public function paid(Request $request, Order $order, ConfirmBankTransfer $confirm): RedirectResponse
    {
        abort_unless($order->payment_method === PaymentMethod::BankTransfer && $order->payment_status !== PaymentStatus::Failed, 403);

        return to_route('admin.orders.show', $order)->with('panel_status', $confirm($order, $request->user())
            ? 'Opłacone — klientka dostanie maila z potwierdzeniem.'
            : 'Ta wpłata jest już zapisana — nic się nie zmieniło.');
    }
}
