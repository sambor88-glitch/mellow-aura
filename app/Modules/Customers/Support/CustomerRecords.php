<?php

namespace App\Modules\Customers\Support;

use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Checkout\Enums\ComplaintStatus;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Shared\Support\PersonalData;
use App\Modules\Workshops\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the shop keeps about one address, read through the other modules' models: the card in „Klientki”,
 * the copy of the data she can ask for (art. 15) and what still stands in the way of forgetting her (art. 17).
 */
class CustomerRecords
{
    /** An unpaid order this fresh may still be paid by transfer, so its data waits. */
    public const PENDING_PAYMENT_DAYS = 14;

    /** @var Collection<int, Order> */
    public readonly Collection $orders;

    /** @var Collection<int, Voucher> */
    public readonly Collection $vouchers;

    /** @var Collection<int, CustomOrderRequest> */
    public readonly Collection $customOrders;

    /** @var Collection<int, FiringRequest> */
    public readonly Collection $firingRequests;

    /** @var Collection<int, Withdrawal> */
    public readonly Collection $withdrawals;

    /** @var Collection<int, Complaint> Complaints as they came in (MA-261). */
    public readonly Collection $receivedComplaints;

    /** @var Collection<int, ComplaintAnswer> */
    public readonly Collection $complaints;

    /** @var Collection<int, StockNotification> Sizes she waits for (MA-266). */
    public readonly Collection $stockNotifications;

    /** @var Collection<int, PrivacyRequest> */
    public readonly Collection $privacyRequests;

    public function __construct(public readonly Customer $customer)
    {
        $byEmail = fn (Builder $query) => $query->whereRaw('lower(email) = ?', [PersonalData::normalize($customer->email)]);

        $this->orders = Order::query()->tap($byEmail)
            ->with(['items', 'workshopBooking.workshopDate'])
            ->latest('id')
            ->get();
        $this->vouchers = Voucher::query()
            ->whereIn('order_item_id', $this->orders->flatMap->items->pluck('id'))
            ->with('orderItem.order')
            ->orderBy('id')
            ->get();
        $this->customOrders = CustomOrderRequest::query()->tap($byEmail)->with('media')->latest('id')->get();
        $this->firingRequests = FiringRequest::query()->tap($byEmail)->latest('id')->get();
        $this->withdrawals = Withdrawal::query()->tap($byEmail)->latest('id')->get();
        $this->receivedComplaints = Complaint::query()->tap($byEmail)->latest('id')->get();
        $this->complaints = ComplaintAnswer::query()->tap($byEmail)->latest('id')->get();
        $this->stockNotifications = StockNotification::query()->tap($byEmail)->with('variant.product')->latest('id')->get();
        $this->privacyRequests = PrivacyRequest::query()->where('customer_key', $customer->key)->latest('id')->get();
    }

    /**
     * What must be finished before her data can go: anything still being paid, made, sent or answered needs her
     * address. Each entry says what it is and links to where Kasia finishes it.
     *
     * @return list<array{label: string, url: string}>
     */
    public function blockers(): array
    {
        $orders = $this->orders->filter(fn (Order $order) => match ($order->payment_status) {
            PaymentStatus::Paid => $order->status !== OrderStatus::Completed,
            PaymentStatus::Pending => $order->created_at->gt(now()->subDays(self::PENDING_PAYMENT_DAYS)),
            PaymentStatus::Failed => false,
        })->map(fn (Order $order) => [
            'label' => 'Zamówienie '.$order->number.' — '.($order->payment_status === PaymentStatus::Paid
                ? mb_strtolower($order->status->label())
                : 'czeka na płatność do '.$order->created_at->addDays(self::PENDING_PAYMENT_DAYS)->format('j.m.Y')),
            'url' => route('admin.orders.show', $order),
        ]);

        $workshops = $this->orders
            ->filter(fn (Order $order) => $order->workshopBooking !== null
                && in_array($order->workshopBooking->status, [BookingStatus::Held, BookingStatus::Confirmed], true)
                && $order->workshopBooking->workshopDate?->starts_at->isFuture())
            ->map(fn (Order $order) => [
                'label' => 'Warsztat '.$order->workshopBooking->workshopDate->dayLabel().' — zapis '.mb_strtolower($order->workshopBooking->status->label()),
                'url' => route('admin.orders.show', $order),
            ]);

        $customOrders = $this->customOrders
            ->filter(fn (CustomOrderRequest $request) => in_array($request->status, CustomOrderStatus::open(), true))
            ->map(fn (CustomOrderRequest $request) => [
                'label' => 'Zamówienie indywidualne z '.$request->created_at->format('j.m.Y').' — '.mb_strtolower($request->status->label()),
                'url' => route('admin.custom-orders.index', ['status' => $request->status->value]).'#pomysl-'.$request->id,
            ]);

        $firing = $this->firingRequests
            ->filter(fn (FiringRequest $request) => in_array($request->status, FiringRequestStatus::open(), true))
            ->map(fn (FiringRequest $request) => [
                'label' => 'Zgłoszenie do wypału z '.$request->created_at->format('j.m.Y').' — '.mb_strtolower($request->status->label()),
                'url' => route('admin.firing.requests.index').'#zgloszenie-'.$request->id,
            ]);

        $withdrawals = $this->withdrawals
            ->whereNull('handled_at')
            ->map(fn (Withdrawal $withdrawal) => [
                'label' => 'Odstąpienie od umowy '.$withdrawal->order_number.' — czeka na zwrot pieniędzy',
                'url' => route('admin.withdrawals.index'),
            ]);

        $complaints = $this->receivedComplaints
            ->filter(fn (Complaint $complaint) => $complaint->status === ComplaintStatus::Open)
            ->map(fn (Complaint $complaint) => [
                'label' => 'Reklamacja z '.$complaint->received_on->format('j.m.Y').' — '.$complaint->deadlineLabel(),
                'url' => route('admin.complaints.index', ['reklamacja' => $complaint->id]).'#odpowiedz',
            ]);

        return $orders->toBase()->concat($workshops)->concat($customOrders)->concat($firing)->concat($withdrawals)->concat($complaints)->values()->all();
    }

