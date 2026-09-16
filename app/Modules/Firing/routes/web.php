<?php

use App\Modules\Firing\Http\Controllers\FiringController;
use Illuminate\Support\Facades\Route;

Route::get('/wypal-ceramiki-krakow', FiringController::class)->name('firing.index');
