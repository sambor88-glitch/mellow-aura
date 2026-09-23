<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    /**
     * Forge runs `php artisan optimize` on every deploy, which compiles every Blade view.
     */
    public function test_every_view_compiles_like_during_a_deploy(): void
    {
        try {
            $this->artisan('view:cache')->assertSuccessful();
        } finally {
            $this->artisan('view:clear');
        }
    }

    /**
     * Staging and production send with MAIL_MAILER=postmark, which needs symfony/postmark-mailer and symfony/http-client.
     */
    public function test_the_postmark_mailer_can_be_built(): void
    {
        config(['services.postmark.key' => 'server-token']);

        $this->assertInstanceOf(PostmarkApiTransport::class, Mail::mailer('postmark')->getSymfonyTransport());
    }
}
