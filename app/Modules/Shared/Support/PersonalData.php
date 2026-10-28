<?php

namespace App\Modules\Shared\Support;

/**
 * What a record holds after its person asked to be forgotten (GDPR art. 17, MA-241). Each module wipes its own
 * tables through its own AnonymizeCustomer action; this class keeps them writing the same placeholders, so the
 * „Klientki” screen can tell an erased record from a real address.
 */
final class PersonalData
{
    /** Takes the place of the e-mail. The .invalid domain never receives mail (RFC 2606). */
    public const ERASED_EMAIL = 'erased@privacy.invalid';

    /** Takes the place of a name Kasia reads in the panel. */
    public const ERASED_NAME = 'Dane usunięte';

    /**
     * The form of an address the records are matched by: people type it in different cases.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
