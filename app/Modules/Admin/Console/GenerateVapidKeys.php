<?php

namespace App\Modules\Admin\Console;

use App\Modules\Admin\Support\Push\WebPush;
use Illuminate\Console\Command;

/**
 * Makes the pair of VAPID keys for the panel's notifications (MA-345) and prints it for Forge → Environment.
 * The keys are never written to a file here: they go only to .env on the server. Run it once — a new pair
 * switches the notifications off on every phone that has them.
 */
class GenerateVapidKeys extends Command
{
    /** @var string */
    protected $signature = 'admin:vapid-keys';

    /** @var string */
    protected $description = 'Make the VAPID key pair for the panel notifications and print it for .env';

    public function handle(): int
    {
        if (filled(config('services.webpush.public_key'))) {
            $this->warn('VAPID keys are already set. A new pair switches the notifications off on every phone that has them.');

            if (! $this->confirm('Make a new pair anyway?')) {
                return self::SUCCESS;
            }
        }

        $keys = WebPush::createKeys();

        $this->line('Paste these lines into Forge → site → Environment, then Deploy and restart the queue worker:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['public']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['private']);
        $this->line('VAPID_SUBJECT=https://mellow-aura.com');
        $this->newLine();
        $this->line('Keep the private key out of chats, Jira and the repository.');

        return self::SUCCESS;
    }
}
