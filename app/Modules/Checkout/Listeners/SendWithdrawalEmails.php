<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\WithdrawalSubmitted;
use App\Modules\Checkout\Mail\NewWithdrawalReceived;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * The law asks for an acknowledgement with the statement's content, date and time, sent without delay: the queue
 * worker sends it within seconds and repeats a failed attempt. Kasia gets the same statement. A mail that cannot be
 * queued is reported; the statement stays saved and listed in the panel either way.
 */
class SendWithdrawalEmails
{
    public function __construct(private Settings $settings) {}

    public function handle(WithdrawalSubmitted $event): void
    {
        $withdrawal = $event->withdrawal->loadMissing('order');

        rescue(fn () => Mail::to(new Address($withdrawal->email, $withdrawal->name))->send(new WithdrawalConfirmed($withdrawal)));

        if ($owner = $this->settings->get('contact_email')) {
            rescue(fn () => Mail::to($owner)->send(new NewWithdrawalReceived($withdrawal)));
        }
    }
}
