<?php

use App\Modules\MugConfigurator\Http\Controllers\Admin\MugSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/kubek-z-napisem', [MugSettingsController::class, 'edit'])->name('mug.edit');
Route::post('/kubek-z-napisem/zdjecie', [MugSettingsController::class, 'photo'])->name('mug.photo');
Route::delete('/kubek-z-napisem/zdjecie', [MugSettingsController::class, 'resetPhoto'])->name('mug.photo.reset');
Route::put('/kubek-z-napisem/rozmiary', [MugSettingsController::class, 'sizes'])->name('mug.sizes');
Route::put('/kubek-z-napisem/napis', [MugSettingsController::class, 'look'])->name('mug.look');
Route::put('/kubek-z-napisem/teksty', [MugSettingsController::class, 'texts'])->name('mug.texts');
Route::post('/kubek-z-napisem/z-bliska/{slot}', [MugSettingsController::class, 'letteringPhoto'])->whereIn('slot', ['closeup', 'fired'])->name('mug.lettering');
Route::delete('/kubek-z-napisem/z-bliska/{slot}', [MugSettingsController::class, 'removeLetteringPhoto'])->whereIn('slot', ['closeup', 'fired'])->name('mug.lettering.remove');
