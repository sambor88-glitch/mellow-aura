<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Support\SitemapPages;
use Spatie\Sitemap\Sitemap;

/**
 * /sitemap.xml: every page the modules add, built on request.
 */
class SitemapController extends Controller
{
    public function __invoke(SitemapPages $pages): Sitemap
    {
        return $pages->build();
    }
}
