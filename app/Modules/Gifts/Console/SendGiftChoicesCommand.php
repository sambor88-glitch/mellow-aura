<?php

namespace App\Modules\Gifts\Console;

use App\Modules\Gifts\Actions\SendDueGiftChoices;
use Illuminate\Console\Command;

class SendGiftChoicesCommand extends Command
{
    protected $signature = 'gifts:send-gift-choices';

    protected $description = 'Sends the gift choice cards due today and the reminders before their last day';

    public function handle(SendDueGiftChoices $send): int
    {
        ['sent' => $sent, 'reminded' => $reminded] = $send();
        $this->info('Cards sent: '.$sent.', reminders: '.$reminded);

        return self::SUCCESS;
    }
}
