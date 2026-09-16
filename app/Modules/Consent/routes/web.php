<?php

use App\Modules\Consent\Http\Controllers\ConsentController;
use Illuminate\Support\Facades\Route;

Route::get('/ustawienia-cookies', [ConsentController::class, 'edit'])->name('consent.edit');
Route::post('/ustawienia-cookies', [ConsentController::class, 'store'])->middleware('throttle:30,1')->name('consent.store');
