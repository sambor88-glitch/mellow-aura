<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Mail\PaymentLinkSent;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\CustomOrders\Models\PaymentLink;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends the customer a link to pay $amount of the quote (MA-144): what is left of it, or half of a big one first
 * (SendPaymentLinkRequest says which). One link at a time is open, so a link sent again cancels the one before. A new idea or one
 * being priced moves to „Zaliczka”, where it waits for the money.
 */
class SendPaymentLink
{
    public function __invoke(CustomOrderRequest $customOrderRequest, int $amount): PaymentLink
    {
        $link = DB::transaction(function () use ($customOrderRequest, $amount) {
            CustomOrderRequest::query()->lockForUpdate()->findOrFail($customOrderRequest->id);

            $customOrderRequest->paymentLinks()->where('status', PaymentLinkStatus::Open)->update(['status' => PaymentLinkStatus::Cancelled]);

            if (in_array($customOrderRequest->status, [CustomOrderStatus::New, CustomOrderStatus::Quoting], true)) {
                $customOrderRequest->update(['status' => CustomOrderStatus::Deposit]);
            }

            return $customOrderRequest->paymentLinks()->create([
                'token' => Str::random(40),
                'amount' => $amount,
                'status' => PaymentLinkStatus::Open,
                'expires_at' => now()->addDays(PaymentLink::VALID_DAYS)->endOfDay(),
            ]);
        });

        rescue(fn () => Mail::to(new Address($customOrderRequest->email, $customOrderRequest->name))->send(new PaymentLinkSent($link)));

        return $link;
    }
}
