<?php

use App\Modules\Settings\Http\Controllers\Admin\MailSignatureImageController;
use App\Modules\Settings\Http\Controllers\Admin\MailTextsController;
use App\Modules\Settings\Http\Controllers\Admin\SeoPagesController;
use App\Modules\Settings\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/ustawienia', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/ustawienia/dostawa', [SettingsController::class, 'shipping'])->name('settings.shipping');
Route::put('/ustawienia/pracownia', [SettingsController::class, 'studio'])->name('settings.studio');
Route::put('/ustawienia/firma', [SettingsController::class, 'company'])->name('settings.company');
Route::put('/ustawienia/karta-produktu', [SettingsController::class, 'productCard'])->name('settings.product-card');
Route::put('/ustawienia/statystyki', [SettingsController::class, 'analytics'])->name('settings.analytics');
Route::put('/ustawienia/opinie', [SettingsController::class, 'reviews'])->name('settings.reviews');

Route::get('/google', [SeoPagesController::class, 'edit'])->name('seo-pages.edit');
Route::put('/google', [SeoPagesController::class, 'update'])->name('seo-pages.update');

Route::get('/maile', [MailTextsController::class, 'edit'])->name('mail-texts.edit');
Route::put('/maile', [MailTextsController::class, 'update'])->name('mail-texts.update');
Route::put('/maile/podglad', [MailTextsController::class, 'preview'])->name('mail-texts.preview');
Route::post('/maile/grafika', [MailSignatureImageController::class, 'store'])->name('mail-signature-image.store');
Route::delete('/maile/grafika', [MailSignatureImageController::class, 'destroy'])->name('mail-signature-image.destroy');
