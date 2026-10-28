<?php

use App\Modules\Checkout\Http\Controllers\Admin\CertificateController;
use App\Modules\Checkout\Http\Controllers\Admin\CertificateTextsController;
use App\Modules\Checkout\Http\Controllers\Admin\ComplaintController;
use App\Modules\Checkout\Http\Controllers\Admin\OrderController;
use App\Modules\Checkout\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
Route::get('/zamowienia/{order:number}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('/zamowienia/{order:number}/status', [OrderController::class, 'status'])->name('orders.status');
Route::patch('/zamowienia/{order:number}/wplata', [OrderController::class, 'paid'])->name('orders.paid');
Route::get('/zamowienia/{order:number}/certyfikaty', CertificateController::class)->name('orders.certificates');
Route::get('/certyfikat', [CertificateTextsController::class, 'edit'])->name('certificate.edit');
Route::put('/certyfikat', [CertificateTextsController::class, 'update'])->name('certificate.update');
Route::put('/certyfikat/podglad', [CertificateTextsController::class, 'preview'])->name('certificate.preview');
Route::get('/odstapienia', [WithdrawalController::class, 'index'])->name('withdrawals.index');
Route::get('/reklamacje', [ComplaintController::class, 'index'])->name('complaints.index');
Route::post('/reklamacje', [ComplaintController::class, 'store'])->name('complaints.store');
Route::patch('/odstapienia/{withdrawal}', [WithdrawalController::class, 'update'])->name('withdrawals.update');
