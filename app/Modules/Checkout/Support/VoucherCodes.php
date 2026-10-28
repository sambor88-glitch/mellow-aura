<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\Localization\Support\Locales;
use Illuminate\Http\Request;

/**
 * The amount vouchers the customer typed in at the checkout (MA-211), kept in the session until the cart is paid for.
 * Only the codes are kept; what they pay is counted again on every look, and for good in PlaceOrder.
 * Vouchers are sold in złoty and pay in złoty: the English checkout, in euro, never takes them.
 */
class VoucherCodes
{
    public const SESSION_KEY = 'checkout.vouchers';

    public static function accepted(): bool
    {
        return Locales::currency() === 'PLN';
    }

    /**
     * @return list<string>
     */
    public static function all(Request $request): array
    {
        return array_values(array_filter((array) $request->session()->get(self::SESSION_KEY, []), 'is_string'));
    }

    public static function add(Request $request, string $code): void
    {
        $request->session()->put(self::SESSION_KEY, [...self::all($request), VoucherCode::normalize($code)]);
    }

    public static function forget(Request $request, string $code): void
    {
        $code = VoucherCode::normalize($code);

        $request->session()->put(self::SESSION_KEY, array_values(array_filter(self::all($request), fn (string $kept) => $kept !== $code)));
    }

    /** What the customer reads under the field, in Kasia's words. */
    public static function refusal(BalanceNotAccepted $refused): string
    {
        $t = fn (string $key, array $replace = []) => __('checkout::checkout.voucher_refused.'.$key, $replace);

        return match ($refused->reason) {
            BalanceRefusal::Unknown => $t('unknown'),
            BalanceRefusal::Workshop => $t('workshop'),
            BalanceRefusal::Spent => $t('spent'),
            BalanceRefusal::Expired => $t('expired', ['date' => $refused->voucher?->valid_until->translatedFormat('j F Y')]),
            BalanceRefusal::Twice => $t('twice'),
        };
    }
}
