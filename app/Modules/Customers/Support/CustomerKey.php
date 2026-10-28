<?php

namespace App\Modules\Customers\Support;

use App\Modules\Shared\Support\PersonalData;

/**
 * Names a person without her address: the card's link and the GDPR log use it, so neither a URL in a server log
 * nor a row of privacy_requests gives the address away. Keyed with APP_KEY, so the hash can't be matched against
 * a list of addresses without it.
 */
final class CustomerKey
{
    public static function of(string $email): string
    {
        return substr(hash_hmac('sha256', PersonalData::normalize($email), (string) config('app.key')), 0, 32);
    }
}
