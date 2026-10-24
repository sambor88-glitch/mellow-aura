<?php

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_is_open_without_staging_password(): void
    {
        config(['staging.password' => '']);

        $this->get('/')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_staging_asks_for_password_and_is_noindex(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->get('/')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_staging_rejects_wrong_password(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->withBasicAuth('mellowaura', 'wrong')->get('/')->assertUnauthorized();
    }

    public function test_staging_lets_in_with_password_and_stays_noindex(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->withBasicAuth('mellowaura', 'secret')
            ->get('/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
