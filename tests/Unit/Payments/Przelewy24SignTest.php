<?php

namespace Tests\Unit\Payments;

use App\Modules\Payments\Support\Przelewy24Client;
use Tests\TestCase;

/**
 * The checksum against Przelewy24's own example, not against our own way of building it.
 *
 * The documentation (developers.przelewy24.pl, „Parameter checksum”) gives the JSON
 * {"sessionId":"sessionId","merchantId":999999,"amount":1000,"currency":"PLN","crc":"crc"} and hashes it with
 * SHA-384. The expected hashes were computed outside PHP, with the documentation's Python snippet (hashlib),
 * so a change in how we encode the JSON fails here even if every other test still agrees with itself.
 */
class Przelewy24SignTest extends TestCase
{
    public function test_the_sign_matches_the_example_from_przelewy24s_documentation(): void
    {
        config(['services.przelewy24.crc' => 'crc']);

        $sign = (new Przelewy24Client)->sign([
            'sessionId' => 'sessionId',
            'merchantId' => 999999,
            'amount' => 1000,
            'currency' => 'PLN',
        ]);

        $this->assertSame('a34cf822f00b51d8d75e2d21334d6ffb5f2fac8408574f4cd7d4897f07ed61312244f9875e18ce63a4fbe2668d7ffc30', $sign);
    }

    public function test_polish_letters_and_slashes_are_signed_unescaped_as_the_documentation_asks(): void
    {
        config(['services.przelewy24.crc' => 'crc']);

        $sign = (new Przelewy24Client)->sign([
            'sessionId' => 'MA-0001/żółć',
            'merchantId' => 999999,
            'amount' => 25550,
            'currency' => 'PLN',
        ]);

        $this->assertSame('327b4a8f1d049bf334d5fe846e66bba77b09bb4c8d9f3501fc13405126e9aa60ba2f2581ef5056040c92bf60fc31b517', $sign);
    }
}
