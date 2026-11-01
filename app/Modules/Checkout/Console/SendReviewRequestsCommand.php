<?php

namespace App\Modules\Checkout\Console;

use App\Modules\Checkout\Actions\SendReviewRequests;
use Illuminate\Console\Command;

/**
 * Runs once a day from the scheduler and asks the customers of the orders completed a few days ago for a Google
 * review (MA-329).
 */
class SendReviewRequestsCommand extends Command
{
    protected $signature = 'checkout:send-review-requests';

    protected $description = 'Wysyła prośby o opinię w Google do zamówień zakończonych kilka dni temu';

    public function handle(SendReviewRequests $sendReviewRequests): int
    {
        $this->info('Próśb o opinię w kolejce: '.$sendReviewRequests());

        return self::SUCCESS;
    }
}
