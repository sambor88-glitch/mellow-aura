<?php

use App\Modules\Gifts\Http\Controllers\BundleController;
use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/zestawy-prezentowe', BundleController::class)->name('bundles.index');

// The code alone is worth money, so the link only works signed, as the confirmation page makes it.
Route::get('/voucher/{voucher:code}', VoucherPdfController::class)->middleware('signed')->name('vouchers.pdf');
