<?php

use App\Modules\Settings\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/ustawienia', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/ustawienia/dostawa', [SettingsController::class, 'shipping'])->name('settings.shipping');
Route::put('/ustawienia/pracownia', [SettingsController::class, 'studio'])->name('settings.studio');
Route::put('/ustawienia/firma', [SettingsController::class, 'company'])->name('settings.company');
Route::put('/ustawienia/materialy', [SettingsController::class, 'materials'])->name('settings.materials');
Route::put('/ustawienia/statystyki', [SettingsController::class, 'analytics'])->name('settings.analytics');
