<?php

use App\Modules\Content\Http\Controllers\Admin\ContentController;
use App\Modules\Content\Http\Controllers\Admin\ServiceExampleController;
use App\Modules\Content\Http\Controllers\Admin\ServicesController;
use Illuminate\Support\Facades\Route;

Route::get('/tresci', [ContentController::class, 'edit'])->name('content.edit');
Route::put('/tresci/teksty', [ContentController::class, 'texts'])->name('content.texts');
Route::put('/tresci/czeste-pytania', [ContentController::class, 'faq'])->name('content.faq');
Route::put('/tresci/pracownia', [ContentController::class, 'facts'])->name('content.facts');
Route::put('/tresci/zamowienia-indywidualne', [ContentController::class, 'customOrderSteps'])->name('content.custom-order-steps');
Route::put('/tresci/gastronomia', [ContentController::class, 'b2bFacts'])->name('content.b2b-facts');
Route::put('/tresci/kontakt', [ContentController::class, 'topics'])->name('content.topics');

Route::whereIn('service', ['apaszka', 'odcisk'])->group(function () {
    Route::get('/uslugi/{service?}', [ServicesController::class, 'edit'])->name('services.edit');
    Route::put('/uslugi/{service}/teksty', [ServicesController::class, 'texts'])->name('services.texts');
    Route::put('/uslugi/{service}/cennik', [ServicesController::class, 'prices'])->name('services.prices');
    Route::put('/uslugi/{service}/kroki', [ServicesController::class, 'steps'])->name('services.steps');
    Route::post('/uslugi/{service}/przed-i-po', [ServiceExampleController::class, 'store'])->name('services.examples.store');
});
Route::put('/uslugi/przed-i-po/{example}', [ServiceExampleController::class, 'update'])->whereNumber('example')->name('services.examples.update');
Route::post('/uslugi/przed-i-po/{example}/kolejnosc', [ServiceExampleController::class, 'move'])->whereNumber('example')->name('services.examples.move');
Route::delete('/uslugi/przed-i-po/{example}', [ServiceExampleController::class, 'destroy'])->whereNumber('example')->name('services.examples.destroy');
