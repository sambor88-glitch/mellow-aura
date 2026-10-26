<?php

namespace App\Modules\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * The launch gate from MA-114, run against the live site from outside, the way Google sees it: headers, robots.txt,
 * one host, the sitemap, a 200 and a title of its own on every page in it, and links instead of click handlers.
 * Staging must say noindex everywhere; production must not say it anywhere. Production also keeps /en/ off
 * (APP_LOCALES=pl, MA-186) until EU VAT, the English terms and EU shipping are settled; --english lifts that.
 *
 *   php artisan seo:launch-check https://mellow-aura.com
 *   php artisan seo:launch-check https://mellowaura-dev.on-forge.com --staging
 */
class LaunchCheckCommand extends Command
{
    protected $signature = 'seo:launch-check
        {url : Adres strony, np. https://mellow-aura.com}
        {--staging : Sprawdź staging — wtedy noindex i hasło są wymagane, a nie zakazane}
        {--auth= : login:hasło do stagingu, żeby sprawdzić strony za hasłem}
        {--limit=200 : Najwięcej adresów z mapy strony do sprawdzenia}
        {--english : Wersja angielska jest już włączona na produkcji — nie wymagaj 404 pod /en}';

    protected $description = 'Brama startowa SEO: nagłówki, robots.txt, jedna domena, mapa strony i tytuły';

    /** @var list<array{string, bool, string}> */
    private array $results = [];

    public function handle(Http $http): int
    {
        $base = rtrim((string) $this->argument('url'), '/');
        $staging = (bool) $this->option('staging');
        $anonymous = fn () => $http->withoutRedirecting()->timeout(20)->withHeaders(['User-Agent' => 'MellowAura launch check']);
        [$user, $password] = array_pad(explode(':', (string) $this->option('auth'), 2), 2, '');
        $client = fn () => $user !== '' ? $anonymous()->withBasicAuth($user, $password) : $anonymous();

        $home = $client()->get($base.'/');
        $noindexHome = $this->saysNoindex($home);
        $staging
            ? $this->record('Staging ma noindex w nagłówku X-Robots-Tag', str_contains(strtolower((string) $home->header('X-Robots-Tag')), 'noindex'), (string) $home->header('X-Robots-Tag'))
            : $this->record('Produkcja nie ma noindex', ! $noindexHome, $noindexHome ? 'noindex w nagłówku albo w <meta name="robots">' : 'brak');

        if (! $staging && ! $this->option('english')) {
            $english = $client()->get($base.'/en');
            $this->record('Wersja angielska wyłączona (APP_LOCALES=pl)', $english->status() === 404, '/en → kod '.$english->status());
        }

        if ($staging) {
            $stranger = $anonymous()->get($base.'/');
            $this->record('Staging pyta o hasło', $stranger->status() === 401, 'bez hasła kod '.$stranger->status());
        }

        // robots.txt matters on production only; staging is kept out by the password and noindex.
        $robots = $client()->get($base.'/robots.txt');
        $disallowed = collect(preg_split('/\R/', $robots->body()))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => Str::startsWith(strtolower($line), 'disallow:'))
            ->map(fn (string $line) => trim(substr($line, strlen('disallow:'))))
            ->filter()
            ->values();
        if (! $staging) {
            $this->record('robots.txt wyklucza tylko /panel', $robots->successful() && $disallowed->all() === ['/panel'], $robots->successful() ? 'Disallow: '.$disallowed->join(', ') : 'kod '.$robots->status());
        }

        $host = (string) parse_url($base, PHP_URL_HOST);
        $twin = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
        if (! $staging) {
            $other = rescue(fn () => $client()->get(str_replace('://'.$host, '://'.$twin, $base).'/'), report: false);
            $target = (string) $other?->header('Location');
            $this->record('Druga wersja domeny przekierowuje 301', $other?->status() === 301 && parse_url($target, PHP_URL_HOST) === $host, $other ? $other->status().' → '.($target ?: 'brak') : 'adres '.$twin.' nie odpowiada');
        }

        $sitemap = $client()->get($base.'/sitemap.xml');
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#', $sitemap->body(), $matches);
        $urls = collect($matches[1])->map(fn (string $url) => html_entity_decode($url))->unique()->values();
        $foreign = $urls->reject(fn (string $url) => parse_url($url, PHP_URL_HOST) === $host);
        $this->record('sitemap.xml odpowiada i ma adresy tej domeny', $sitemap->successful() && $urls->isNotEmpty() && $foreign->isEmpty(),
            $sitemap->successful() ? $urls->count().' adresów'.($foreign->isNotEmpty() ? ', z innej domeny: '.$foreign->take(3)->join(', ') : '') : 'kod '.$sitemap->status());

        $broken = [];
        $noindexed = [];
        $titles = [];
        $clickHandlers = [];
        foreach ($urls->take((int) $this->option('limit')) as $url) {
            $page = $client()->get($url);

            if ($page->status() !== 200) {
                $broken[] = $url.' ('.$page->status().')';

                continue;
            }

            if (! $staging && $this->saysNoindex($page)) {
                $noindexed[] = $url;
            }

            preg_match('#<title>(.*?)</title>#s', $page->body(), $title);
            $titles[$url] = trim(html_entity_decode($title[1] ?? ''));

            if (preg_match('#<a\b[^>]*\bonclick=#i', $page->body())) {
                $clickHandlers[] = $url;
            }
        }

        $duplicates = collect($titles)->filter()->duplicates()->unique();
        $untitled = collect($titles)->filter(fn (string $title) => $title === '')->keys();
        $this->record('Każdy adres z mapy odpowiada 200', $broken === [], $broken ? implode(', ', array_slice($broken, 0, 5)) : count($titles).' stron');
        $this->record('Każda strona ma własny tytuł', $duplicates->isEmpty() && $untitled->isEmpty(), match (true) {
            $duplicates->isNotEmpty() => 'powtórzone: '.$duplicates->take(3)->map(fn (string $title) => '„'.$title.'”')->join(', '),
            $untitled->isNotEmpty() => 'bez tytułu: '.$untitled->take(3)->join(', '),
            default => 'bez powtórzeń',
        });
        if (! $staging) {
            $this->record('Mapa strony nie ma stron z noindex', $noindexed === [], $noindexed ? implode(', ', array_slice($noindexed, 0, 5)) : 'brak');
        }
        $this->record('Przejścia to linki <a href>, nie onclick', $clickHandlers === [], $clickHandlers ? implode(', ', array_slice($clickHandlers, 0, 5)) : 'brak onclick w linkach');

        $this->table(['', 'Punkt', 'Wynik'], array_map(fn (array $row) => [$row[1] ? '✓' : '✗', $row[0], $row[2]], $this->results));
        $this->line('Search Console, Bing i przekierowania ze SumUp (punkty 7–8) sprawdź ręcznie.');

        return collect($this->results)->every(fn (array $row) => $row[1]) ? self::SUCCESS : self::FAILURE;
    }

    private function saysNoindex(Response $response): bool
    {
        return str_contains(strtolower((string) $response->header('X-Robots-Tag')), 'noindex')
            || (bool) preg_match('#<meta\s+name="robots"\s+content="[^"]*noindex#i', $response->body());
    }

    private function record(string $check, bool $passed, string $detail): void
    {
        $this->results[] = [$check, $passed, $detail];
    }
}
