<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\WithdrawalScope;
use App\Modules\Checkout\Events\WithdrawalSubmitted;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;

/**
 * Records a withdrawal statement the moment it arrives and links it to the order when the number
 * and the e-mail both match. A statement that matches nothing is kept too — Kasia checks it by hand.
 */
class SubmitWithdrawal
{
    /**
     * @param  array{name: string, email: string, order_number: string, scope: string, items?: ?string}  $data
     */
    public function __invoke(array $data): Withdrawal
    {
        $order = Order::query()
            ->where('number', $data['order_number'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
            ->first();

        $withdrawal = Withdrawal::create([
            'order_id' => $order?->id,
            'order_number' => $data['order_number'],
            'name' => $data['name'],
            'email' => $data['email'],
            'scope' => $data['scope'],
            'items' => $data['scope'] === WithdrawalScope::Part->value ? $data['items'] : null,
            'submitted_at' => now(),
        ]);

        WithdrawalSubmitted::dispatch($withdrawal);

        return $withdrawal;
    }
}
