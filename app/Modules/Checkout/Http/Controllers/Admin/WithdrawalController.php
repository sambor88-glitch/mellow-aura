<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Models\Withdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Withdrawal statements in the panel, the ones still to settle first. It works without e-mail too,
 * so nothing gets lost while the mail isn't set up.
 */
class WithdrawalController extends Controller
{
    public function index(): View
    {
        return view('checkout::admin.withdrawals.index', [
            'withdrawals' => Withdrawal::query()
                ->with('order')
                ->orderByRaw('handled_at IS NOT NULL')
                ->latest('submitted_at')
                ->paginate(30),
        ]);
    }

    public function update(Withdrawal $withdrawal): RedirectResponse
    {
        $withdrawal->update(['handled_at' => $withdrawal->handled_at ? null : now()]);

        return to_route('admin.withdrawals.index')->with('panel_status', $withdrawal->handled_at
            ? 'Załatwione — '.$withdrawal->order_number.' przeniesione na koniec listy.'
            : 'Przywrócone do załatwienia — '.$withdrawal->order_number.'.');
    }
}
