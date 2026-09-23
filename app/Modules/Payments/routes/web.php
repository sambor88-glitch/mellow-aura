<?php

use App\Modules\Payments\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Stripe calls this address, not a person, so it keeps an English name and skips the CSRF token
// (the exception is in bootstrap/app.php). The signature in the header is what proves who is calling.
Route::post('/stripe/webhook', StripeWebhookController::class)->name('payments.stripe.webhook');
