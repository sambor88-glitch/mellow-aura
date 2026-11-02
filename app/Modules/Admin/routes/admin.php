<?php

use App\Modules\Admin\Http\Controllers\AccountController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\LoginController;
use App\Modules\Admin\Http\Controllers\PushSubscriptionController;
use App\Modules\Admin\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('/wyloguj', [LoginController::class, 'destroy'])->name('logout');
// The search at the top of every screen (MA-308); each account gets results only from the screens it may open.
Route::get('/szukaj', SearchController::class)->name('search');

Route::get('/konta', [AccountController::class, 'index'])->name('accounts.index');
Route::post('/konta', [AccountController::class, 'store'])->name('accounts.store');
Route::post('/konta/{user}/link', [AccountController::class, 'link'])->name('accounts.link');
Route::delete('/konta/{user}', [AccountController::class, 'destroy'])->name('accounts.destroy');

// Notifications on the phone (MA-345): every account turns them on for its own devices.
Route::get('/powiadomienia', [PushSubscriptionController::class, 'index'])->name('push.index');
Route::post('/powiadomienia', [PushSubscriptionController::class, 'store'])->middleware('throttle:30,1')->name('push.store');
Route::post('/powiadomienia/wylacz', [PushSubscriptionController::class, 'forget'])->name('push.forget');
Route::post('/powiadomienia/proba', [PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('push.test');
Route::delete('/powiadomienia/{subscription}', [PushSubscriptionController::class, 'destroy'])->whereNumber('subscription')->name('push.destroy');
