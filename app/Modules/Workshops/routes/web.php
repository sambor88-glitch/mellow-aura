<?php

use App\Modules\Workshops\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::get('/warsztaty-ceramiczne-krakow', WorkshopController::class)->name('workshops.index');
