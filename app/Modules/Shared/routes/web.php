<?php

use App\Modules\Shared\Http\Controllers\CatalogFeedController;
use App\Modules\Shared\Http\Controllers\MerchantFeedController;
use App\Modules\Shared\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/google-merchant.xml', MerchantFeedController::class)->name('merchant-feed');
Route::get('/social-catalog.xml', CatalogFeedController::class)->name('catalog-feed');
