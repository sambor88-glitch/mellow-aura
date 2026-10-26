<?php

namespace App\Modules\CustomOrders\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\CustomOrders\Actions\SendPaymentLink;
use App\Modules\CustomOrders\Http\Requests\Admin\SendPaymentLinkRequest;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;

/**
 * „Wyślij link do płatności” on an idea in the panel (MA-144): the amount Kasia typed goes to the customer by mail.
 */
class PaymentLinkController extends Controller
{
    public function store(SendPaymentLinkRequest $request, CustomOrderRequest $customOrderRequest, SendPaymentLink $send): RedirectResponse
    {
        $link = $send($customOrderRequest, $request->amount());

        return to_route('admin.custom-orders.index')
            ->withFragment('pomysl-'.$customOrderRequest->id)
            ->with('panel_status', 'Link na '.Money::format($link->amount).' poszedł do '.$customOrderRequest->email.'.');
    }
}
