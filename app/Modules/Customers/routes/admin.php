<?php

use App\Modules\Customers\Http\Controllers\Admin\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/klienci', [CustomerController::class, 'index'])->name('customers.index');
Route::get('/klienci/{customer}', [CustomerController::class, 'show'])->name('customers.show');
Route::post('/klienci/{customer}/kopia-danych', [CustomerController::class, 'export'])->name('customers.export');
Route::delete('/klienci/{customer}', [CustomerController::class, 'anonymize'])->name('customers.anonymize');
