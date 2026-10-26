<?php

namespace App\Modules\Firing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firing\Actions\ReportBatch;
use App\Modules\Firing\Http\Requests\ReportBatchRequest;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Firing\Support\FiringQuote;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Firing\Support\LoadDays;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * /wypal-ceramiki-krakow: the firing price list from the panel and the batch form with the quote from it.
 * Kasia and the customer get the batch by mail.
 */
class FiringController extends Controller
{
    /** Batches one address may report in an hour, so a script can't flood Kasia's inbox. */
    private const REPORTS_PER_HOUR = 5;

    public function show(KilnPrices $prices, LoadDays $loadDays, Settings $settings): View
    {
        $list = $prices->all();
        $lead = $settings->get('text_kiln_lead');
        $reported = session('firing_reported') ? FiringRequest::query()->find(session('firing_reported')) : null;

        return view('firing::index', [
            'prices' => $list,
            'lead' => $lead,
            'structuredData' => ServiceStructuredData::for('Wypał ceramiki na zlecenie w Krakowie', route('firing.index'), $lead,
                $list->map(fn (array $price) => [$price['label'], $price['price_gross'], $price['unit_label']])),
            'note' => $settings->get('text_kiln_note'),
            'phone' => $settings->get('contact_phone'),
            // What the form's quote counts with, the same as FiringQuote on the server.
            'services' => $list->map(fn (array $price) => [
                'code' => KilnPrices::key($price),
                'label' => $price['label'],
                'price' => $price['price_gross'],
                'unit' => $price['unit'] === null || in_array($price['unit'], FiringQuote::BY_AMOUNT, true) ? $price['unit'] : 'other',
            ])->values(),
            'loadDays' => $loadDays->upcoming(),
            'reported' => $reported,
        ]);
    }

    public function store(ReportBatchRequest $request, ReportBatch $reportBatch): RedirectResponse
    {
        // A bot is sent back to the form and nothing is saved.
        if ($request->isFromBot()) {
            return $this->backToForm();
        }

        $limiterKey = 'firing-form:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::REPORTS_PER_HOUR)) {
            return $this->backToForm()->withInput()->with('firing_throttled', true);
        }

        $batch = $reportBatch($request->service(), $request->quantity(), $request->safe()->only(['name', 'email', 'phone', 'drop_off_on', 'message']));
        RateLimiter::hit($limiterKey, 3600);

        return $this->backToForm()->with('firing_reported', $batch->id);
    }

    /** After a reload the page opens at the form, so the answer is on screen, also on a phone. */
    private function backToForm(): RedirectResponse
    {
        return to_route('firing.index')->withFragment('zglos-wsad');
    }
}
