<?php

namespace App\Modules\Consent\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Consent\Support\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /ustawienia-cookies: the same choice as in the banner, as a page that works without JavaScript.
 * The banner posts here too, in the background.
 */
class ConsentController extends Controller
{
    public function edit(Request $request, Consent $consent): Response
    {
        return response()->view('consent::edit', [
            'needed' => $consent->isNeeded(),
            'decided' => $consent->decided($request),
            'analytics' => $consent->allowsAnalytics($request),
            'decidedAt' => $consent->decidedAt($request),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, Consent $consent): JsonResponse|RedirectResponse
    {
        $analytics = $request->validate(['analytics' => ['required', 'in:0,1']])['analytics'] === '1';

        $response = $request->expectsJson()
            ? response()->json(['analytics' => $analytics])
            : redirect()->back(fallback: route('home'))->with('consent_saved', true);

        $response->withCookie($consent->cookie($analytics));

        if (! $analytics) {
            foreach ($consent->forgetAnalyticsCookies($request) as $cookie) {
                $response->withCookie($cookie);
            }
        }

        return $response;
    }
}
