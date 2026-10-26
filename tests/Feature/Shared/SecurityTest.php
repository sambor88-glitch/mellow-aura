<?php

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_page_says_how_browsers_may_treat_it_and_https_pages_keep_to_https(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_behind_cloudflare_the_customer_s_own_address_counts_and_a_faked_header_does_not(): void
    {
        Route::get('/test-address', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '162.158.10.20'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/test-address')
            ->assertSeeText('203.0.113.7');

        // Straight to the server, not through Cloudflare: the header is someone's invention.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/test-address')
            ->assertSeeText('198.51.100.9');
    }
}
