<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Shared\Support\PersonalData;

/**
 * Forgets a customer in Checkout's tables when she asks for it (GDPR art. 17, MA-241). Called by the „Klienci”
 * screen, which first makes sure nothing of hers is still on its way.
 *
 * A paid order is a sale the books must show for five years (art. 17(3)(b)): it keeps its number, dates, amounts,
 * items, the name and the NIP for the invoice. Everything else goes — e-mail, phone, address, Paczkomat, notes, the gift message
 * and the texts written for the pieces (the mug's inscription, the voucher's names and dedication, Kasia's note on the
 * page of the piece, which may name her), Google Analytics' id of her browser and the photo of a mug with an inscription, which shows the text too (MA-290).
 * An order that was never paid is no sale, so its name and NIP go too. Withdrawals, complaints and their answers keep
 * the name and their content as proof in a dispute (art. 17(3)(e)) and lose the e-mail.
 */
class AnonymizeCustomer
{
    /**
     * @return array{orders: int, paid_orders: int, withdrawals: int, complaints: int, received_complaints: int}
     */
    public function __invoke(string $email): array
    {
        $email = PersonalData::normalize($email);
        $orders = Order::query()->whereRaw('lower(email) = ?', [$email])->get(['id', 'paid_at']);

        OrderItem::query()->whereIn('order_id', $orders->pluck('id'))->whereNotNull('custom_text')->with('media')->get()
            ->each(fn (OrderItem $item) => $item->clearMediaCollection('photo'));

        OrderItem::query()->whereIn('order_id', $orders->pluck('id'))->update([
            'custom_text' => null,
            'recipient_name' => null,
            'sender_name' => null,
            'dedication' => null,
            'maker_note' => null,
        ]);

        $wiped = [
            'email' => PersonalData::ERASED_EMAIL,
            'phone' => '',
            'shipping_address' => null,
            'locker_code' => null,
            'note' => null,
            // The few words to the person who got the gift may name both of them (MA-342).
            'gift_message' => null,
            'problem_note' => null,
            // Google Analytics' id of her browser (MA-264).
            'ga_client_id' => null,
            'ga_session_id' => null,
        ];
        $paid = $orders->whereNotNull('paid_at')->pluck('id');
        Order::query()->whereIn('id', $paid)->update($wiped);
        Order::query()->whereIn('id', $orders->whereNull('paid_at')->pluck('id'))
            ->update([...$wiped, 'name' => PersonalData::ERASED_NAME, 'invoice_nip' => null]);

        return [
            'orders' => $orders->count(),
            'paid_orders' => $paid->count(),
            'withdrawals' => Withdrawal::query()->whereRaw('lower(email) = ?', [$email])->update(['email' => PersonalData::ERASED_EMAIL]),
            'complaints' => ComplaintAnswer::query()->whereRaw('lower(email) = ?', [$email])->update(['email' => PersonalData::ERASED_EMAIL]),
            'received_complaints' => Complaint::query()->whereRaw('lower(email) = ?', [$email])->update(['email' => PersonalData::ERASED_EMAIL]),
        ];
    }
}
