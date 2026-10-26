<?php

use App\Modules\Workshops\Http\Controllers\BookingController;
use App\Modules\Workshops\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::get('/warsztaty-ceramiczne-krakow', WorkshopController::class)->name('workshops.index');
Route::get('/warsztaty-ceramiczne-krakow/zapis/potwierdzenie', [BookingController::class, 'confirmation'])->name('workshops.booking.confirmation');
Route::get('/warsztaty-ceramiczne-krakow/zapis/{date}', [BookingController::class, 'create'])->whereNumber('date')->name('workshops.booking.create');
Route::post('/warsztaty-ceramiczne-krakow/zapis/{date}', [BookingController::class, 'store'])->whereNumber('date')->middleware('throttle:10,1')->name('workshops.booking.store');
