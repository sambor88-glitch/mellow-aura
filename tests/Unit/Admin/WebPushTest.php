<?php

namespace Tests\Unit\Admin;

use App\Modules\Admin\Support\Push\WebPush;
use PHPUnit\Framework\TestCase;

/**
 * Web Push on OpenSSL (MA-345): what the server encrypts, the browser has to read, and the push service has to
 * accept the signature. The test plays the browser and the push service.
 */
class WebPushTest extends TestCase
{
    public function test_a_new_pair_is_a_p256_point_and_its_secret(): void
    {
        $keys = WebPush::createKeys();

        $this->assertSame(65, strlen(WebPush::decode($keys['public'])));
        $this->assertSame("\x04", WebPush::decode($keys['public'])[0]);
        $this->assertSame(32, strlen(WebPush::decode($keys['private'])));
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $keys['public'].$keys['private']);
    }

    public function test_the_device_reads_the_message_with_its_own_keys(): void
    {
        // The browser's side: its key pair and the secret it gives with the subscription.
        $device = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $ec = openssl_pkey_get_details($device)['ec'];
        $uaPublic = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        $auth = random_bytes(16);

        $body = WebPush::encrypt('{"title":"Nowe zamówienie MA-2026-1001 · 255 zł"}', WebPush::encode($uaPublic), WebPush::encode($auth));

        // RFC 8291 from the receiving end.
        $salt = substr($body, 0, 16);
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $idLength = ord($body[20]);
        $asPublic = substr($body, 21, $idLength);
        $sealed = substr($body, 21 + $idLength);

        $shared = openssl_pkey_derive(WebPush::publicKey($asPublic), $device, 32);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($sealed, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($sealed, -16));

        $this->assertSame('{"title":"Nowe zamówienie MA-2026-1001 · 255 zł"}'."\x02", $plain);
    }

    public function test_the_push_service_can_check_the_signature_with_the_public_key(): void
    {
        $keys = WebPush::createKeys();

        $header = WebPush::authorization('https://web.push.apple.com/QGuQyavXutnMH', $keys['public'], $keys['private'], 'https://mellow-aura.com');

        $this->assertMatchesRegularExpression('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k='.preg_quote($keys['public'], '/').'$/', $header);
        preg_match('/t=([\w-]+)\.([\w-]+)\.([\w-]+)/', $header, $jwt);

        $claims = json_decode(WebPush::decode($jwt[2]), true);
        $this->assertSame('https://web.push.apple.com', $claims['aud']);
        $this->assertSame('https://mellow-aura.com', $claims['sub']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPush::decode($jwt[1]), true));

        $raw = WebPush::decode($jwt[3]);
        $this->assertSame(64, strlen($raw));
        $this->assertSame(1, openssl_verify($jwt[1].'.'.$jwt[2], $this->der($raw), WebPush::publicKey(WebPush::decode($keys['public'])), OPENSSL_ALGO_SHA256));
    }

    /** r‖s back to the DER that OpenSSL checks. */
    private function der(string $raw): string
    {
        $integer = function (string $part): string {
            $part = ltrim($part, "\0");

            if ($part === '' || ord($part[0]) & 0x80) {
                $part = "\0".$part;
            }

            return "\x02".chr(strlen($part)).$part;
        };
        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return "\x30".chr(strlen($body)).$body;
    }
}