    /**
     * The copy of her data she can ask for (GDPR art. 15) in a form another service can read (art. 20). Keys stay
     * in English like the rest of the data; values are what she typed and what the shop recorded.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'controller' => config('app.name'),
            'email' => $this->customer->email,
            'orders' => $this->orders->map(fn (Order $order) => [
                'number' => $order->number,
                'placed_at' => $order->created_at->toIso8601String(),
                'status' => $order->status->value,
                'payment' => ['method' => $order->payment_method->value, 'status' => $order->payment_status->value, 'paid_at' => $order->paid_at?->toIso8601String()],
                'name' => $order->name,
                'email' => $order->email,
                'phone' => $order->phone,
                'shipping_method' => $order->shipping_method,
                'shipping_address' => $order->shipping_address,
                'delivery_country' => $order->delivery_country,
                'parcel_locker' => $order->locker_code,
                'tracking_number' => $order->tracking_number,
                'note' => $order->note,
                'invoice_nip' => $order->invoice_nip,
                'terms' => ['version' => $order->terms_version, 'accepted_at' => $order->terms_accepted_at?->toIso8601String()],
                'currency' => $order->currency,
                'total' => $order->money($order->total_gross),
                'shipping' => $order->money($order->shipping_gross),
                'items' => $order->items->map(fn ($item) => array_filter([
                    'product' => $item->product_name,
                    'variant' => $item->variant_label,
                    'quantity' => $item->quantity,
                    'unit_price' => $order->money($item->unit_price_gross),
                    'inscription' => $item->custom_text,
                    'glaze' => $item->custom_glaze,
                    'voucher_recipient' => $item->recipient_name,
                    'voucher_sender' => $item->sender_name,
                    'dedication' => $item->dedication,
                ], fn (mixed $value) => $value !== null && $value !== ''))->values()->all(),
                'workshop' => $order->workshopBooking === null ? null : [
                    'date' => $order->workshopBooking->workshopDate?->starts_at->toIso8601String(),
                    'seats' => $order->workshopBooking->seats,
                    'status' => $order->workshopBooking->status->value,
                ],
            ])->values()->all(),
            'vouchers' => $this->vouchers->map(fn (Voucher $voucher) => [
                'code' => $voucher->code,
                'order' => $voucher->orderItem?->order?->number,
                'recipient' => $voucher->recipient_name,
                'sender' => $voucher->sender_name,
                'dedication' => $voucher->dedication,
                'valid_until' => $voucher->valid_until?->toDateString(),
            ])->values()->all(),
            'custom_orders' => $this->customOrders->map(fn (CustomOrderRequest $request) => [
                'sent_at' => $request->created_at->toIso8601String(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'kind' => $request->kind->value,
                'quantity' => $request->quantity,
                'needed_by' => $request->needed_by,
                'budget' => $request->budget,
                'description' => $request->description,
                'status' => $request->status->value,
                'photos' => $request->getMedia('photos')->pluck('file_name')->all(),
            ])->values()->all(),
            'firing_requests' => $this->firingRequests->map(fn (FiringRequest $request) => [
                'sent_at' => $request->created_at->toIso8601String(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'service' => $request->service_label,
                'amount' => $request->amount(),
                'drop_off_on' => $request->drop_off_on?->toDateString(),
                'message' => $request->message,
                'status' => $request->status->value,
            ])->values()->all(),
            'withdrawals' => $this->withdrawals->map(fn (Withdrawal $withdrawal) => [
                'submitted_at' => $withdrawal->submitted_at->toIso8601String(),
                'order' => $withdrawal->order_number,
                'name' => $withdrawal->name,
                'email' => $withdrawal->email,
                'statement' => $withdrawal->statement(),
            ])->values()->all(),
            'received_complaints' => $this->receivedComplaints->map(fn (Complaint $complaint) => [
                'received_on' => $complaint->received_on->toDateString(),
                'order' => $complaint->order_number,
                'name' => $complaint->name,
                'email' => $complaint->email,
                'description' => $complaint->description,
                'status' => $complaint->status->value,
            ])->values()->all(),
            'complaints' => $this->complaints->map(fn (ComplaintAnswer $answer) => [
                'received_on' => $answer->received_on?->toDateString(),
                'order' => $answer->order_number,
                'name' => $answer->name,
                'email' => $answer->email,
                'decision' => $answer->decision->value,
                'details' => $answer->details,
                'answer' => $answer->letter,
            ])->values()->all(),
            'stock_notifications' => $this->stockNotifications->map(fn (StockNotification $notification) => [
                'signed_up_at' => $notification->created_at->toIso8601String(),
                'email' => $notification->email,
                'product' => $notification->variant?->product?->name,
                'variant' => $notification->variant?->label,
                'language' => $notification->locale,
            ])->values()->all(),
        ];
    }

    /**
     * How many records of each kind she has, for the GDPR log.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'orders' => $this->orders->count(),
            'workshop_bookings' => $this->orders->whereNotNull('workshopBooking')->count(),
            'vouchers' => $this->vouchers->count(),
            'custom_orders' => $this->customOrders->count(),
            'firing_requests' => $this->firingRequests->count(),
            'withdrawals' => $this->withdrawals->count(),
            'received_complaints' => $this->receivedComplaints->count(),
            'complaints' => $this->complaints->count(),
            'stock_notifications' => $this->stockNotifications->count(),
        ];
    }
}
