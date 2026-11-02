<?php

namespace App\Modules\Admin\Support\Push;

use InvalidArgumentException;
use RuntimeException;

/**
 * The two pieces of Web Push, on PHP's own OpenSSL (MA-345): the message encrypted for one device (RFC 8291,
 * aes128gcm) and the VAPID signature that tells the push service the message comes from this shop (RFC 8292).
 * No package: the usual one needs Guzzle 7, and Laravel 13 runs on Guzzle 8.
 *
 * Keys travel as base64url, as browsers give them: the public one is the 65-byte point 04‖x‖y on P-256,
 * the private one the 32-byte number d.
 */
final class WebPush
{
    /** One record holds the whole message; a notification is far below it. */
    private const RECORD_SIZE = 4096;

    /** DER around a raw P-256 public point (SubjectPublicKeyInfo). */
    private const PUBLIC_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * A new pair for VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.
     *
     * @return array{public: string, private: string}
     */
    public static function createKeys(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'])
            ?: throw new RuntimeException('OpenSSL could not make a P-256 key.');
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'public' => self::encode(self::point($ec)),
            'private' => self::encode(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * The request body for one device: salt, record size, the server's one-time public key and the ciphertext.
     *
     * @param  string  $devicePublicKey  `keys.p256dh` from the browser
     * @param  string  $deviceAuth  `keys.auth` from the browser
     */
    public static function encrypt(string $message, string $devicePublicKey, string $deviceAuth): string
    {
        $uaPublic = self::decode($devicePublicKey);
        $authSecret = self::decode($deviceAuth);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) < 16) {
            throw new InvalidArgumentException('The browser gave keys that are not a P-256 point and a 16-byte secret.');
        }

        // A key of the server's own, for this one message only.
        $local = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'])
            ?: throw new RuntimeException('OpenSSL could not make a P-256 key.');
        $asPublic = self::point(openssl_pkey_get_details($local)['ec']);

        $shared = openssl_pkey_derive(self::publicKey($uaPublic), $local, 32)
            ?: throw new RuntimeException('OpenSSL could not agree a key with the device.');

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 closes the last (and only) record.
        $ciphertext = openssl_encrypt($message."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('OpenSSL could not encrypt the message.');
        }

        return $salt.pack('N', self::RECORD_SIZE).chr(strlen($asPublic)).$asPublic.$ciphertext.$tag;
    }

    /**
     * The Authorization header for one push service: a JWT signed with the private key, valid for 12 hours,
     * for the service's own address.
     */
    public static function authorization(string $endpoint, string $publicKey, string $privateKey, string $subject): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        $header = self::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::encode(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));
        $signed = $header.'.'.$claims;

        if (! openssl_sign($signed, $der, self::privateKey($privateKey, $publicKey), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('OpenSSL could not sign the VAPID token — check VAPID_PRIVATE_KEY.');
        }

        return 'vapid t='.$signed.'.'.self::encode(self::rawSignature($der)).', k='.$publicKey;
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }

    /** PEM of a raw public point, for OpenSSL. */
    public static function publicKey(string $point): string
    {
        return self::pem('PUBLIC KEY', hex2bin(self::PUBLIC_PREFIX).$point);
    }

    /**
     * @param  array{x: string, y: string}  $ec
     */
    private static function point(array $ec): string
    {
        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** The SEC1 private key OpenSSL reads, built from d and the public point. */
    private static function privateKey(string $private, string $public): string
    {
        $d = self::decode($private);
        $point = self::decode($public);

        if (strlen($d) !== 32 || strlen($point) !== 65) {
            throw new InvalidArgumentException('VAPID keys are not a P-256 pair — make them with php artisan admin:vapid-keys.');
        }

        return self::pem('EC PRIVATE KEY', hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$point);
    }

    /** ES256 in a JWT is r‖s, 32 bytes each; OpenSSL gives DER. */
    private static function rawSignature(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }
}
