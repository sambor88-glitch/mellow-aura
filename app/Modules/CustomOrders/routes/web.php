<?php

use App\Modules\CustomOrders\Http\Controllers\CustomOrdersController;
use Illuminate\Support\Facades\Route;

Route::get('/zamowienia-indywidualne', [CustomOrdersController::class, 'show'])->name('custom-orders.index');
// Polish only: the English page has no form (see CustomOrdersController::show).
Route::post('/zamowienia-indywidualne', [CustomOrdersController::class, 'store'])->name('custom-orders.store');
