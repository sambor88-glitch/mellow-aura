<?php

use App\Modules\Checkout\Http\Controllers\Admin\OrderController;
use App\Modules\Checkout\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
Route::get('/zamowienia/{order:number}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/odstapienia', [WithdrawalController::class, 'index'])->name('withdrawals.index');
Route::patch('/odstapienia/{withdrawal}', [WithdrawalController::class, 'update'])->name('withdrawals.update');
