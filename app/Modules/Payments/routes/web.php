<?php

use App\Modules\Payments\Http\Controllers\Przelewy24NotificationController;
use Illuminate\Support\Facades\Route;

// Przelewy24 calls this address, not a person, so it skips the CSRF token (bootstrap/app.php) and the staging
// password (ProtectStaging::OPEN). The signature with the CRC key is what proves who is calling.
Route::post('/przelewy24/powiadomienie', Przelewy24NotificationController::class)->name('payments.przelewy24.notify');
