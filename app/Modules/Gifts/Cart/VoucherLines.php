<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Cart\Lines\ProductLines;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Vouchers added from their product page. The names and dedication are optional, and their limits
 * come from the panel.
 */
class VoucherLines extends ProductLines
{
    public function fromRequest(Request $request): CartLine
    {
        $variant = $this->requestedVariant($request);

        abort_unless($variant->product->isVoucher(), 404);

        $text = fn (string $field) => is_string($value = $request->input($field)) ? $value : '';

        // Browsers send a line break in a textarea as two characters; the counter on the page counts one.
        $request->merge([
            'recipient_name' => trim((string) preg_replace('/\s+/u', ' ', $text('recipient_name'))),
            'sender_name' => trim((string) preg_replace('/\s+/u', ' ', $text('sender_name'))),
            'dedication' => trim((string) preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], $text('dedication'))),
        ]);

        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'recipient_name' => ['nullable', 'string', 'max:'.(int) $this->settings->get('voucher_recipient_name_max_chars', 40)],
            'sender_name' => ['nullable', 'string', 'max:'.(int) $this->settings->get('voucher_recipient_name_max_chars', 40)],
            'dedication' => ['nullable', 'string', 'max:'.(int) $this->settings->get('voucher_dedication_max_chars', 180)],
        ], [
            'quantity.max' => CartLine::TOO_MANY_NOTICE,
            'recipient_name.max' => 'Imię na voucherze zmieszczę do :max znaków',
            'sender_name.max' => 'Podpis „od kogo” zmieszczę do :max znaków',
            'dedication.max' => 'Dedykację zmieszczę do :max znaków',
        ]);

        $recipientName = ($data['recipient_name'] ?? '') ?: null;
        $dedication = ($data['dedication'] ?? '') ?: null;
        $senderName = ($data['sender_name'] ?? '') ?: null;

        return new VoucherLine(
            VoucherLine::keyForVoucher($variant, $recipientName, $dedication, $senderName),
            (int) ($data['quantity'] ?? 1),
            $variant,
            $recipientName,
            $dedication,
            $senderName,
        );
    }

    protected function line(string $key, int $quantity, ProductVariant $variant, array $row): ProductLine
    {
        return new VoucherLine($key, $quantity, $variant, $row['recipient_name'] ?? null, $row['dedication'] ?? null, $row['sender_name'] ?? null);
    }
}
