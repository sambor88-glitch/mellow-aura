<?php

namespace App\Modules\CustomOrders\Http\Requests\Admin;

use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The amount of a payment link, in złoty: at least 1 zł and at most what is left of the quote.
 */
class SendPaymentLinkRequest extends FormRequest
{
    private const PRICE = '/^\d{1,6}([.,]\d{1,2})?$/';

    private const MIN = 100;

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
            'amount' => ['required', 'regex:'.self::PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.*' => 'Wpisz kwotę do zapłaty, np. 450 albo 450,50',
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
            $amount = $this->amount();

            match (true) {
                ! $idea->status->takesPayments() => $validator->errors()->add('amount', 'Pomysł jest odrzucony — zmień status, zanim wyślesz link'),
                $due === null => $validator->errors()->add('amount', 'Najpierw zapisz wycenę'),
                $due === 0 => $validator->errors()->add('amount', 'Wycena jest już opłacona w całości'),
                $amount < self::MIN => $validator->errors()->add('amount', 'Link musi być na co najmniej '.Money::format(self::MIN)),
                $amount > $due => $validator->errors()->add('amount', 'Do zapłaty zostało '.Money::format($due).' — wpisz tyle albo mniej'),
                default => null,
            };
        }];
    }

    /** In grosze. */
    public function amount(): int
    {
        return Money::parse((string) $this->validated('amount'));
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
