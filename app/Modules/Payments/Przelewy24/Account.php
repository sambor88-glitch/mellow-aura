<?php

namespace App\Modules\Payments\Przelewy24;

/**
 * The shop's account at Przelewy24, read from the env. All four values come from the merchant panel:
 * the merchant id, the shop (POS) id, the CRC key for signatures and the reports key the API logs in with.
 * Without any one of them the shop cannot take a payment, so it must not pretend it can.
 */
class Account
{
    public static function configured(): bool
    {
        return self::merchantId() > 0 && self::posId() > 0 && filled(self::crc()) && filled(self::apiKey());
    }

    public static function merchantId(): int
    {
        return (int) config('services.przelewy24.merchant_id');
    }

    /** Przelewy24 calls it the shop id; for a merchant with one shop it is the merchant id again. */
    public static function posId(): int
    {
        return (int) (config('services.przelewy24.pos_id') ?: self::merchantId());
    }

    public static function crc(): string
    {
        return (string) config('services.przelewy24.crc');
    }

    public static function apiKey(): string
    {
        return (string) config('services.przelewy24.api_key');
    }

    /** Sandbox until the env says otherwise, so a forgotten setting never charges a real card. */
    public static function baseUrl(): string
    {
        return config('services.przelewy24.sandbox', true)
            ? 'https://sandbox.przelewy24.pl'
            : 'https://secure.przelewy24.pl';
    }
}
