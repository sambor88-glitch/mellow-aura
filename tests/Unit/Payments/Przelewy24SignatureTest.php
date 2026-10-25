<?php

namespace Tests\Unit\Payments;

use App\Modules\Payments\Przelewy24\Signature;
use PHPUnit\Framework\TestCase;

class Przelewy24SignatureTest extends TestCase
{
    /**
     * The register example from Przelewy24's documentation („Wyliczanie sumy kontrolnej”). The expected hash
     * is SHA-384 of the exact JSON line the documentation builds, computed outside PHP.
     */
    public function test_it_signs_the_register_example_from_the_documentation(): void
    {
        $sign = Signature::make([
            'sessionId' => 'unikalne-id-sesji',
            'merchantId' => 999999,
            'amount' => 1234,
            'currency' => 'PLN',
        ], 'crc-z-panelu-p24');

        $this->assertSame('c5d47472de94f7d0f7b01d8048d51e7d2a0f3c025a246dd90301d2c2df83a3a7cdb2ffde27fc770a8fdd3a0f1a3c4423', $sign);
    }

    public function test_an_amount_sent_as_text_gives_another_signature(): void
    {
        $fields = ['sessionId' => 's', 'merchantId' => 1, 'amount' => 1234, 'currency' => 'PLN'];

        $this->assertNotSame(Signature::make($fields, 'crc'), Signature::make([...$fields, 'amount' => '1234'], 'crc'));
    }

    public function test_polish_letters_and_slashes_are_signed_as_they_are(): void
    {
        $this->assertSame(
            hash('sha384', '{"sessionId":"MA/ż","crc":"k"}'),
            Signature::make(['sessionId' => 'MA/ż'], 'k'),
        );
    }
}
