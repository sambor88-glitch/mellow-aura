<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\MeasurementProtocol;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use RuntimeException;
use Throwable;

/**
 * Tells Google Analytics about a paid order from the server (MA-264): every paid order — the shop, vouchers,
 * workshops and custom orders' payments all pay as an order. From the queue, after the payment has committed,
 * so a slow or failing Google never holds up or undoes a payment.
 *
 * The order is marked before the call, in one update that only one worker can win, so a second OrderPaid or a job
 * run twice sends nothing more. A failed call clears the mark and goes back to the queue for later; the last failure
 * is reported to the alerts and never thrown, so even a queue that runs at once cannot fail the payment with it.
 */
class SendServerPurchase implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(private MeasurementProtocol $protocol) {}

    public function shouldQueue(OrderPaid $event): bool
    {
        return $this->protocol->covers($event->order);
    }

    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->order->id);

        if ($order === null || ! $this->protocol->covers($order)) {
            return;
        }

        $claimed = Order::query()->whereKey($order->id)->whereNull('analytics_sent_at')->update(['analytics_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        try {
            $this->protocol->sendPurchase($order);
        } catch (Throwable $failed) {
            Order::query()->whereKey($order->id)->update(['analytics_sent_at' => null]);

            // A queue that runs jobs at once has no later, so its failure is reported straight away.
            if ($this->job !== null && ! $this->job instanceof SyncJob && $this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);

                return;
            }

            // A connection error names the whole address, API secret included, so the alert gets its own words.
            $status = $failed instanceof RequestException ? ' (odpowiedź '.$failed->response->status().')' : '';
            report(new RuntimeException('Google Analytics nie przyjął zakupu '.$order->number.' wysłanego z serwera'.$status.'. Zamówienie jest opłacone, brakuje go tylko w statystykach.'));
        }
    }
}
