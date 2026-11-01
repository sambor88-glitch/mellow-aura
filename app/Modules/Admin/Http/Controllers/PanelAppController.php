<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The panel as an app on Kasia's phone (MA-328): added to the home screen, it opens without the browser's bar.
 *
 * The three files sit outside the login: the browser fetches the manifest and the worker without asking anyone,
 * and they hold nothing but the panel's name, colours and a page that says the phone is offline.
 * The worker keeps only that page. No screen of the panel is ever stored on the phone — it shows customers'
 * names, addresses and orders, and a lost phone must not give them away.
 */
class PanelAppController extends Controller
{
    /** The worker controls every panel address, also /panel itself, which sits one level above its file. */
    public const SCOPE = '/panel';

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'MellowAura — mój panel',
            'short_name' => 'MellowAura',
            'description' => 'Zamówienia, produkty i sprawy na dziś.',
            'lang' => 'pl',
            'id' => self::SCOPE,
            'start_url' => self::SCOPE,
            'scope' => self::SCOPE,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#EDE4D8',
            'theme_color' => '#EDE4D8',
            'icons' => [
                ['src' => asset('icons/panel-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/panel-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/panel-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function worker(): Response
    {
        return response(view('admin::app.worker', ['offline' => route('admin.app.offline', absolute: false)])->render())
            ->header('Content-Type', 'text/javascript; charset=utf-8')
            // Always checked for a newer version, so a fix reaches the phone with the next open.
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', self::SCOPE);
    }

    public function offline(): Response
    {
        return response()->view('admin::app.offline')
            ->header('Cache-Control', 'no-cache');
    }
}
