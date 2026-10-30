<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\GiftChoiceRefusal;
use App\Modules\Gifts\Exceptions\GiftChoiceNotAccepted;
use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Support\VoucherCode;
use App\Modules\MugConfigurator\Cart\MugLine;
use Illuminate\Support\Facades\DB;

/**
 * The person a gift choice card is for chooses her mug (MA-235), once. In one transaction the card is found by its
 * code and locked, checked, and an order for the mug is placed and paid: its item copies the text, the glaze and the
 * size, at no price — the money came in with the card, together with the delivery, so the order costs nothing, like
 * a workshop booked with a voucher. The order then goes through MarkOrderPaid like a paid one, so it waits under
 * „W realizacji” with the address she gave, and the mails and the panel treat it the same.
 * Two people sending the same code at once are counted one after the other; the second one hears it is used.
 */
class RedeemGiftChoice
{
    public function __construct(private PlaceOrder $placeOrder, private MarkOrderPaid $markOrderPaid) {}

    /**
     * Finds the card by its code and checks it, without locking: for the pages before the choice.
     *
     * @throws GiftChoiceNotAccepted
     */
    public function find(string $code): GiftChoice
    {
        return $this->check(GiftChoice::query()->issued()->where('code', VoucherCode::normalize($code))->first());
    }

    /**
     * @param  string  $text  the tidied, checked text (MugText)
     * @param  array{code: string, name: string, hex: string}  $glaze
     * @param  array<string, mixed>  $data  her name, contact and delivery, in the shape PlaceOrder takes
     *
     * @throws GiftChoiceNotAccepted
     */
    public function __invoke(string $code, string $text, array $glaze, array $data): Order
    {
        return DB::transaction(function () use ($code, $text, $glaze, $data) {
            $choice = $this->check(GiftChoice::query()->issued()->lockForUpdate()->where('code', VoucherCode::normalize($code))->first());
            $buyer = $choice->orderItem->order;

            $line = new MugLine('gift-choice-'.$choice->id, 1, $text, ['label' => $choice->size_label, 'name' => $choice->size_name, 'price' => 0], $glaze, null);
            $order = ($this->placeOrder)(collect([$line->key => $line]), [
                ...$data,
                'payment_method' => PaymentMethod::Voucher->value,
                'note' => 'Kubek z karty wyboru '.$choice->code.' — kupiony w zamówieniu '.$buyer->number.' ('.$buyer->name.').',
            ], 0);

            ($this->markOrderPaid)($order, $choice->code);
            $choice->update(['chosen_at' => now(), 'fulfilment_order_id' => $order->id]);

            return $order;
        });
    }

    /**
     * @throws GiftChoiceNotAccepted
     */
    private function check(?GiftChoice $choice): GiftChoice
    {
        $refusal = match (true) {
            $choice === null => GiftChoiceRefusal::Unknown,
            $choice->chosen_at !== null => GiftChoiceRefusal::Chosen,
            // Once Kasia decided after the deadline, the card is settled.
            $choice->resolution !== null, $choice->isExpired() => GiftChoiceRefusal::Expired,
            default => null,
        };

        if ($refusal !== null) {
            throw new GiftChoiceNotAccepted($refusal);
        }

        return $choice;
    }
}
