<?php

namespace App\Modules\Customers;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Customers\Support\CustomerDirectory;
use App\Modules\Customers\Support\Search\CustomerSearch;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Customers: the „Klienci” screen in the panel (MA-241). There are no customer accounts — a person is everything
 * the shop keeps under one e-mail address: orders, workshop bookings, custom orders, batches for firing,
 * withdrawals and complaints. The module reads the other modules' tables and changes them only through their
 * actions, when Kasia carries out a GDPR request: a copy of the data or forgetting the person.
 */
class CustomersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(PanelSearch::class, fn (PanelSearch $search) => $search->add(new CustomerSearch));
    }

    public function boot(): void
    {
        parent::boot();

        // {customer} in a URL is CustomerKey, never the address itself.
        Route::bind('customer', fn (string $key) => app(CustomerDirectory::class)->find($key) ?? abort(404));
    }
}
