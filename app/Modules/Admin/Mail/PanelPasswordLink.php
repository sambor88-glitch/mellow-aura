<?php

namespace App\Modules\Admin\Mail;

use App\Models\User;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A link to set a panel password: a new one after „Nie pamiętasz hasła?”, or the first one when the owner adds an account.
 * The link works as long as the password broker allows (auth.passwords.users.expire).
 */
class PanelPasswordLink extends QueuedMail
{
    public function __construct(public User $user, public string $token, public bool $invitation = false) {}

    public function description(): string
    {
        return ($this->invitation ? 'Zaproszenie do panelu dla ' : 'Link do nowego hasła w panelu dla ').$this->user->email;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->invitation ? 'Dostęp do panelu MellowAury' : 'Nowe hasło do panelu MellowAury');
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin::mail.password-link',
            text: 'admin::mail.password-link-text',
            with: [
                'url' => route('admin.password.reset', ['token' => $this->token, 'email' => $this->user->email]),
                'minutes' => (int) config('auth.passwords.users.expire', 60),
            ],
        );
    }
}
