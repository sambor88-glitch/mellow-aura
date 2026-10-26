<?php

use App\Modules\Firing\Http\Controllers\Admin\KilnPricesController;
use Illuminate\Support\Facades\Route;

Route::get('/wypaly', [KilnPricesController::class, 'edit'])->name('firing.edit');
Route::put('/wypaly', [KilnPricesController::class, 'update'])->name('firing.update');
