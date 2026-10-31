<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Enums\ErasureReason;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Shared\Support\PersonalData;

/**
 * Forgets a customer's ideas from the custom order form when she asks for it (GDPR art. 17, MA-241).
 * CustomOrderRequest::erase() says what goes and what stays; EraseExpiredRequests does the same once the time to keep them runs out.
 */
class AnonymizeCustomer
{
    /**
     * @return int how many ideas were wiped
     */
    public function __invoke(string $email): int
    {
        $requests = CustomOrderRequest::query()->whereRaw('lower(email) = ?', [PersonalData::normalize($email)])->get();

        foreach ($requests as $request) {
            $request->erase(ErasureReason::Request);
        }

        return $requests->count();
    }
}
