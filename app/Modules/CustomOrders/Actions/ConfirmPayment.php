<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Models\CustomOrderPayment;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Support\Facades\DB;

/**
 * Counts the money for an idea once the payment is confirmed. Checkout\Actions\MarkOrderPaid calls it inside its own
 * transaction, so the idea and the payment change together. The first payment starts the work: an idea waiting for
 * it moves to „W realizacji”; a later one (the rest before shipping) leaves the status alone.
 *
 * Money the idea should not have taken still counts, but the order goes under „Problem” and Kasia hears about it:
 * a payment for a declined idea, or one that takes the total above the quote (an old link and a new one both paid).
 */
class ConfirmPayment
{
    public function __construct(private Alerts $alerts) {}

    /**
     * @return ?string a note for the order when the money has to be looked at, null when all is well or the order pays no idea
     */
    public function __invoke(Order $order): ?string
    {
        return DB::transaction(function () use ($order) {
            $payment = CustomOrderPayment::query()->where('order_id', $order->id)->first();

            if ($payment === null) {
                return null;
            }

            $link = $payment->paymentLink;
            $idea = CustomOrderRequest::query()->lockForUpdate()->findOrFail($link->custom_order_request_id);

            if ($payment->paid_at === null) {
                $payment->update(['paid_at' => now()]);
                $link->update(['status' => PaymentLinkStatus::Paid, 'paid_at' => $link->paid_at ?? now()]);
            }

            $problem = match (true) {
                $idea->status === CustomOrderStatus::Declined => 'Wpłata za odrzucony pomysł. Zwróć ją albo przywróć zamówienie indywidualne.',
                $idea->quote !== null && $idea->paidAmount() > $idea->quote => 'Wpłaty przekraczają wycenę '.$order->money($idea->quote).'. Zwróć nadpłatę.',
                default => null,
            };

            if (in_array($idea->status, [CustomOrderStatus::New, CustomOrderStatus::Quoting, CustomOrderStatus::Deposit], true)) {
                $idea->update(['status' => CustomOrderStatus::InProgress]);
            }

            if ($problem !== null) {
                DB::afterCommit(fn () => $this->alerts->send(
                    'custom-order-payment:'.$order->number,
                    'Wpłata za zamówienie indywidualne do sprawdzenia',
                    $order->number.' ('.$idea->name.', '.$idea->email.'): wpłata '.$order->money($order->total_gross).'. '.$problem,
                ));
            }

            return $problem;
        });
    }
}
