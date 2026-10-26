<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Support\MerchantFeed;
use Illuminate\Http\Response;

/**
 * /google-merchant.xml: the product file Google Merchant Center fetches once a day. It is for Merchant Center,
 * not for search results.
 */
class MerchantFeedController extends Controller
{
    public function __invoke(MerchantFeed $feed): Response
    {
        return response($feed->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
