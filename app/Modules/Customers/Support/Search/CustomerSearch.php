<?php

namespace App\Modules\Customers\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;
use App\Modules\Customers\Support\Customer;
use App\Modules\Customers\Support\CustomerDirectory;

/**
 * People in the panel search (MA-308), by name, e-mail or phone, the most recently seen first. The directory gathers
 * them by address from the records of the shop, so they are found the same way as on „Klienci”, also without Polish letters.
 */
final class CustomerSearch implements SearchProvider
{
    public function group(): string
    {
        return 'Klienci';
    }

    public function screen(): string
    {
        return 'admin.customers.index';
    }

    public function search(string $term, int $limit): iterable
    {
        $digits = (string) preg_replace('/\D/', '', $term);

        return app(CustomerDirectory::class)->all()
            ->filter(fn (Customer $customer) => PanelSearch::matches($term, $customer->name, $customer->email)
                || (strlen($digits) >= 3 && collect($customer->phones)->contains(fn (string $phone) => str_contains((string) preg_replace('/\D/', '', $phone), $digits))))
            ->take($limit)
            ->map(fn (Customer $customer) => new SearchResult(
                title: $customer->name,
                url: route('admin.customers.show', $customer->key),
                route: 'admin.customers.show',
                detail: collect([$customer->name === $customer->email ? null : $customer->email, $customer->activity()])->filter()->join(' · '),
                icon: mb_strtoupper(mb_substr($customer->name, 0, 1)),
            ))
            ->values();
    }
}
