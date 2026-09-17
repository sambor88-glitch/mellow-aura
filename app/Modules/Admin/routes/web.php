<?php

use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/panel/logowanie', [LoginController::class, 'show'])->name('admin.login');
    Route::post('/panel/logowanie', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('admin.login.store');

    Route::get('/panel/haslo', [PasswordController::class, 'request'])->name('admin.password.request');
    Route::post('/panel/haslo', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('admin.password.email');
    Route::get('/panel/haslo/{token}', [PasswordController::class, 'edit'])->name('admin.password.reset');
    Route::post('/panel/haslo/nowe', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('admin.password.update');
});
