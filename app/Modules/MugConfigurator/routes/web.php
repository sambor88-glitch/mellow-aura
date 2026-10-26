<?php

use App\Modules\MugConfigurator\Http\Controllers\MugController;
use Illuminate\Support\Facades\Route;

Route::get('/kubek-z-napisem', MugController::class)->name('mug.index');
