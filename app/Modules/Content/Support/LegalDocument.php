<?php

namespace App\Modules\Content\Support;

use App\Modules\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

/**
 * The terms of sale and the privacy policy. Their text lives in resources/legal, taken from the draft for the
 * lawyer without the notes to the lawyer; the text the lawyer accepts replaces the file and gets a new version
 * and date below. A place marked data-fill takes the seller's details from the panel or an address on the site;
 * a place nobody has filled stays marked, so the page never makes a fact up.
 */
final class LegalDocument
{
    /** @var array<string, array{title: string, file: string, version: string, date: string, effective_from: ?string, draft: bool}> */
    private const DOCUMENTS = [
        'terms' => [
            'title' => 'Regulamin sklepu internetowego MellowAura',
            'file' => 'terms.html',
            'version' => '0.2',
            'date' => '2026-09-16',
            'effective_from' => null,
            'draft' => true,
        ],
        'privacy' => [
            'title' => 'Polityka prywatności MellowAura',
            'file' => 'privacy.html',
            'version' => '0.2',
            'date' => '2026-09-16',
            'effective_from' => null,
            'draft' => true,
        ],
    ];

    /**
     * @param  array{title: string, file: string, version: string, date: string, effective_from: ?string, draft: bool}  $meta
     */
    private function __construct(private readonly array $meta) {}

    public static function terms(): self
    {
        return new self(self::DOCUMENTS['terms']);
    }

    public static function privacy(): self
    {
        return new self(self::DOCUMENTS['privacy']);
    }

    public function title(): string
    {
        return $this->meta['title'];
    }

    /**
     * Until the lawyer's text arrives the page says it is a draft and stays out of search results.
     */
    public function isDraft(): bool
    {
        return $this->meta['draft'];
    }

    public function version(): string
    {
        return $this->meta['version'];
    }

    public function date(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->meta['date']);
    }

    public function effectiveFrom(): ?CarbonImmutable
    {
        return $this->meta['effective_from'] === null ? null : CarbonImmutable::parse($this->meta['effective_from']);
    }

    /**
     * What an order keeps as the accepted terms, e.g. „wersja 0.2 z 16 września 2026”.
     */
    public function versionLabel(): string
    {
        return 'wersja '.$this->version().' z '.$this->date()->translatedFormat('j F Y');
    }

    /**
     * The numbered sections, for the table of contents.
     *
     * @return list<array{id: string, title: string}>
     */
    public function sections(): array
    {
        preg_match_all('#<h3 class="sec" id="([^"]+)">(.*?)</h3>#s', $this->source(), $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => ['id' => $match[1], 'title' => html_entity_decode(strip_tags($match[2]))], $matches);
    }

    /**
     * The document's HTML with the seller's details put in. The text comes from the repository,
     * and every value from the panel is escaped.
     */
    public function html(Settings $settings): string
    {
        return (string) preg_replace_callback(
            '#<mark data-fill="([^"]+)">(.*?)</mark>#s',
            fn (array $match) => $this->fill($match[1], $settings) ?? '<mark>'.$match[2].'</mark>',
            $this->source(),
        );
    }

    private function fill(string $key, Settings $settings): ?string
    {
        if (str_starts_with($key, 'route:')) {
            $route = substr($key, strlen('route:'));

            return Route::has($route) ? '<a href="'.e(route($route)).'">'.e(route($route)).'</a>' : null;
        }

        if ($key === 'document:effective_from') {
            return $this->effectiveFrom() ? e($this->effectiveFrom()->translatedFormat('j F Y')) : null;
        }

        $value = $settings->get($key);

        if ($value === null) {
            return null;
        }

        return $key === 'contact_email'
            ? '<a href="mailto:'.e($value).'">'.e($value).'</a>'
            : e((string) $value);
    }

    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/resources/legal/'.$this->meta['file']);
    }
}
