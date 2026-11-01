<?php

use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\PanelAppController;
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

// The panel as an app on the phone (MA-328). Outside the login: the browser fetches these on its own.
Route::get('/panel/manifest.webmanifest', [PanelAppController::class, 'manifest'])->name('admin.app.manifest');
Route::get('/panel/sw.js', [PanelAppController::class, 'worker'])->name('admin.app.worker');
Route::get('/panel/brak-polaczenia', [PanelAppController::class, 'offline'])->name('admin.app.offline');
