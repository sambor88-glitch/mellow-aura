<?php

namespace App\Modules\Content\Console;

use App\Modules\Content\Actions\RefreshInstagramFeed;
use App\Modules\Content\Exceptions\InstagramFeedUnavailable;
use Illuminate\Console\Command;

/**
 * Runs every hour from the scheduler, so new posts reach the home page without anyone opening the panel.
 */
class RefreshInstagramFeedCommand extends Command
{
    protected $signature = 'instagram:refresh';

    protected $description = 'Pobiera najnowsze zdjęcia z kanału Behold na stronę główną';

    public function handle(RefreshInstagramFeed $refresh): int
    {
        try {
            $this->info('Zdjęć na stronie głównej: '.$refresh());
        } catch (InstagramFeedUnavailable $exception) {
            $this->warn($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
