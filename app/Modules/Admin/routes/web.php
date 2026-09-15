<?php

use App\Modules\Admin\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/panel/logowanie', [LoginController::class, 'show'])->name('admin.login');
    Route::post('/panel/logowanie', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('admin.login.store');
});
