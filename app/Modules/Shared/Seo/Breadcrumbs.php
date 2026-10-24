<?php

namespace App\Modules\Shared\Seo;

/**
 * Breadcrumbs shown as links on the page and sent as BreadcrumbList.
 * The last crumb is the current page and has no URL.
 */
final class Breadcrumbs
{
    /**
     * @param  array<int, array{0: string, 1: string|null}>  $items  [label, url]
     */
    public function __construct(public readonly array $items) {}

    public static function home(): self
    {
        return new self([['Strona główna', route('home')]]);
    }

    public function add(string $label, ?string $url = null): self
    {
        return new self([...$this->items, [$label, $url]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $item, int $index) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item[0],
                    'item' => $item[1] ?? url()->current(),
                ]),
                $this->items,
                array_keys($this->items),
            ),
        ];
    }
}
