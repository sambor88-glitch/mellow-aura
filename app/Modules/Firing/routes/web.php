<?php

use App\Modules\Firing\Http\Controllers\FiringController;
use Illuminate\Support\Facades\Route;

Route::get('/wypal-ceramiki-krakow', [FiringController::class, 'show'])->name('firing.index');
Route::post('/wypal-ceramiki-krakow', [FiringController::class, 'store'])->name('firing.store');
