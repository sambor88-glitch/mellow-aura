<?php

namespace Tests\Feature\Checkout;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Tests\TestCase;

class PostmarkMailerTest extends TestCase
{
    public function test_postmark_transport_can_be_built_on_staging_and_production(): void
    {
        config(['services.postmark.key' => 'test-server-token']);

        $transport = Mail::mailer('postmark')->getSymfonyTransport();

        $this->assertInstanceOf(PostmarkApiTransport::class, $transport);
    }
}
