<?php

namespace App\Modules\Checkout\Actions;

use App\Models\User;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Kasia saw a bank transfer on her account and marks the order as paid in the panel (MA-216). No gateway
 * confirms a bank transfer, so this is the only way such an order gets paid. Only a pending bank transfer counts;
 * a second click, or an order paid in the meantime, changes nothing.
 */
class ConfirmBankTransfer
{
    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    /** Whether this call marked the order as paid. */
    public function __invoke(Order $order, User $user): bool
    {
        return DB::transaction(function () use ($order, $user) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_method !== PaymentMethod::BankTransfer || $order->payment_status !== PaymentStatus::Pending) {
                return false;
            }

            $order = ($this->markOrderPaid)($order, 'transfer-'.now()->format('Y-m-d'));
            $order->update(['payment_confirmed_by' => $user->name]);

            return true;
        });
    }
}
