<?php

use App\Modules\Content\Http\Controllers\Admin\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('/tresci', [ContentController::class, 'edit'])->name('content.edit');
Route::put('/tresci/teksty', [ContentController::class, 'texts'])->name('content.texts');
Route::put('/tresci/czeste-pytania', [ContentController::class, 'faq'])->name('content.faq');
Route::put('/tresci/pracownia', [ContentController::class, 'facts'])->name('content.facts');
Route::put('/tresci/kontakt', [ContentController::class, 'topics'])->name('content.topics');
