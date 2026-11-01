<?php

use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\PanelAppController;
use App\Modules\Admin\Http\Controllers\PasswordController;
use App\Modules\Shared\Http\Middleware\StartSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('guest')->group(function () {
    Route::get('/panel/logowanie', [LoginController::class, 'show'])->name('admin.login');
    Route::post('/panel/logowanie', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('admin.login.store');

    Route::get('/panel/haslo', [PasswordController::class, 'request'])->name('admin.password.request');
    Route::post('/panel/haslo', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('admin.password.email');
    Route::get('/panel/haslo/{token}', [PasswordController::class, 'edit'])->name('admin.password.reset');
    Route::post('/panel/haslo/nowe', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('admin.password.update');
});

// The panel as an app on the phone (MA-328). Outside the login: the browser fetches these on its own.
// Without the session too (MA-357): the browser asks for them at any moment, also between a form's POST and the
// redirect after it, and a request with the session would use up the flash message meant for the next page.
// ProtectStaging is global middleware, so the staging password still guards them as before.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
])->group(function () {
    Route::get('/panel/manifest.webmanifest', [PanelAppController::class, 'manifest'])->name('admin.app.manifest');
    Route::get('/panel/sw.js', [PanelAppController::class, 'worker'])->name('admin.app.worker');
    Route::get('/panel/brak-polaczenia', [PanelAppController::class, 'offline'])->name('admin.app.offline');
});
