<?php

namespace App\Modules\Shared\Seo;

/**
 * Head data of one page: title, description, canonical, robots and JSON-LD.
 *
 * Built in the controller from the page's own data and handed to <x-shared::layout :seo="...">.
 * The site-wide LocalBusiness block is added by the layout, not here.
 */
final readonly class Seo
{
    public string $title;

    public string $description;

    /**
     * @param  list<string>|string  $title  options from the fullest to the shortest, see SeoText::title()
     * @param  string|null  $path  canonical path; null means the current path without the query string
     * @param  string|null  $robots  e.g. "noindex, nofollow"; null leaves indexing to the defaults
     * @param  list<array<string, mixed>>  $schema  page JSON-LD blocks (Product, BreadcrumbList, ...)
     */
    public function __construct(
        array|string $title,
        ?string $description = null,
        public ?string $path = null,
        public ?string $robots = null,
        public string $type = 'website',
        public array $schema = [],
    ) {
        $this->title = SeoText::title($title);
        $this->description = SeoText::description($description);
    }

    /**
     * Absolute and on the one domain from APP_URL, whatever host the request came on.
     */
    public function canonical(): string
    {
        $path = '/'.ltrim($this->path ?? request()->path(), '/');

        return rtrim((string) config('app.url'), '/').($path === '/' ? '/' : rtrim($path, '/'));
    }
}
