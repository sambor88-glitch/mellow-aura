<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Enums\PrivacyRequestType;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\Customers\Support\CustomerRecords;

/**
 * Gives Kasia the copy of a customer's data to send her (GDPR art. 15 and 20, MA-241) and notes in the GDPR log
 * that it was made.
 */
class ExportCustomer
{
    /**
     * @return string the copy as pretty-printed JSON
     */
    public function __invoke(CustomerRecords $records, string $performedBy): string
    {
        PrivacyRequest::create([
            'type' => PrivacyRequestType::Export,
            'customer_key' => $records->customer->key,
            'performed_by' => $performedBy,
            'summary' => $records->counts(),
        ]);

        return json_encode($records->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
