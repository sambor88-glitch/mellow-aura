<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\SubmitWithdrawal;
use App\Modules\Checkout\Http\Requests\SubmitWithdrawalRequest;
use App\Modules\Checkout\Models\Withdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * „Odstąp od umowy tutaj” (Directive 2023/2673, art. 11a): available all the time from the footer,
 * confirmed with one button, acknowledged on screen and by e-mail with the date and time.
 */
class WithdrawalController extends Controller
{
    private const ROBOTS = 'noindex, nofollow';

    /** Statements one address may send in an hour. A real customer never gets near it. */
    private const PER_HOUR = 5;

    public function create(Request $request): Response
    {
        $number = $request->query('zamowienie');

        return response()->view('checkout::withdrawal.create', [
            'orderNumber' => is_string($number) ? mb_substr($number, 0, 40) : null,
        ])->header('X-Robots-Tag', self::ROBOTS);
    }

    public function store(SubmitWithdrawalRequest $request, SubmitWithdrawal $submit): RedirectResponse
    {
        $limiterKey = 'withdrawal-form:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::PER_HOUR)) {
            return to_route('withdrawal.create')->withInput()->with('withdrawal_throttled', true);
        }

        RateLimiter::hit($limiterKey, 3600);

        $withdrawal = $submit($request->validated());
        $request->session()->put('withdrawal.id', $withdrawal->id);

        return to_route('withdrawal.confirmation');
    }

    public function confirmation(Request $request): Response|RedirectResponse
    {
        $id = $request->session()->get('withdrawal.id');
        $withdrawal = $id ? Withdrawal::query()->find($id) : null;

        if ($withdrawal === null) {
            return to_route('withdrawal.create');
        }

        return response()->view('checkout::withdrawal.confirmation', ['withdrawal' => $withdrawal])
            ->header('X-Robots-Tag', self::ROBOTS);
    }
}
