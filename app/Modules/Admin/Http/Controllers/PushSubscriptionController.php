<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\PushSubscription;
use App\Modules\Admin\Support\Push\PanelNotice;
use App\Modules\Admin\Support\Push\PanelPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * „Powiadomienia” (MA-345): each account turns the notifications on for its own phones and sees the list of them.
 * The browser asks for the permission and gives the keys; the server only keeps them, per account.
 */
class PushSubscriptionController extends Controller
{
    public function index(Request $request, PanelPush $push): View
    {
        return view('admin::push.index', [
            'publicKey' => $push->publicKey(),
            'devices' => PushSubscription::query()->where('user_id', $request->user()->id)->latest('id')->get(),
        ]);
    }

    /** From the button „Włącz powiadomienia na tym telefonie”, and again whenever the browser renews its keys. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            // aes128gcm is what every browser with Web Push speaks today; the older aesgcm is not sent.
            'contentEncoding' => ['nullable', 'in:aes128gcm'],
        ]);

        // The same browser signed in to another account now rings for this one.
        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => 'aes128gcm',
                'device' => PushSubscription::deviceName($request->userAgent()),
            ],
        );

        return response()->json(['id' => $subscription->id, 'device' => $subscription->device], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    /** „Wyłącz na tym telefonie”: the browser forgets its subscription and tells which one it was. */
    public function forget(Request $request): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:2048']])['endpoint'];

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hash($endpoint))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** „Usuń” on the list, e.g. for a phone that is lost or sold. */
    public function destroy(Request $request, PushSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);

        $subscription->delete();

        return to_route('admin.push.index')->with('panel_status', 'Usunięte — „'.$subscription->device.'” nie dostanie już powiadomień.');
    }

    /** „Wyślij próbne”: to this account's own devices only. */
    public function test(Request $request, PanelPush $push): JsonResponse|RedirectResponse
    {
        $push->notify(new PanelNotice(
            title: 'Próbne powiadomienie z panelu',
            body: 'Działa. Tak zadzwoni telefon, gdy przyjdzie nowe zamówienie.',
            url: route('admin.push.index'),
            route: 'admin.push.index',
            tag: 'test-'.$request->user()->id,
            onlyUser: $request->user()->id,
        ));

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : to_route('admin.push.index')->with('panel_status', 'Próbne powiadomienie wysłane — powinno przyjść w ciągu minuty.');
    }
}
