<?php

use App\Modules\Workshops\Http\Controllers\Admin\WorkshopPricesController;
use Illuminate\Support\Facades\Route;

Route::get('/warsztaty', [WorkshopPricesController::class, 'edit'])->name('workshops.edit');
Route::put('/warsztaty', [WorkshopPricesController::class, 'update'])->name('workshops.update');
