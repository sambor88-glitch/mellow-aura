<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes the photos from the custom order form once the privacy policy says they are no longer kept (MA-312):
 * two years from handing over a shipped piece, three months from the last contact about an idea that did not
 * become an order. CustomOrderRequest::photosKeptUntil() says when. The idea itself stays, only the photos go —
 * the files and their media records, through the media library.
 *
 * One idea that fails does not stop the rest: Kasia hears about it through Alerts and the next run tries again.
 */
class PrunePhotos
{
    public function __construct(private Alerts $alerts) {}

    /**
     * @return array{photos: int, requests: int, failed: int}
     */
    public function __invoke(): array
    {
        $requests = CustomOrderRequest::query()
            ->whereNull('photos_deleted_at')
            ->where('status', '!=', CustomOrderStatus::InProgress)
            ->whereHas('media', fn ($media) => $media->where('collection_name', 'photos'))
            ->with(['media', 'paymentLinks'])
            ->get()
            ->filter(fn (CustomOrderRequest $request) => $request->photosKeptUntil()?->isPast() ?? false);

        $photos = 0;
        $failed = [];

        foreach ($requests as $request) {
            try {
                $photos += $request->deletePhotos();
            } catch (Throwable $exception) {
                $failed[] = '#'.$request->id.': '.$exception->getMessage();
            }
        }

        Log::info('custom-orders:prune-photos deleted '.$photos.' photos of '.(count($requests) - count($failed)).' custom order requests'
            .($failed === [] ? '.' : ', '.count($failed).' failed.'));

        if ($failed !== []) {
            $this->alerts->send(
                key: 'custom-orders-prune-photos',
                subject: 'Nie udało się usunąć zdjęć z zamówień indywidualnych',
                details: 'custom-orders:prune-photos nie usunął zdjęć z '.count($failed)." zgłoszeń. Polityka prywatności obiecuje, że po czasie przechowywania znikną — kolejna próba jutro w nocy.\n\n"
                    .implode("\n", array_slice($failed, 0, 10)),
            );
        }

        return ['photos' => $photos, 'requests' => count($requests) - count($failed), 'failed' => count($failed)];
    }
}
