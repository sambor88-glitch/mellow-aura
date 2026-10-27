<?php

namespace App\Modules\Admin\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Which panel screens an account may open. The owner opens all of them. A helper only gets the orders — the list,
 * the details, the status, certificates and voucher PDFs to pack — and no money matters, settings or accounts.
 * Marking a bank transfer as received stays the owner's: only she sees the bank account (MA-216).
 * A new screen is the owner's until it is added here.
 */
final class PanelAccess
{
    /** Route names a helper may open. */
    private const HELPER_ROUTES = ['admin.dashboard', 'admin.logout', 'admin.orders.*', 'admin.vouchers.pdf'];

    /** Route names under HELPER_ROUTES that are still the owner's alone. */
    private const OWNER_ROUTES = ['admin.orders.paid'];

    public static function allows(?User $user, string $route): bool
    {
        return $user !== null && ($user->isOwner() || (Str::is(self::HELPER_ROUTES, $route) && ! in_array($route, self::OWNER_ROUTES, true)));
    }
}
