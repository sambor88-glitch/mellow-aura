<?php

use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\PanelAppController;
use App\Modules\Admin\Http\Controllers\PasswordController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
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
// Outside the session and cookies too (MA-354): the browser fetches them whenever it likes, also between a form's POST
// and the page it redirects to. Through the session, that request would use up the flash meant for the page —
// „Zapisane…”, the errors under the fields, „Mam Twój pomysł…” — and the page would show nothing.
// A class here also drops its subclasses: StartSession covers the shop's own from Shared. Not the other way round —
// ValidateCsrfToken would miss PreventRequestForgery, the parent that Laravel 13 puts in the group.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
    Route::get('/panel/manifest.webmanifest', [PanelAppController::class, 'manifest'])->name('admin.app.manifest');
    Route::get('/panel/sw.js', [PanelAppController::class, 'worker'])->name('admin.app.worker');
    // The worker stores this page when it installs, so the browser fetches it on its own as well.
    Route::get('/panel/brak-polaczenia', [PanelAppController::class, 'offline'])->name('admin.app.offline');
});
