<?php

use App\Modules\Gifts\Http\Controllers\VoucherPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/vouchery/{voucher}', VoucherPdfController::class)->whereNumber('voucher')->name('vouchers.pdf');
