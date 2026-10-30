<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Catalog\Actions\AnonymizeCustomer as AnonymizeStockNotifications;
use App\Modules\Checkout\Actions\AnonymizeCustomer as AnonymizeOrders;
use App\Modules\Customers\Enums\PrivacyRequestType;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\Customers\Support\CustomerRecords;
use App\Modules\CustomOrders\Actions\AnonymizeCustomer as AnonymizeCustomOrders;
use App\Modules\Firing\Actions\AnonymizeCustomer as AnonymizeFiringRequests;
use App\Modules\Gifts\Actions\AnonymizeGiftChoices;
use App\Modules\Gifts\Actions\AnonymizeVouchers;
use App\Modules\Monitoring\Actions\ForgetFailedMails;
use App\Modules\Shipping\Actions\AnonymizeShipments;
use Illuminate\Support\Facades\DB;

/**
 * Forgets a customer on her request (GDPR art. 17, MA-241). Each module wipes its own tables through its own
 * action and decides what it must keep: a paid order stays for the books, a withdrawal or a complaint stays as
 * proof in a dispute. Nothing happens while something of hers is still being paid, made, sent or answered
 * (CustomerRecords::blockers) — that work needs her address. The request goes to the GDPR log under her key.
 */
class AnonymizeCustomer
{
    public function __construct(
        private AnonymizeOrders $orders,
        private AnonymizeVouchers $vouchers,
        private AnonymizeShipments $shipments,
        private AnonymizeCustomOrders $customOrders,
        private AnonymizeFiringRequests $firingRequests,
        private ForgetFailedMails $failedMails,
        private AnonymizeStockNotifications $stockNotifications,
        private AnonymizeGiftChoices $giftChoices,
    ) {}

    /**
     * @return ?PrivacyRequest null when something still stands in the way
     */
    public function __invoke(CustomerRecords $records, string $performedBy): ?PrivacyRequest
    {
        if ($records->blockers() !== []) {
            return null;
        }

        $email = $records->customer->email;

        return DB::transaction(function () use ($records, $email, $performedBy) {
            $summary = [
                'vouchers' => ($this->vouchers)($records->orders->flatMap->items->pluck('id')),
                'gift_choices' => ($this->giftChoices)($records->orders->flatMap->items->pluck('id'), $email),
                'shipments' => ($this->shipments)($records->orders->pluck('id')),
                ...($this->orders)($email),
                'firing_requests' => ($this->firingRequests)($email),
                'failed_mails' => ($this->failedMails)($email),
                'stock_notifications' => ($this->stockNotifications)($email),
                // Photo files are not rolled back with the rows, so they go last.
                'custom_orders' => ($this->customOrders)($email),
            ];

            return PrivacyRequest::create([
                'type' => PrivacyRequestType::Erasure,
                'customer_key' => $records->customer->key,
                'performed_by' => $performedBy,
                'summary' => $summary,
            ]);
        });
    }
}
