<?php

namespace App\Modules\Workshops\Console;

use App\Modules\Workshops\Actions\SendReminders;
use Illuminate\Console\Command;

/**
 * Runs every hour from the scheduler, so the reminder goes out about a day before each workshop.
 */
class SendRemindersCommand extends Command
{
    protected $signature = 'workshops:send-reminders';

    protected $description = 'Wysyła przypomnienia o warsztatach, które zaczynają się w ciągu doby';

    public function handle(SendReminders $sendReminders): int
    {
        $this->info('Przypomnień w kolejce: '.$sendReminders());

        return self::SUCCESS;
    }
}
