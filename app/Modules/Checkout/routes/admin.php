<?php

use App\Modules\Checkout\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
Route::get('/zamowienia/{order:number}', [OrderController::class, 'show'])->name('orders.show');
