<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaunchCheckCommandTest extends TestCase
{
    private const SITEMAP = '<?xml version="1.0"?><urlset><url><loc>https://mellow-aura.com/</loc></url><url><loc>https://mellow-aura.com/sklep</loc></url></urlset>';

    public function test_a_healthy_production_passes_every_check(): void
    {
        Http::fake([
            'https://www.mellow-aura.com/' => Http::response('', 301, ['Location' => 'https://mellow-aura.com/']),
            'https://mellow-aura.com/robots.txt' => Http::response("User-agent: *\nDisallow: /panel\n"),
            'https://mellow-aura.com/sitemap.xml' => Http::response(self::SITEMAP),
            'https://mellow-aura.com/sklep' => Http::response('<title>Sklep — ceramika | MellowAura</title><a href="/sklep/kubki">Kubki</a>'),
            'https://mellow-aura.com/' => Http::response('<title>MellowAura — ceramika i rękodzieło z jedwabiu, Kraków</title>'),
        ]);

        $this->artisan('seo:launch-check', ['url' => 'https://mellow-aura.com'])
            ->expectsOutputToContain('Produkcja nie ma noindex')
            ->expectsOutputToContain('Druga wersja domeny przekierowuje 301')
            ->assertSuccessful();
    }

    public function test_noindex_shared_titles_a_closed_robots_and_two_hosts_stop_the_launch(): void
    {
        Http::fake([
            'https://www.mellow-aura.com/' => Http::response('<title>Kopia</title>'),
            'https://mellow-aura.com/robots.txt' => Http::response("User-agent: *\nDisallow: /\n"),
            'https://mellow-aura.com/sitemap.xml' => Http::response(self::SITEMAP.'<loc>https://mellowaura-dev.on-forge.com/sklep</loc>'),
            'https://mellowaura-dev.on-forge.com/*' => Http::response('<title>Sklep</title>'),
            'https://mellow-aura.com/sklep' => Http::response('<title>MellowAura</title><a onclick="go()">Dalej</a>'),
            'https://mellow-aura.com/' => Http::response('<title>MellowAura</title>', 200, ['X-Robots-Tag' => 'noindex, nofollow']),
        ]);

        $this->artisan('seo:launch-check', ['url' => 'https://mellow-aura.com'])
            ->expectsOutputToContain('noindex w nagłówku')
            ->expectsOutputToContain('Disallow: /')
            ->expectsOutputToContain('200 → brak')
            ->expectsOutputToContain('z innej domeny: https://mellowaura-dev.on-forge.com/sklep')
            ->expectsOutputToContain('powtórzone: „MellowAura”')
            ->expectsOutputToContain('https://mellow-aura.com/sklep')
            ->assertFailed();
    }

    public function test_staging_must_say_noindex_and_ask_for_a_password(): void
    {
        Http::fake([
            'https://mellowaura-dev.on-forge.com/sitemap.xml' => Http::response('<urlset><url><loc>https://mellowaura-dev.on-forge.com/</loc></url></urlset>'),
            'https://mellowaura-dev.on-forge.com/*' => Http::response('<title>MellowAura</title>', 200, ['X-Robots-Tag' => 'noindex, nofollow']),
        ]);

        $this->artisan('seo:launch-check', ['url' => 'https://mellowaura-dev.on-forge.com', '--staging' => true])
            ->expectsOutputToContain('Staging ma noindex w nagłówku X-Robots-Tag')
            ->expectsOutputToContain('bez hasła kod 200')
            ->assertFailed();
    }

    public function test_staging_behind_a_password_passes_with_the_password_given(): void
    {
        Http::fake(function ($request) {
            if (! $request->hasHeader('Authorization')) {
                return Http::response('', 401);
            }

            return str_ends_with($request->url(), '/sitemap.xml')
                ? Http::response('<urlset><url><loc>https://mellowaura-dev.on-forge.com/</loc></url></urlset>')
                : Http::response('<title>MellowAura</title>', 200, ['X-Robots-Tag' => 'noindex, nofollow']);
        });

        $this->artisan('seo:launch-check', ['url' => 'https://mellowaura-dev.on-forge.com', '--staging' => true, '--auth' => 'kasia:tajne'])
            ->expectsOutputToContain('bez hasła kod 401')
            ->assertSuccessful();
    }
}
