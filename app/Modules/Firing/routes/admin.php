<?php

use App\Modules\Firing\Http\Controllers\Admin\FiringRequestController;
use App\Modules\Firing\Http\Controllers\Admin\KilnPricesController;
use Illuminate\Support\Facades\Route;

Route::get('/wypaly', [KilnPricesController::class, 'edit'])->name('firing.edit');
Route::put('/wypaly', [KilnPricesController::class, 'update'])->name('firing.update');

Route::get('/wypaly/zgloszenia', [FiringRequestController::class, 'index'])->name('firing.requests.index');
Route::patch('/wypaly/zgloszenia/{firingRequest}', [FiringRequestController::class, 'update'])->name('firing.requests.update');
Route::patch('/wypaly/partie/{date}', [FiringRequestController::class, 'updateLoad'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('firing.requests.update-load');
