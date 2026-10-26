<?php

namespace App\Modules\Firing\Actions;

use App\Modules\Firing\Enums\FiringRequestStatus;
use App\Modules\Firing\Mail\BatchReportConfirmed;
use App\Modules\Firing\Mail\BatchReported;
use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Firing\Support\FiringQuote;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Saves a batch from the firing form with the quote from the price list, then mails Kasia and the customer.
 * The batch is saved first: a mail that can't be queued is reported, and the request still waits for Kasia.
 */
class ReportBatch
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{code: ?string, label: string, price_gross: int, unit: ?string}|null  $service  null: „Nie wiem, doradź”
     * @param  array{name: string, email: string, phone?: ?string, drop_off_on?: ?string, message?: ?string}  $customer
     */
    public function __invoke(?array $service, ?string $quantity, array $customer): FiringRequest
    {
        $request = FiringRequest::query()->create([
            'name' => trim($customer['name']),
            'email' => trim($customer['email']),
            'phone' => filled($customer['phone'] ?? null) ? trim($customer['phone']) : null,
            'service_code' => $service === null ? null : KilnPrices::key($service),
            'service_label' => $service['label'] ?? null,
            'unit' => $service['unit'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $service['price_gross'] ?? null,
            'quote' => $service === null ? null : FiringQuote::for($service, $quantity),
            'drop_off_on' => $customer['drop_off_on'] ?? null,
            'message' => filled($customer['message'] ?? null) ? trim($customer['message']) : null,
            'status' => FiringRequestStatus::New,
        ]);

        try {
            Mail::to($this->settings->get('contact_email') ?: throw new RuntimeException('No contact e-mail in the panel for a firing request.'))
                ->send(new BatchReported($request));
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Mail::to($request->email, $request->name)->send(new BatchReportConfirmed($request));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $request;
    }
}
