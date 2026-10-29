<?php

namespace App\Modules\Customers\Support;

use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The people behind the shop's records, gathered by e-mail address — there are no customer accounts. Reads the
 * other modules' tables and changes nothing. A small studio has a few thousand records at most, so they are read
 * whole and grouped here; records already erased (PersonalData::ERASED_EMAIL) belong to no one.
 */
class CustomerDirectory
{
    /**
     * Everyone, the most recently seen first.
     *
     * @return Collection<int, Customer>
     */
    public function all(): Collection
    {
        return $this->records()
            ->groupBy(fn (array $record) => PersonalData::normalize($record['email']))
            ->map(function (Collection $records, string $email): Customer {
                $records = $records->sortByDesc('at')->values();
                $count = fn (string $kind) => $records->where('kind', $kind)->count();

                return new Customer(
                    key: CustomerKey::of($email),
                    email: $email,
                    // Waiting for a size leaves no name; the address stands in when nothing else does.
                    name: (string) ($records->first(fn (array $record) => filled($record['name']))['name'] ?? $email),
                    phones: $records->pluck('phone')->filter(fn (?string $phone) => filled($phone))->unique()->values()->all(),
                    orders: $count('order') + $count('workshop'),
                    workshops: $count('workshop'),
                    customOrders: $count('custom_order'),
                    firingRequests: $count('firing'),
                    cases: $count('case'),
                    stockNotifications: $count('stock_notification'),
                    lastSeenAt: $records->first()['at'],
                );
            })
            ->sortByDesc(fn (Customer $customer) => $customer->lastSeenAt->getTimestamp())
            ->values();
    }

    /**
     * The people whose name, address or phone contains the query. Phones match on digits alone,
     * so „600 100 200” finds „+48600100200”.
     *
     * @return Collection<int, Customer>
     */
    public function search(?string $query): Collection
    {
        $query = mb_strtolower(trim((string) $query));

        if ($query === '') {
            return $this->all();
        }

        $digits = preg_replace('/\D/', '', $query);

        return $this->all()->filter(fn (Customer $customer) => str_contains(mb_strtolower($customer->name), $query)
            || str_contains($customer->email, $query)
            || (strlen($digits) >= 3 && collect($customer->phones)->contains(fn (string $phone) => str_contains((string) preg_replace('/\D/', '', $phone), $digits))))
            ->values();
    }

    public function find(string $key): ?Customer
    {
        return $this->all()->first(fn (Customer $customer) => hash_equals($customer->key, $key));
    }

    /**
     * @return Collection<int, array{email: string, name: string, phone: ?string, at: Carbon, kind: string}>
     */
    private function records(): Collection
    {
        $orders = Order::query()->withCount('workshopBooking')->get(['id', 'name', 'email', 'phone', 'created_at'])
            ->map(fn (Order $order) => $this->record($order->email, $order->name, $order->phone, $order->created_at, $order->workshop_booking_count > 0 ? 'workshop' : 'order'));
        $customOrders = CustomOrderRequest::query()->get(['name', 'email', 'phone', 'created_at'])
            ->map(fn (CustomOrderRequest $request) => $this->record($request->email, $request->name, $request->phone, $request->created_at, 'custom_order'));
        $firing = FiringRequest::query()->get(['name', 'email', 'phone', 'created_at'])
            ->map(fn (FiringRequest $request) => $this->record($request->email, $request->name, $request->phone, $request->created_at, 'firing'));
        $withdrawals = Withdrawal::query()->get(['name', 'email', 'submitted_at'])
            ->map(fn (Withdrawal $withdrawal) => $this->record($withdrawal->email, $withdrawal->name, null, $withdrawal->submitted_at, 'case'));
        $complaints = ComplaintAnswer::query()->whereNull('complaint_id')->get(['name', 'email', 'created_at'])
            ->map(fn (ComplaintAnswer $answer) => $this->record($answer->email, $answer->name, null, $answer->created_at, 'case'))
            ->concat(Complaint::query()->get(['name', 'email', 'created_at'])
                ->map(fn (Complaint $complaint) => $this->record($complaint->email, $complaint->name, null, $complaint->created_at, 'case')));

        $stockNotifications = StockNotification::query()->get(['email', 'created_at'])
            ->map(fn (StockNotification $notification) => $this->record($notification->email, '', null, $notification->created_at, 'stock_notification'));

        return $orders->toBase()
            ->concat($customOrders)->concat($firing)->concat($withdrawals)->concat($complaints)->concat($stockNotifications)
            ->reject(fn (array $record) => PersonalData::normalize($record['email']) === PersonalData::ERASED_EMAIL);
    }

    /**
     * @return array{email: string, name: string, phone: ?string, at: Carbon, kind: string}
     */
    private function record(string $email, string $name, ?string $phone, mixed $at, string $kind): array
    {
        return ['email' => $email, 'name' => $name, 'phone' => $phone, 'at' => Carbon::parse($at), 'kind' => $kind];
    }
}
