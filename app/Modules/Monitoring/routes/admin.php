<?php

use App\Modules\Monitoring\Http\Controllers\Admin\FailedMailController;
use Illuminate\Support\Facades\Route;

Route::get('/niewyslane-maile', [FailedMailController::class, 'index'])->name('failed-mails.index');
Route::post('/niewyslane-maile/wyslij-wszystkie', [FailedMailController::class, 'retryAll'])->name('failed-mails.retry-all');
Route::post('/niewyslane-maile/{id}/wyslij', [FailedMailController::class, 'retry'])->whereUuid('id')->name('failed-mails.retry');
Route::delete('/niewyslane-maile/{id}', [FailedMailController::class, 'destroy'])->whereUuid('id')->name('failed-mails.destroy');
