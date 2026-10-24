<?php

namespace Tests\Feature\Shared;

use App\Modules\Shared\Mail\StagingUpdated;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class NotifyStagingReviewTest extends TestCase
{
    private string $repository;

    private string $stateFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = sys_get_temp_dir().'/staging-review-'.uniqid();
        $this->stateFile = $this->repository.'-state/last-notified-commit';
        File::ensureDirectoryExists($this->repository);

        $this->git('init', '-q');
        $this->commit('Initial commit');

        config([
            'app.url' => 'https://staging.mellow-aura.test',
            'staging.review.to' => 'reviewer@example.com',
            'staging.review.reply_to' => 'owner@example.com',
            'staging.review.signature' => 'Maciej',
            'staging.review.repository' => $this->repository,
            'staging.review.state_file' => $this->stateFile,
        ]);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repository);
        File::deleteDirectory(dirname($this->stateFile));

        parent::tearDown();
    }

    public function test_first_run_remembers_head_without_sending(): void
    {
        $this->artisan('staging:notify-review')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame($this->headCommit(), trim(File::get($this->stateFile)));
    }

    public function test_sends_review_notes_from_new_commits(): void
    {
        $this->artisan('staging:notify-review');

        $this->commit("Add product page\n\nReview-Note: karta produktu ze zdjęciami i ceną");
        $this->commit('Fix deploy script');
        $this->commit("Add cart\n\nReview-Note: koszyk w szufladzie z boku");

        $this->artisan('staging:notify-review')->assertSuccessful();

        Mail::assertSent(StagingUpdated::class, function (StagingUpdated $mail) {
            return $mail->hasTo('reviewer@example.com')
                && $mail->hasReplyTo('owner@example.com')
                && $mail->notes === ['karta produktu ze zdjęciami i ceną', 'koszyk w szufladzie z boku'];
        });
        $this->assertSame($this->headCommit(), trim(File::get($this->stateFile)));
    }

    public function test_technical_deploy_sends_nothing_and_moves_marker(): void
    {
        $this->artisan('staging:notify-review');
        $this->commit('Remove font download from build');

        $this->artisan('staging:notify-review')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame($this->headCommit(), trim(File::get($this->stateFile)));
    }

    public function test_redeploy_of_same_commit_sends_nothing(): void
    {
        $this->artisan('staging:notify-review');
        $this->commit("Add cart\n\nReview-Note: koszyk");
        $this->artisan('staging:notify-review');

        $this->artisan('staging:notify-review')->assertSuccessful();

        Mail::assertSentCount(1);
    }

    public function test_failed_send_keeps_marker_and_does_not_fail_deploy(): void
    {
        $this->artisan('staging:notify-review');
        $before = $this->headCommit();
        $this->commit("Add cart\n\nReview-Note: koszyk");

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->artisan('staging:notify-review')->assertSuccessful();

        $this->assertSame($before, trim(File::get($this->stateFile)));
    }

    public function test_disabled_without_recipient(): void
    {
        config(['staging.review.to' => '']);

        $this->artisan('staging:notify-review')->assertSuccessful();

        $this->assertFileDoesNotExist($this->stateFile);
    }

    public function test_e_mail_lists_notes_and_site_address(): void
    {
        $mail = new StagingUpdated(['koszyk w szufladzie'], 'https://staging.mellow-aura.test', 'Maciej');

        $mail->assertSeeInText('- koszyk w szufladzie');
        $mail->assertSeeInText('Strona: https://staging.mellow-aura.test');
        $mail->assertSeeInText('Maciej');
    }

    public function test_e_mail_links_straight_to_app_url(): void
    {
        $mail = new StagingUpdated(['koszyk'], 'https://staging.mellow-aura.test');

        $mail->assertSeeInHtml('<a href="https://staging.mellow-aura.test">https://staging.mellow-aura.test</a>', false);
        $mail->assertDontSeeInHtml('google.com/url', false);
        $mail->assertDontSeeInText('google.com/url', false);
    }

    public function test_notes_are_escaped_in_html(): void
    {
        $mail = new StagingUpdated(['<script>alert(1)</script>'], 'https://staging.mellow-aura.test');

        $mail->assertDontSeeInHtml('<script>', false);
    }

    private function commit(string $message): void
    {
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '-q', '--allow-empty', '-m', $message);
    }

    private function headCommit(): string
    {
        return $this->git('rev-parse', 'HEAD');
    }

    private function git(string ...$arguments): string
    {
        return trim(Process::path($this->repository)->run(['git', ...$arguments])->throw()->output());
    }
}
