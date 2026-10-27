<?php

namespace App\Modules\CustomOrders\Http\Requests\Admin;

use App\Modules\CustomOrders\Models\CustomOrderRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Which part of the quote the link is for — Maciej's decision of 26.10, after the terms (custom orders, point 1):
 * „Całość” is what is left of the quote, „Połowa” is half of it for a big order, only while nothing is paid yet.
 * The second half is then „Całość”.
 */
class SendPaymentLinkRequest extends FormRequest
{
    public const FULL = 'full';

    public const HALF = 'half';

    /** @var string */
    protected $errorBag = 'pomysl';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'part' => ['required', 'in:'.self::FULL.','.self::HALF],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'part.*' => 'Wybierz, czy link jest na całość, czy na połowę',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $idea = $this->idea();
            $due = $idea->amountDue();

            match (true) {
                ! $idea->status->takesPayments() => $validator->errors()->add('part', 'Pomysł jest odrzucony — zmień status, zanim wyślesz link'),
                $due === null => $validator->errors()->add('part', 'Najpierw zapisz wycenę'),
                $due === 0 => $validator->errors()->add('part', 'Wycena jest już opłacona w całości'),
                $this->validated('part') === self::HALF && ! self::halfAllowed($idea) => $validator->errors()->add('part', 'Połowę wysyłasz tylko jako pierwszą wpłatę — teraz wyślij resztę'),
                default => null,
            };
        }];
    }

    /**
     * Half of the quote, rounded down: the rest link („Całość”) then takes the odd grosz, so both add up to it.
     */
    public static function half(CustomOrderRequest $idea): int
    {
        return intdiv((int) $idea->quote, 2);
    }

    /** Only as the first payment, and only when each half is at least 1 zł. */
    public static function halfAllowed(CustomOrderRequest $idea): bool
    {
        return $idea->quote !== null && self::half($idea) >= 100 && $idea->paidAmount() === 0;
    }

    /** In grosze. */
    public function amount(): int
    {
        $idea = $this->idea();

        return $this->validated('part') === self::HALF ? self::half($idea) : (int) $idea->amountDue();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.custom-orders.index').'#pomysl-'.$this->idea()->id;
    }

    private function idea(): CustomOrderRequest
    {
        return $this->route('customOrderRequest');
    }
}
