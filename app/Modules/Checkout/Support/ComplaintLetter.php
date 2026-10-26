<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\ComplaintRemedy;
use App\Modules\Checkout\Enums\MediationConsent;
use App\Modules\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;

/**
 * The answer to a complaint in Kasia's words. A decision that leaves the dispute open always ends with her statement
 * on out-of-court proceedings and a pointer to §14 of the terms; the signature carries the seller's details.
 */
class ComplaintLetter
{
    private const TERMS_ADR_SECTION = 'regulamin-14-pozasadowe-rozwiazywanie-sporow';

    public function __construct(private Settings $settings) {}

    /**
     * @return list<string> paragraphs; the last one is the signature, its lines split by new lines
     */
    public function paragraphs(CarbonInterface $receivedOn, ?string $orderNumber, ComplaintDecision $decision, ?ComplaintRemedy $remedy, ?string $details, ?MediationConsent $mediation): array
    {
        $paragraphs = [
            'Dzień dobry,',
            'dziękuję za reklamację, która doszła do mnie '.$receivedOn->translatedFormat('j F Y').($orderNumber ? ' i dotyczy zamówienia '.$orderNumber : '').'.',
            trim($decision->sentence().($decision === ComplaintDecision::Accepted && $remedy ? ' '.$remedy->sentence() : '')),
        ];

        if (filled($details)) {
            $paragraphs[] = trim($details);
        }

        if ($decision->leavesDispute() && $mediation) {
            $paragraphs[] = $mediation->statement();

            if (Route::has('content.terms')) {
                $paragraphs[] = 'O innych sposobach dochodzenia roszczeń piszę w regulaminie, w §14: '.route('content.terms').'#'.self::TERMS_ADR_SECTION;
            }
        }

        $paragraphs[] = collect(['Pozdrawiam', 'Katarzyna Samborska', $this->settings->get('company_name'), $this->settings->get('company_address'), $this->settings->get('contact_email')])
            ->filter()
            ->join("\n");

        return $paragraphs;
    }
}
