<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Models\Certificate;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives every handmade piece of an order its certificate number, once: printing again keeps the numbers.
 * The number comes from the certificate's id and year, e.g. „2026/0012”, so two pieces never share one.
 */
class IssueCertificates
{
    /**
     * @return Collection<int, Certificate> in the order of the items, piece by piece
     */
    public function __invoke(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            // Two clicks at once would try to number the same pieces.
            Order::query()->whereKey($order->id)->lockForUpdate()->first();
            $order->load(['items.variant.product.category', 'items.certificates']);

            $certificates = collect();

            foreach ($order->items as $item) {
                if (! $item->getsCertificate()) {
                    continue;
                }

                $existing = $item->certificates->keyBy('piece');

                for ($piece = 1; $piece <= $this->pieces($item); $piece++) {
                    $certificate = $existing->get($piece) ?? $this->issue($item, $piece);
                    $certificates->push($certificate->setRelation('orderItem', $item));
                }
            }

            return $certificates;
        });
    }

    /**
     * Pieces that were gone from the shelf when the payment came in are not sent, so they get no certificate.
     */
    private function pieces(OrderItem $item): int
    {
        return max(0, $item->quantity - (int) $item->missing_quantity);
    }

    private function issue(OrderItem $item, int $piece): Certificate
    {
        $certificate = $item->certificates()->create(['piece' => $piece]);
        $certificate->update(['number' => $certificate->created_at->year.'/'.str_pad((string) $certificate->id, 4, '0', STR_PAD_LEFT)]);

        return $certificate;
    }
}
