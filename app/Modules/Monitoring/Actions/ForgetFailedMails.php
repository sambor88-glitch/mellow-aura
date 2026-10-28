<?php

namespace App\Modules\Monitoring\Actions;

use App\Modules\Monitoring\Support\FailedMails;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * Removes the mails to a customer that never went out, when she asks to be forgotten (GDPR art. 17, MA-241).
 * A failed job keeps the whole mail with her address and order, so it goes from „Niewysłane maile” for good.
 */
class ForgetFailedMails
{
    public function __construct(private FailedMails $mails, private FailedJobProviderInterface $failer) {}

    /**
     * @return int how many mails were removed
     */
    public function __invoke(string $email): int
    {
        $email = PersonalData::normalize($email);

        return $this->mails->all()
            ->filter(fn (array $mail) => collect($mail['to'])->contains(fn (string $to) => PersonalData::normalize($to) === $email))
            ->each(fn (array $mail) => $this->failer->forget($mail['id']))
            ->count();
    }
}
