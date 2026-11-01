<?php

use App\Modules\Checkout\Http\Controllers\Admin\CertificateController;
use App\Modules\Checkout\Http\Controllers\Admin\CertificateTextsController;
use App\Modules\Checkout\Http\Controllers\Admin\ComplaintController;
use App\Modules\Checkout\Http\Controllers\Admin\GiftCardController;
use App\Modules\Checkout\Http\Controllers\Admin\OrderController;
use App\Modules\Checkout\Http\Controllers\Admin\PieceController;
use App\Modules\Checkout\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
Route::get('/zamowienia/{order:number}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('/zamowienia/{order:number}/status', [OrderController::class, 'status'])->name('orders.status');
Route::patch('/zamowienia/{order:number}/wplata', [OrderController::class, 'paid'])->name('orders.paid');
Route::get('/zamowienia/{order:number}/certyfikaty', CertificateController::class)->name('orders.certificates');
Route::get('/zamowienia/{order:number}/liscik', GiftCardController::class)->name('orders.gift-card');
Route::patch('/zamowienia/{order:number}/sztuka/{item}', [PieceController::class, 'update'])->whereNumber('item')->name('orders.piece');
Route::post('/zamowienia/{order:number}/sztuka/{item}/zdjecie', [PieceController::class, 'storePhoto'])->whereNumber('item')->name('orders.piece-photo');
Route::delete('/zamowienia/{order:number}/sztuka/{item}/zdjecie', [PieceController::class, 'destroyPhoto'])->whereNumber('item')->name('orders.piece-photo.destroy');
Route::get('/zamowienia/{order:number}/sztuka/{item}/karta', [PieceController::class, 'card'])->whereNumber('item')->name('orders.piece-card');
Route::get('/certyfikat', [CertificateTextsController::class, 'edit'])->name('certificate.edit');
Route::put('/certyfikat', [CertificateTextsController::class, 'update'])->name('certificate.update');
Route::put('/certyfikat/podglad', [CertificateTextsController::class, 'preview'])->name('certificate.preview');
Route::get('/odstapienia', [WithdrawalController::class, 'index'])->name('withdrawals.index');
Route::get('/reklamacje', [ComplaintController::class, 'index'])->name('complaints.index');
Route::post('/reklamacje', [ComplaintController::class, 'store'])->name('complaints.store');
Route::post('/reklamacje/nowa', [ComplaintController::class, 'record'])->name('complaints.record');
Route::patch('/odstapienia/{withdrawal}', [WithdrawalController::class, 'update'])->name('withdrawals.update');
