<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Shared\Support\PersonalData;

/**
 * Forgets the sizes a customer was waiting for when she asks for it (GDPR art. 17, MA-241, MA-266). Nothing here
 * is needed for the books, so the rows go whole.
 */
class AnonymizeCustomer
{
    /**
     * @return int how many waiting sizes were forgotten
     */
    public function __invoke(string $email): int
    {
        return StockNotification::query()->whereRaw('lower(email) = ?', [PersonalData::normalize($email)])->delete();
    }
}
