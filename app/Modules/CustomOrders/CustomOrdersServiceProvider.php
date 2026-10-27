<?php

namespace App\Modules\CustomOrders;

use App\Modules\Shared\ModuleServiceProvider;

/**
 * Custom orders: the page /zamowienia-indywidualne with the form for an idea and its photos, and the mails about it.
 * The ideas wait for Kasia in the panel, where she sends a link to pay for one (MA-144). The link's page pays through
 * Checkout and Payments, like the shop, and ConfirmPayment counts the money towards the idea.
 */
class CustomOrdersServiceProvider extends ModuleServiceProvider {}
