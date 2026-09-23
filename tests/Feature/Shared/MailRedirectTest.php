<?php

namespace Tests\Feature\Shared;

use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_sends_a_shop_mail_to_one_inbox_and_the_subject_names_the_original_recipients(): void
    {
        config(['mail.redirect_to' => 'kasia@example.com']);

        Mail::to('pracownia@example.com')->cc('ola@example.com')->bcc('ukryta@example.com')
            ->send(new ContactMessageReceived('Anna Nowak', 'anna@example.com', null, 'Czy kubek można myć w zmywarce?'));

        $message = $this->sentMessage();

        $this->assertSame(['kasia@example.com'], $this->addresses($message->getTo()));
        $this->assertSame([], $message->getCc());
        $this->assertSame([], $message->getBcc());
        $this->assertSame(['anna@example.com'], $this->addresses($message->getReplyTo()));
        $this->assertSame('[do: pracownia@example.com, ola@example.com, ukryta@example.com] Wiadomość ze strony — Anna Nowak', $message->getSubject());
    }

    public function test_a_technical_alert_keeps_its_address_on_staging(): void
    {
        config(['mail.redirect_to' => 'kasia@example.com', 'monitoring.alert_email' => 'opiekun@example.com']);

        app(Alerts::class)->send('queue-silent', 'Kolejka nie działa od 12 min', 'Zadań w kolejce: 3');

        $message = $this->sentMessage();

        $this->assertSame(['opiekun@example.com'], $this->addresses($message->getTo()));
        $this->assertSame('[MellowAura · testing] Kolejka nie działa od 12 min', $message->getSubject());
    }

    public function test_without_the_setting_every_mail_goes_to_its_own_recipients(): void
    {
        Mail::to('pracownia@example.com')->send(new ContactMessageReceived('Anna Nowak', 'anna@example.com', 'Warsztaty', 'Czy są wolne miejsca?'));

        $message = $this->sentMessage();

        $this->assertSame(['pracownia@example.com'], $this->addresses($message->getTo()));
        $this->assertSame('Wiadomość ze strony: Warsztaty — Anna Nowak', $message->getSubject());
    }

    private function sentMessage(): Email
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->getAddress(), $addresses);
    }
}
