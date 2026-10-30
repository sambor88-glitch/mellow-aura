<?php

use App\Modules\Gifts\Http\Controllers\BundleController;
use App\Modules\Gifts\Http\Controllers\GiftChoiceController;
use App\Modules\Gifts\Http\Controllers\GiftFinderController;
use App\Modules\Gifts\Http\Controllers\VoucherPageController;
use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/prezenty', GiftFinderController::class)->name('gifts.index');
Route::get('/zestawy-prezentowe', BundleController::class)->name('bundles.index');
Route::get('/voucher-na-warsztaty-ceramiczne', VoucherPageController::class)->name('vouchers.index');

// The code alone is worth money, so the link only works signed, as the confirmation page makes it.
Route::get('/voucher/{voucher:code}', VoucherPdfController::class)->middleware('signed')->name('vouchers.pdf');

// „Podaruj wybór” (MA-235): a typed code is checked a few times a minute at most, so nobody tries them all;
// the page of a card opens only from a signed link — the mail, the QR code or the form below.
Route::get('/wybierz-kubek', [GiftChoiceController::class, 'index'])->name('gift-choice.index');
Route::post('/wybierz-kubek', [GiftChoiceController::class, 'find'])->middleware('throttle:10,1')->name('gift-choice.find');
Route::get('/wybierz-kubek/{choice}', [GiftChoiceController::class, 'show'])->middleware('signed')->name('gift-choice.show');
Route::post('/wybierz-kubek/{choice}', [GiftChoiceController::class, 'redeem'])->middleware(['signed', 'throttle:10,1'])->name('gift-choice.redeem');
