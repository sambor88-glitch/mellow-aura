<?php

namespace App\Modules\Shared\Support;

use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for the breadcrumbs on a page. The trail has to match the links the page shows.
 */
class BreadcrumbStructuredData
{
    /**
     * @param  list<array{string, string}>  $trail  [name, URL] pairs from the top of the site down to the current page
     */
    public static function for(array $trail): string
    {
        return Schema::breadcrumbList()->itemListElement(array_map(
            fn (array $crumb, int $index) => Schema::listItem()->position($index + 1)->name($crumb[0])->item($crumb[1]),
            $trail,
            array_keys($trail),
        ))->toScript();
    }
}
