<?php

namespace App\Modules\Gifts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Gifts\Actions\ResolveGiftChoice;
use App\Modules\Gifts\Actions\SendGiftChoice;
use App\Modules\Gifts\Enums\GiftChoiceResolution;
use App\Modules\Gifts\Http\Requests\Admin\SaveGiftChoiceSettingsRequest;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Support\GiftChoiceTerms;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Karty wyboru” in the panel (MA-235): the paid cards that wait for a choice, the ones past their last day for Kasia
 * to decide on, and the chosen ones, newest first. A card can be sent again; after the deadline it becomes a plain
 * mug or a voucher. The card's terms — days to choose and the delivery in its price — save on the same screen.
 */
class GiftChoicesController extends Controller
{
    public function index(Request $request, GiftChoiceTerms $terms, Settings $settings): View
    {
        $filter = in_array($request->query('pokaz'), ['wszystkie'], true) ? 'all' : 'open';

        return view('gifts::admin.gift-choices', [
            'awaiting' => GiftChoice::query()->awaitingDecision()->with('orderItem.order')->orderBy('choose_by')->get(),
            'choices' => GiftChoice::query()
                ->issued()
                ->when($filter === 'open', fn ($query) => $query->whereNull('chosen_at')->whereNull('resolution')->where('choose_by', '>=', today()->toDateString()))
                ->with(['orderItem.order', 'fulfilmentOrder', 'voucher'])
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filter' => $filter,
            'days' => $terms->days(),
            'delivery' => $settings->get('gift_choice_delivery_gross'),
            'deliveryGross' => $terms->deliveryGross(),
        ]);
    }

    public function settings(SaveGiftChoiceSettingsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return redirect()->to(route('admin.gift-choices.index').'#ustawienia')->with('panel_status', 'Zapisane. Nowe karty już tak działają.');
    }

    public function resend(GiftChoice $choice, SendGiftChoice $send): RedirectResponse
    {
        abort_if($choice->code === null, 404);

        $send($choice);

        return back()->with('panel_status', $choice->goesByEmail()
            ? 'Karta '.$choice->code.' wysłana jeszcze raz na '.$choice->recipient_email.'.'
            : 'Karta '.$choice->code.' w PDF wysłana jeszcze raz do kupującej.');
    }

    public function resolve(Request $request, GiftChoice $choice, ResolveGiftChoice $resolve): RedirectResponse
    {
        $resolution = GiftChoiceResolution::from($request->validate(['resolution' => ['required', Rule::enum(GiftChoiceResolution::class)]])['resolution']);

        if (! $resolve($choice, $resolution)) {
            return back()->with('panel_status', 'Karta '.$choice->code.' jest już załatwiona albo wciąż czeka na wybór.');
        }

        return back()->with('panel_status', $resolution === GiftChoiceResolution::Voucher
            ? 'Karta '.$choice->code.' zamieniona na voucher — kupująca dostała go mailem.'
            : 'Zapisane: kubek bez napisu. Ustal adres z kupującą.');
    }
}
