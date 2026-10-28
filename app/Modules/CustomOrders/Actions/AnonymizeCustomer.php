<?php

namespace App\Modules\CustomOrders\Actions;

use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Shared\Support\PersonalData;

/**
 * Forgets a customer's ideas from the custom order form when she asks for it (GDPR art. 17, MA-241). The kind,
 * the status, Kasia's quote and the payment links stay, so the money paid through them still adds up; her name,
 * contact, the description, Kasia's note about her and the photos go. The payments themselves are orders and stay
 * in Checkout for the books.
 */
class AnonymizeCustomer
{
    /**
     * @return int how many ideas were wiped
     */
    public function __invoke(string $email): int
    {
        $requests = CustomOrderRequest::query()->whereRaw('lower(email) = ?', [PersonalData::normalize($email)])->get();

        foreach ($requests as $request) {
            $request->clearMediaCollection('photos');
            $request->update([
                'name' => PersonalData::ERASED_NAME,
                'email' => PersonalData::ERASED_EMAIL,
                'phone' => null,
                'quantity' => null,
                'needed_by' => null,
                'budget' => null,
                'description' => '',
                'notes' => null,
            ]);
        }

        return $requests->count();
    }
}
