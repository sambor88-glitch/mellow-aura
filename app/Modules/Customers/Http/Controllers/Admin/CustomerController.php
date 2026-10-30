<?php

namespace App\Modules\Customers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Actions\AnonymizeCustomer;
use App\Modules\Customers\Actions\ExportCustomer;
use App\Modules\Customers\Http\Requests\Admin\AnonymizeCustomerRequest;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\Customers\Support\Customer;
use App\Modules\Customers\Support\CustomerDirectory;
use App\Modules\Customers\Support\CustomerRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * „Klienci” in the panel (MA-241): everyone who bought, booked, asked or complained, found by name, address or
 * phone; one card per person; and the two GDPR requests — a copy of her data and forgetting her. The owner's alone
 * (PanelAccess): a helper packing orders has no business with whole customer histories.
 */
class CustomerController extends Controller
{
    private const PER_PAGE = 30;

    /** How many GDPR requests the list shows under the people. */
    private const REQUESTS_SHOWN = 20;

    public function index(Request $request, CustomerDirectory $directory): View
    {
        $query = trim((string) $request->query('szukaj'));
        $found = $directory->search($query);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('customers::admin.index', [
            'query' => $query,
            'customers' => (new LengthAwarePaginator($found->forPage($page, self::PER_PAGE)->values(), $found->count(), self::PER_PAGE, $page, [
                'path' => $request->url(),
                'query' => array_filter(['szukaj' => $query]),
            ])),
            'privacyRequests' => PrivacyRequest::query()->latest('id')->limit(self::REQUESTS_SHOWN)->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('customers::admin.show', ['records' => new CustomerRecords($customer)]);
    }

    public function export(Request $request, Customer $customer, ExportCustomer $export): Response
    {
        $json = $export(new CustomerRecords($customer), $request->user()->name);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="mellowaura-dane-'.now()->format('Y-m-d').'.json"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function anonymize(AnonymizeCustomerRequest $request, Customer $customer, AnonymizeCustomer $anonymize): RedirectResponse
    {
        $done = $anonymize(new CustomerRecords($customer), $request->user()->name);

        if ($done === null) {
            return to_route('admin.customers.show', $customer->key)
                ->with('panel_status', 'Najpierw dokończ sprawy z listy — do nich potrzebny jest jeszcze jej adres.');
        }

        return to_route('admin.customers.index')
            ->with('panel_status', 'Dane usunięte. Zostały tylko opłacone zamówienia do księgowości oraz odstąpienia i reklamacje — bez adresu e-mail.');
    }
}
