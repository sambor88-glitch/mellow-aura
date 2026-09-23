<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Http\Requests\SendContactMessageRequest;
use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

/**
 * /kontakt: WhatsApp, Instagram and e-mail from the panel, and a form that mails Kasia. The studio's
 * address is never here — only the area from the panel.
 */
class ContactController extends Controller
{
    /** Messages one address may send in an hour, so a script can't flood Kasia's inbox. */
    private const MESSAGES_PER_HOUR = 5;

    public function show(): View
    {
        return view('content::contact');
    }

    public function send(SendContactMessageRequest $request, Settings $settings): RedirectResponse
    {
        // A bot gets the same answer as a person and nothing is sent.
        if ($request->isFromBot()) {
            return $this->backToForm()->with('contact_sent', true);
        }

        $limiterKey = 'contact-form:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::MESSAGES_PER_HOUR)) {
            return $this->backToForm()->withInput()->with('contact_throttled', true);
        }

        $data = $request->validated();

        try {
            $recipient = $settings->get('contact_email') ?: throw new \RuntimeException('No contact e-mail in the panel.');

            Mail::to($recipient)->send(new ContactMessageReceived($data['name'], $data['email'], $data['topic'] ?? null, $data['message']));
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToForm()->withInput()->with('contact_failed', true);
        }

        RateLimiter::hit($limiterKey, 3600);

        return $this->backToForm()->with('contact_sent', true);
    }

    /** After a reload the page opens at the form, so the answer is on screen, also on a phone. */
    private function backToForm(): RedirectResponse
    {
        return to_route('content.contact')->withFragment('formularz');
    }
}
