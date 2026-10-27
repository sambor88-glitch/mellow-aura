<?php

use App\Modules\Workshops\Http\Controllers\Admin\WorkshopBookingController;
use App\Modules\Workshops\Http\Controllers\Admin\WorkshopDateController;
use App\Modules\Workshops\Http\Controllers\Admin\WorkshopPricesController;
use Illuminate\Support\Facades\Route;

Route::get('/warsztaty', [WorkshopPricesController::class, 'edit'])->name('workshops.edit');
Route::put('/warsztaty', [WorkshopPricesController::class, 'update'])->name('workshops.update');
Route::post('/warsztaty/terminy', [WorkshopDateController::class, 'store'])->name('workshops.dates.store');
Route::delete('/warsztaty/terminy/{date}', [WorkshopDateController::class, 'destroy'])->whereNumber('date')->name('workshops.dates.destroy');
Route::get('/warsztaty/terminy/{date}/zapisy', [WorkshopBookingController::class, 'index'])->whereNumber('date')->name('workshops.dates.bookings');
Route::post('/warsztaty/zapisy/{booking}/anuluj', [WorkshopBookingController::class, 'cancel'])->whereNumber('booking')->name('workshops.bookings.cancel');
