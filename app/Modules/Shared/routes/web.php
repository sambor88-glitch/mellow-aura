<?php

use App\Modules\Shared\Http\Controllers\CatalogFeedController;
use App\Modules\Shared\Http\Controllers\MerchantFeedController;
use App\Modules\Shared\Http\Controllers\MissingPageController;
use App\Modules\Shared\Http\Controllers\SitemapController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/google-merchant.xml', MerchantFeedController::class)->name('merchant-feed');
Route::get('/social-catalog.xml', CatalogFeedController::class)->name('catalog-feed');

// An address no route knows goes through the web middleware too, so the missing page shows the customer's own cart in
// the header (MA-259). Matched after every other route, whatever order the modules load in. Every method, not only GET
// like Route::fallback(): a POST to an unknown address stays a 404 instead of a 405, and skips the CSRF check, which
// would answer it with a 419.
Route::any('{fallbackPlaceholder}', MissingPageController::class)->where('fallbackPlaceholder', '.*')->fallback()
    ->withoutMiddleware(ValidateCsrfToken::class);
