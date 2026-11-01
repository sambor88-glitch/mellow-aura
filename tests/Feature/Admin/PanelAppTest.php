<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_manifest_opens_the_panel_as_an_app_without_a_login(): void
    {
        $response = $this->get('/panel/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $response->assertJson([
            'short_name' => 'MellowAura',
            'start_url' => '/panel',
            'scope' => '/panel',
            'display' => 'standalone',
            'lang' => 'pl',
        ]);

        $icons = collect($response->json('icons'));
        $this->assertEqualsCanonicalizing(['192x192', '512x512', '512x512'], $icons->pluck('sizes')->all());
        $this->assertContains('maskable', $icons->pluck('purpose')->all());

        foreach ($icons as $icon) {
            $this->assertFileExists(public_path(parse_url($icon['src'], PHP_URL_PATH)));
        }
    }

    public function test_the_worker_covers_the_panel_and_keeps_only_the_offline_page(): void
    {
        $response = $this->get('/panel/sw.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/panel')
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));

        $script = $response->getContent();
        $this->assertStringContainsString("const OFFLINE = '\\/panel\\/brak-polaczenia';", $script);
        $this->assertStringContainsString("event.request.mode !== 'navigate'", $script);
        // Nothing the panel shows is put into the cache: only the offline page is ever added.
        $this->assertSame(1, substr_count($script, 'cache.add('));
        $this->assertStringNotContainsString('cache.put(', $script);
        $this->assertStringNotContainsString('{{', $script);
    }

    public function test_the_offline_page_needs_no_login_and_holds_no_data(): void
    {
        $this->get('/panel/brak-polaczenia')
            ->assertOk()
            ->assertSee('Brak połączenia')
            ->assertSee('href="'.url('/panel').'"', false)
            ->assertDontSee('/build/', false)
            ->assertDontSee('Wyloguj');
    }

    public function test_the_panel_and_its_login_point_the_phone_to_the_app(): void
    {
        $this->get('/panel/logowanie')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="'.url('/panel/manifest.webmanifest').'" crossorigin="use-credentials">', false)
            ->assertSee('<meta name="theme-color" content="#EDE4D8">', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee("register('\\/panel\\/sw.js', { scope: '\\/panel' })", false);

        $this->actingAs(User::factory()->create())
            ->get('/panel')
            ->assertOk()
            ->assertSee('<link rel="manifest"', false);
    }

    public function test_the_app_files_leave_the_session_alone_so_a_flash_message_reaches_the_next_page(): void
    {
        // The state right after a form's POST answered with a redirect: the message waits for the very next request.
        $this->actingAs(User::factory()->create())
            ->withSession(['panel_status' => 'Zapisane. Klienci już to widzą.', '_flash' => ['old' => ['panel_status'], 'new' => []]]);

        // The browser fetches the worker, the manifest or the offline page before it follows the redirect (MA-357).
        foreach (['/panel/sw.js', '/panel/manifest.webmanifest', '/panel/brak-polaczenia'] as $address) {
            $this->get($address)
                ->assertOk()
                ->assertCookieMissing(config('session.cookie'))
                ->assertCookieMissing('XSRF-TOKEN');
        }

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Zapisane. Klienci już to widzą.');
    }

    public function test_staging_lets_the_phone_read_the_manifest_but_keeps_the_worker_behind_the_password(): void
    {
        config(['staging.password' => 'secret']);

        $this->get('/panel/manifest.webmanifest')->assertOk();
        $this->get('/panel/sw.js')->assertUnauthorized();
        $this->withBasicAuth('mellowaura', 'secret')->get('/panel/sw.js')->assertOk();
    }
}
