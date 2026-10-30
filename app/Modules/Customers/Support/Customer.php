<?php

namespace App\Modules\Customers\Support;

use Illuminate\Support\Carbon;

/**
 * One person on the „Klienci” list: everything she left in the shop under one address.
 */
final readonly class Customer
{
    /**
     * @param  list<string>  $phones
     */
    public function __construct(
        public string $key,
        public string $email,
        public string $name,
        public array $phones,
        public int $orders,
        public int $workshops,
        public int $customOrders,
        public int $firingRequests,
        public int $cases,
        public int $stockNotifications,
        public Carbon $lastSeenAt,
    ) {}

    /**
     * „2 zamówienia · 1 warsztat · 1 wypał”, leaving out what she never did.
     */
    public function activity(): string
    {
        return collect([
            [$this->orders, '{1} :count zamówienie|[2,4] :count zamówienia|[5,*] :count zamówień'],
            [$this->workshops, '{1} :count warsztat|[2,4] :count warsztaty|[5,*] :count warsztatów'],
            [$this->customOrders, '{1} :count zamówienie indywidualne|[2,4] :count zamówienia indywidualne|[5,*] :count zamówień indywidualnych'],
            [$this->firingRequests, '{1} :count wypał|[2,4] :count wypały|[5,*] :count wypałów'],
            [$this->cases, '{1} :count odstąpienie lub reklamacja|[2,4] :count odstąpienia lub reklamacje|[5,*] :count odstąpień lub reklamacji'],
            [$this->stockNotifications, '{1} czeka na :count rzecz|[2,*] czeka na :count rzeczy'],
        ])
            ->filter(fn (array $count) => $count[0] > 0)
            ->map(fn (array $count) => trans_choice($count[1], $count[0]))
            ->join(' · ');
    }
}
