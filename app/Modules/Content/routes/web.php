<?php

use App\Modules\Content\Http\Controllers\ContactController;
use App\Modules\Content\Http\Controllers\HomeController;
use App\Modules\Content\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/kontakt', [ContactController::class, 'show'])->name('content.contact');
Route::post('/kontakt', [ContactController::class, 'send'])->name('content.contact.send');
Route::get('/regulamin', [LegalController::class, 'terms'])->name('content.terms');
Route::get('/polityka-prywatnosci', [LegalController::class, 'privacy'])->name('content.privacy');
