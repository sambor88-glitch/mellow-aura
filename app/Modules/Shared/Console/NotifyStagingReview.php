<?php

namespace App\Modules\Shared\Console;

use App\Modules\Shared\Mail\StagingUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Last step of the staging deploy: e-mails the reviewer what is new.
 *
 * Collects Review-Note trailers from commits since the last notified commit.
 * Purely technical deploys have no trailer and send nothing. A failed send
 * never fails the deploy; the marker stays put and the next deploy retries.
 */
class NotifyStagingReview extends Command
{
    protected $signature = 'staging:notify-review';

    protected $description = 'E-mail the staging reviewer the Review-Note trailers of newly deployed commits';

    public function handle(): int
    {
        $recipient = (string) config('staging.review.to');

        if ($recipient === '') {
            $this->info('STAGING_REVIEW_TO is empty, nothing to do.');

            return self::SUCCESS;
        }

        $head = $this->git(['rev-parse', 'HEAD']);

        if ($head === null) {
            $this->warn('Not a git checkout, skipping review e-mail.');

            return self::SUCCESS;
        }

        $stateFile = (string) config('staging.review.state_file');
        $lastNotified = File::exists($stateFile) ? trim(File::get($stateFile)) : '';

        if ($lastNotified === '') {
            $this->info('First run: remembering '.$head.', no e-mail.');
            $this->remember($stateFile, $head);

            return self::SUCCESS;
        }

        if ($lastNotified === $head) {
            $this->info('No new commits since the last e-mail.');

            return self::SUCCESS;
        }

        // Forge zero-downtime releases are shallow clones: fetch history until
        // the last notified commit is there, or log would see no commits.
        $this->deepenUntilKnown($lastNotified);

        $log = $this->git(['log', '--reverse', '--format=%(trailers:key=Review-Note,valueonly,unfold)', $lastNotified.'..'.$head]);

        if ($log === null) {
            $this->warn('Commit '.$lastNotified.' is no longer in history, starting over from '.$head.'.');
            $this->remember($stateFile, $head);

            return self::SUCCESS;
        }

        $notes = collect(preg_split('/\R/u', $log))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($notes === []) {
            $this->info('No Review-Note in the new commits, no e-mail.');
            $this->remember($stateFile, $head);

            return self::SUCCESS;
        }

        try {
            Mail::to($recipient)->send(new StagingUpdated(
                notes: $notes,
                siteUrl: (string) config('app.url'),
                signature: (string) config('staging.review.signature'),
                replyToAddress: (string) config('staging.review.reply_to'),
            ));
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('Review e-mail failed, will retry on the next deploy: '.$exception->getMessage());

            return self::SUCCESS;
        }

        $this->remember($stateFile, $head);
        $this->info('Review e-mail sent with '.count($notes).' note(s).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments): ?string
    {
        $result = Process::path((string) config('staging.review.repository'))->run(['git', ...$arguments]);

        return $result->successful() ? trim($result->output()) : null;
    }

    private function deepenUntilKnown(string $commit): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($this->git(['cat-file', '-e', $commit.'^{commit}']) !== null) {
                return;
            }

            if ($this->git(['rev-parse', '--is-shallow-repository']) !== 'true'
                || $this->git(['fetch', '--quiet', '--deepen=50']) === null) {
                return;
            }
        }
    }

    private function remember(string $stateFile, string $commit): void
    {
        File::ensureDirectoryExists(dirname($stateFile));
        File::put($stateFile, $commit.PHP_EOL);
    }
}
