<?php

namespace App\Modules\Admin\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Which panel screens an account may open. The owner opens all of them. A helper only gets the orders — the list,
 * the details, the status, certificates and voucher PDFs to pack — and no money matters, settings or accounts.
 * A new screen is the owner's until it is added here.
 */
final class PanelAccess
{
    /** Route names a helper may open. */
    private const HELPER_ROUTES = ['admin.dashboard', 'admin.logout', 'admin.orders.*', 'admin.vouchers.pdf'];

    public static function allows(?User $user, string $route): bool
    {
        return $user !== null && ($user->isOwner() || Str::is(self::HELPER_ROUTES, $route));
    }
}
