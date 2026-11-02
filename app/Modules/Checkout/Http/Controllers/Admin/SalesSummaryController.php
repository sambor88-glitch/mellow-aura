<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\SalesPeriod;
use App\Modules\Checkout\Support\SalesSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * „Podsumowanie sprzedaży” (MA-344): how the sales go, at a glance, for the owner only (PanelAccess).
 */
class SalesSummaryController extends Controller
{
    public function __invoke(Request $request, SalesSummary $summary): View
    {
        $period = SalesPeriod::tryFrom((string) $request->query('period')) ?? SalesPeriod::ThisMonth;

        return view('checkout::admin.sales.index', [
            ...$summary->for($period),
            'periods' => SalesPeriod::cases(),
        ]);
    }
}
