<?php

use App\Modules\Customers\Http\Controllers\Admin\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/klientki', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/klientki/{customer}', [CustomerController::class, 'show'])->name('customers.show');
Route::post('/klientki/{customer}/kopia-danych', [CustomerController::class, 'export'])->name('customers.export');
Route::delete('/klientki/{customer}', [CustomerController::class, 'anonymize'])->name('customers.anonymize');
