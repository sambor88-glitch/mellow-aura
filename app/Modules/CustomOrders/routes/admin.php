<?php

use App\Modules\CustomOrders\Http\Controllers\Admin\CustomOrderRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia-indywidualne', [CustomOrderRequestController::class, 'index'])->name('custom-orders.index');
Route::patch('/zamowienia-indywidualne/{customOrderRequest}', [CustomOrderRequestController::class, 'update'])->name('custom-orders.update');
// The customer's photos stay on the private disk; the panel streams them only to a signed-in owner.
Route::get('/zamowienia-indywidualne/{customOrderRequest}/zdjecia/{photo}', [CustomOrderRequestController::class, 'photo'])
    ->whereNumber('photo')
    ->name('custom-orders.photo');
