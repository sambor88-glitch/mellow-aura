<?php

namespace App\Modules\Admin\Support\Search;

/**
 * One line in the panel search: what it is, a line under it, a short note on the right (a status, an amount),
 * and the screen it opens. $route is the route of that screen, so an account that may not open it never sees the line.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public string $route,
        public ?string $detail = null,
        public ?string $meta = null,
        public ?string $image = null,
        public ?string $icon = null,
    ) {}

    /**
     * @return array{title: string, url: string, detail: ?string, meta: ?string, image: ?string, icon: ?string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'detail' => $this->detail,
            'meta' => $this->meta,
            'image' => $this->image,
            'icon' => $this->icon,
        ];
    }
}
