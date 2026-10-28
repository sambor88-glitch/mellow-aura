<?php

namespace App\Modules\Firing\Actions;

use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Shared\Support\PersonalData;

/**
 * Forgets a customer's batches reported for firing when she asks for it (GDPR art. 17, MA-241). The service,
 * the amount, the price and the dates stay for the weekly loads; her name, contact and message go.
 */
class AnonymizeCustomer
{
    /**
     * @return int how many batches were wiped
     */
    public function __invoke(string $email): int
    {
        return FiringRequest::query()->whereRaw('lower(email) = ?', [PersonalData::normalize($email)])->update([
            'name' => PersonalData::ERASED_NAME,
            'email' => PersonalData::ERASED_EMAIL,
            'phone' => null,
            'message' => null,
        ]);
    }
}
