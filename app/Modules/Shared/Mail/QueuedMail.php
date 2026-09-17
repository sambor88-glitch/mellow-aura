<?php

namespace App\Modules\Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A mail the queue worker sends, so a slow or unavailable mail provider never holds up a payment or a form.
 * A failed attempt is repeated for about two hours. A mail that still does not go out lands on the panel's list
 * „Maile, które nie wyszły”, described in Kasia's words, with a button to send it again.
 */
abstract class QueuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** The first attempt and five more. */
    public $tries = 6;

    /**
     * Seconds before each next attempt: 1, 5, 15, 30 and 60 minutes.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    /**
     * What the mail is, as Kasia reads it on the list: „Potwierdzenie zamówienia MA-2026-1047”.
     */
    abstract public function description(): string;
}
