<?php

namespace App\Modules\CustomOrders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomOrders\Actions\SendBrief;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Http\Requests\SendBriefRequest;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * /zamowienia-indywidualne: how a custom piece comes about, with the sentences and steps from the panel,
 * and the form for the idea with its photos. Kasia and the customer get the idea by mail.
 */
class CustomOrdersController extends Controller
{
    /** Ideas one address may send in an hour, so a script can't flood Kasia's inbox. */
    private const BRIEFS_PER_HOUR = 5;

    public function show(Request $request, Settings $settings): View
    {
        return view('custom-orders::index', [
            'lead' => $settings->get('text_custom_orders_lead'),
            'structuredData' => ServiceStructuredData::for('Ceramika na zamówienie', route('custom-orders.index'), $settings->get('text_custom_orders_lead')),
            'steps' => collect((array) $settings->get('custom_order_steps', []))
                ->filter(fn (mixed $step) => is_array($step) && filled($step['title'] ?? null))
                ->values(),
            'phone' => $settings->get('contact_phone'),
            // The form and its mails are Polish, so the English page keeps WhatsApp and the contact form.
            'withForm' => Locales::current() === 'pl',
            'kinds' => BriefKind::cases(),
            'chosenKind' => BriefKind::fromSlug($request->query('usluga')),
            'photoLimits' => SendBriefRequest::limits(),
            'sent' => session('brief_sent') ? CustomOrderRequest::query()->find(session('brief_sent')) : null,
        ]);
    }

    public function store(SendBriefRequest $request, SendBrief $sendBrief): RedirectResponse
    {
        // A bot is sent back to the form and nothing is saved.
        if ($request->isFromBot()) {
            return $this->backToForm();
        }

        $limiterKey = 'custom-order-form:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::BRIEFS_PER_HOUR)) {
            return $this->backToForm()->withInput()->with('brief_throttled', true);
        }

        $brief = $sendBrief(
            $request->kind(),
            $request->safe()->only(['name', 'email', 'phone', 'quantity', 'needed_by', 'budget', 'description']),
            $request->photos(),
        );
        RateLimiter::hit($limiterKey, 3600);

        return $this->backToForm()->with('brief_sent', $brief->id);
    }

    /** After a reload the page opens at the form, so the answer is on screen, also on a phone. */
    private function backToForm(): RedirectResponse
    {
        return to_route('custom-orders.index')->withFragment('formularz');
    }
}
