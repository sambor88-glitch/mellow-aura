<?php

use App\Modules\Content\Http\Controllers\AboutController;
use App\Modules\Content\Http\Controllers\B2bController;
use App\Modules\Content\Http\Controllers\ContactController;
use App\Modules\Content\Http\Controllers\CustomOrdersController;
use App\Modules\Content\Http\Controllers\FaqController;
use App\Modules\Content\Http\Controllers\HomeController;
use App\Modules\Content\Http\Controllers\LegalController;
use App\Modules\Content\Http\Controllers\ServicePageController;
use App\Modules\Content\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/o-mnie', AboutController::class)->name('content.about');
Route::get('/pracownia', StudioController::class)->name('content.studio');
Route::get('/z-twojej-apaszki', [ServicePageController::class, 'scarf'])->name('content.scarf');
Route::get('/odcisk-twojej-rosliny', [ServicePageController::class, 'imprint'])->name('content.imprint');
// Named for the CustomOrders module, which takes the page over with the brief form after the holidays.
Route::get('/zamowienia-indywidualne', CustomOrdersController::class)->name('custom-orders.index');
Route::get('/ceramika-dla-gastronomii', B2bController::class)->name('content.b2b');
Route::get('/kontakt', [ContactController::class, 'show'])->name('content.contact');
Route::post('/kontakt', [ContactController::class, 'send'])->name('content.contact.send');
Route::get('/wysylka-i-pielegnacja', FaqController::class)->name('content.faq');
Route::get('/regulamin', [LegalController::class, 'terms'])->name('content.terms');
Route::get('/polityka-prywatnosci', [LegalController::class, 'privacy'])->name('content.privacy');
