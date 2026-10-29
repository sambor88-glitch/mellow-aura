<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Support\MerchantFeed;
use Illuminate\Http\Response;

/**
 * /social-catalog.xml: the product file the Meta catalog (Instagram Shopping) and Pinterest Catalogs fetch.
 * It stays open on staging, because neither can send the staging password.
 */
class CatalogFeedController extends Controller
{
    public function __invoke(MerchantFeed $feed): Response
    {
        return response($feed->catalogXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
