<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\ServiceStructuredData;
use Illuminate\View\View;

/**
 * /zamowienia-indywidualne: how a custom piece comes about, with the sentences and steps from the panel.
 * Until the brief form arrives with the CustomOrders module, the idea goes through WhatsApp or the contact form.
 */
class CustomOrdersController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        return view('content::custom-orders', [
            'lead' => $settings->get('text_custom_orders_lead'),
            'structuredData' => ServiceStructuredData::for('Ceramika na zamówienie', route('custom-orders.index'), $settings->get('text_custom_orders_lead')),
            'steps' => collect((array) $settings->get('custom_order_steps', []))
                ->filter(fn (mixed $step) => is_array($step) && filled($step['title'] ?? null))
                ->values(),
            'phone' => $settings->get('contact_phone'),
        ]);
    }
}
