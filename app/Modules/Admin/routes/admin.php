<?php

use App\Modules\Admin\Http\Controllers\AccountController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use App\Modules\Admin\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('/wyloguj', [LoginController::class, 'destroy'])->name('logout');

Route::get('/konta', [AccountController::class, 'index'])->name('accounts.index');
Route::post('/konta', [AccountController::class, 'store'])->name('accounts.store');
Route::post('/konta/{user}/link', [AccountController::class, 'link'])->name('accounts.link');
Route::delete('/konta/{user}', [AccountController::class, 'destroy'])->name('accounts.destroy');
