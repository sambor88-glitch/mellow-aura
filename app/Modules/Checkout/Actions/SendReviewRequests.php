<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Mail\ReviewRequest;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queues the mail asking for a Google review (MA-329) for every order completed `review_request_days` days ago:
 * a parcel that reached the customer (from the ShipX sync or Kasia's „Zakończone” in the panel). Without the review
 * link or the number of days in the panel nothing goes out.
 *
 * Each order gets one such mail, ever: `review_requested_at` is set before the mail is queued and only by the run
 * that sets it, so two runs at once never send it twice. No mail for an order that is no longer completed (a problem
 * after the delivery), that brought a withdrawal or a complaint, whose customer asked to be forgotten, or that had
 * nothing to deliver (vouchers sent as PDFs, workshop seats, custom-order payments).
 *
 * The scheduler runs it daily. An order completed up to CATCH_UP_DAYS days before the due day still gets its mail,
 * so a day the server missed is caught up, but turning the mail on never sends it for months-old orders.
 */
class SendReviewRequests
{
    public const CATCH_UP_DAYS = 7;

    /** Where a withdrawal or a complaint about the order is kept, by the order's id or the number she typed. */
    private const DISPUTES = ['withdrawals', 'complaints', 'complaint_answers'];

    public function __construct(private Settings $settings) {}

    /**
     * @return int how many mails were queued
     */
    public function __invoke(): int
    {
        $url = $this->settings->get('review_request_url');
        $days = (int) $this->settings->get('review_request_days');

        if (! is_string($url) || $url === '' || $days < 1) {
            return 0;
        }

        $due = now()->subDays($days);
        $sent = 0;

        $orders = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereNull('review_requested_at')
            ->where('completed_at', '<=', $due)
            ->where('completed_at', '>', $due->copy()->subDays(self::CATCH_UP_DAYS))
            ->where('shipping_method', '!=', ShippingMethods::EMAIL)
            ->where('email', '!=', PersonalData::ERASED_EMAIL)
            ->where(fn (Builder $query) => $this->withoutDisputes($query))
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            if (Order::query()->whereKey($order->id)->whereNull('review_requested_at')->update(['review_requested_at' => now()]) !== 1) {
                continue;
            }

            try {
                Mail::to(new Address($order->email, $order->name))->send(new ReviewRequest($order, $url));
                $sent++;
            } catch (Throwable $exception) {
                // Not queued: the next run tries again.
                Order::query()->whereKey($order->id)->update(['review_requested_at' => null]);
                report($exception);
            }
        }

        return $sent;
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function withoutDisputes(Builder $query): void
    {
        foreach (self::DISPUTES as $table) {
            $query->whereNotExists(fn (QueryBuilder $dispute) => $dispute
                ->selectRaw('1')
                ->from($table)
                ->where(fn (QueryBuilder $match) => $match
                    ->whereColumn($table.'.order_id', 'orders.id')
                    ->orWhereColumn($table.'.order_number', 'orders.number')));
        }
    }
}
