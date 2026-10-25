<?php

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_is_open_without_a_staging_password(): void
    {
        config(['staging.password' => '']);

        $this->get('/up')->assertOk();
        $this->assertNotSame(401, $this->get('/')->status());
    }

    public function test_staging_asks_for_the_password_and_stays_noindex(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret', 'app.noindex' => true]);

        $this->get('/')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="MellowAura staging"')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->withBasicAuth('mellowaura', 'wrong')->get('/')->assertUnauthorized();
        $this->withBasicAuth('kasia', 'secret')->get('/')->assertUnauthorized();
    }

    public function test_the_right_login_and_password_let_in(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret', 'app.noindex' => true]);

        $response = $this->withBasicAuth('mellowaura', 'secret')->get('/');

        $this->assertNotSame(401, $response->status());
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_stripe_and_the_health_check_pass_without_a_password(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->get('/up')->assertOk();
        // The webhook answers on its own terms (a bad signature), not with the password prompt.
        $this->assertNotSame(401, $this->postJson('/stripe/webhook', [])->status());
    }
}
