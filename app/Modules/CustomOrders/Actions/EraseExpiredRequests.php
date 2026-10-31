<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\ErasureReason;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forgets the customer's data and photos from the custom order form once the privacy policy no longer keeps them
 * (MA-312): two years from handing over a shipped piece, three months from the last contact about an idea that did
 * not become an order. CustomOrderRequest::keptUntil() says when, CustomOrderRequest::erase() what goes — the same
 * as when she asks to be forgotten (AnonymizeCustomer). The idea stays with its kind, status, quote and payments.
 *
 * One idea that fails does not stop the rest: the alert goes through Alerts and the next run tries again.
 */
class EraseExpiredRequests
{
    public function __construct(private Alerts $alerts) {}

    /**
     * @return array{requests: int, photos: int, failed: int}
     */
    public function __invoke(): array
    {
        $requests = CustomOrderRequest::query()
            ->whereNull('erased_at')
            ->where('status', '!=', CustomOrderStatus::InProgress)
            // Erased on request before MA-312, when there was no date to mark it.
            ->where('email', '!=', PersonalData::ERASED_EMAIL)
            ->with(['media', 'paymentLinks'])
            ->get()
            ->filter(fn (CustomOrderRequest $request) => $request->keptUntil()?->isPast() ?? false);

        $erased = 0;
        $photos = 0;
        $failed = [];

        foreach ($requests as $request) {
            try {
                $photos += $request->erase(ErasureReason::Retention);
                $erased++;
            } catch (Throwable $exception) {
                $failed[] = '#'.$request->id.': '.$exception->getMessage();
            }
        }

        Log::info('custom-orders:erase-expired erased '.$erased.' custom order requests with '.$photos.' photos'
            .($failed === [] ? '.' : ', '.count($failed).' failed.'));

        if ($failed !== []) {
            $this->alerts->send(
                key: 'custom-orders-erase-expired',
                subject: 'Nie udało się usunąć danych z zamówień indywidualnych',
                details: 'custom-orders:erase-expired nie usunął danych i zdjęć z '.count($failed)." zgłoszeń, choć minął ich czas przechowywania z polityki prywatności. Kolejna próba jutro w nocy.\n\n"
                    .implode("\n", array_slice($failed, 0, 10)),
            );
        }

        return ['requests' => $erased, 'photos' => $photos, 'failed' => count($failed)];
    }
}
