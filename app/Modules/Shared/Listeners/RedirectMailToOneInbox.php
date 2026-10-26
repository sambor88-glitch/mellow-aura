<?php

namespace App\Modules\Shared\Listeners;

use App\Modules\Shared\Mail\KeepsRecipients;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * On staging every mail goes to one inbox, so a test order with a made-up or a stranger's address reaches nobody
 * but Kasia. The subject names the original recipients. Laravel's own global "to" address would take the alerts too.
 */
class RedirectMailToOneInbox
{
    public function handle(MessageSending $event): void
    {
        $inbox = config('mail.redirect_to');
        $mailable = $event->data['__laravel_mailable'] ?? null;

        if (blank($inbox) || (is_string($mailable) && is_a($mailable, KeepsRecipients::class, true))) {
            return;
        }

        $message = $event->message;
        $recipients = collect([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()])
            ->map(fn (Address $address) => $address->getAddress())
            ->implode(', ');

        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
        $message->to($inbox);
        $message->subject('[do: '.$recipients.'] '.$message->getSubject());
    }
}
