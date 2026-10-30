<?php

namespace App\Modules\Gifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Gifts\Actions\RedeemGiftChoice;
use App\Modules\Gifts\Enums\GiftChoiceRefusal;
use App\Modules\Gifts\Exceptions\GiftChoiceNotAccepted;
use App\Modules\Gifts\Http\Requests\RedeemGiftChoiceRequest;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * /wybierz-kubek (MA-235): the person a gift choice card is for types its code, or comes from the link in her mail
 * or the QR code on the card, both signed. There she writes her text, picks the inside glaze and says where to send
 * the mug; the choice places the order (Actions\RedeemGiftChoice). A used or late card says so, and whom to write to.
 * Polish only, like the rest of „Podaruj wybór”, and kept out of Google: the pages are only for card holders.
 */
class GiftChoiceController extends Controller
{
    public function index(): View
    {
        return view('gifts::choice.index');
    }

    public function find(Request $request, RedeemGiftChoice $redeem): RedirectResponse
    {
        $code = is_string($typed = $request->input('code')) ? trim($typed) : '';

        if ($code === '') {
            return back()->withInput()->withErrors(['code' => __('gifts::choice.refusal.empty')]);
        }

        try {
            $choice = $redeem->find($code);
        } catch (GiftChoiceNotAccepted $refused) {
            return back()->withInput()->withErrors(['code' => $refused->refusal->message()]);
        }

        return redirect()->to($choice->chooseUrl());
    }

    public function show(string $choice, RedeemGiftChoice $redeem, MugOptions $options, ShippingMethods $shipping, Settings $settings): View|Response
    {
        $card = GiftChoice::query()->issued()->where('code', VoucherCode::normalize($choice))->first();
        abort_if($card === null, 404);

        try {
            $redeem->find($card->code);
        } catch (GiftChoiceNotAccepted $refused) {
            return response()->view('gifts::choice.closed', [
                'choice' => $card->load('fulfilmentOrder.items'),
                'refusal' => $refused->refusal,
                'chosen' => $refused->refusal === GiftChoiceRefusal::Chosen ? $card->fulfilmentOrder?->items->first() : null,
                'contactEmail' => $settings->get('contact_email'),
            ]);
        }

        abort_if($options->glazes()->isEmpty() || $shipping->all()->isEmpty(), 404);

        return view('gifts::choice.show', [
            'choice' => $card,
            'options' => $options,
            'methods' => $shipping->all(),
            'action' => URL::signedRoute('gift-choice.redeem', ['choice' => $card->code]),
        ]);
    }

    public function redeem(RedeemGiftChoiceRequest $request, string $choice, RedeemGiftChoice $redeem): RedirectResponse
    {
        try {
            $redeem($choice, $request->validated('text'), $request->glaze(), $request->delivery());
        } catch (GiftChoiceNotAccepted $refused) {
            return back()->withInput()->with('gift_choice_notice', $refused->refusal->message());
        }

        return redirect()->to(URL::signedRoute('gift-choice.show', ['choice' => VoucherCode::normalize($choice)]))->with('gift_choice_chosen', true);
    }
}
