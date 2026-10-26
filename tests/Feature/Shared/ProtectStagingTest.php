<?php

namespace Tests\Feature\Shared;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_staging_password_the_site_is_open(): void
    {
        config(['staging.password' => '']);

        $this->get('/sklep')->assertOk();
    }

    public function test_staging_asks_for_the_password_and_is_never_indexed(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->get('/sklep')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="MellowAura staging", charset="UTF-8"')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->withBasicAuth('mellowaura', 'wrong')->get('/sklep')->assertUnauthorized();
        $this->withBasicAuth('kasia', 'secret')->get('/sklep')->assertUnauthorized();
    }

    public function test_the_right_login_and_password_open_the_site_and_the_panel(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->withBasicAuth('mellowaura', 'secret')->get('/sklep')->assertOk();
        $this->withBasicAuth('mellowaura', 'secret')->get('/panel')->assertRedirect(route('admin.login'));
    }

    public function test_the_health_check_passes_without_the_password(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->get('/up')->assertOk();
    }
}
