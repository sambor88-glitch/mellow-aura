<?php

namespace App\Modules\Checkout\Http\Requests\Admin;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A status Kasia can set by hand, only on a paid order. „Wysłane” only fits a parcel that travels,
 * so an order collected at the studio or vouchers sent as PDFs go straight to „Zakończone”.
 */
class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->order()->payment_status === PaymentStatus::Paid;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->order();
        $allowed = [OrderStatus::InProgress, OrderStatus::Completed, OrderStatus::Problem];

        if ($order->sendsParcel() && $order->shipping_method !== 'studio_pickup') {
            $allowed[] = OrderStatus::Shipped;
        }

        return [
            'status' => ['required', Rule::enum(OrderStatus::class)->only($allowed)],
            'tracking_number' => ['nullable', 'regex:/^[A-Z0-9]{8,40}$/'],
            'problem_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Wybierz, co się dzieje z zamówieniem',
            'status.enum' => 'Tego statusu nie da się ustawić przy tym zamówieniu',
            'tracking_number.regex' => 'Numer przesyłki to litery i cyfry z etykiety InPost — sprawdź, czy się zgadza',
            'problem_note.max' => 'Notatkę zmieszczę do :max znaków',
        ];
    }

    public function order(): Order
    {
        return $this->route('order');
    }

    protected function prepareForValidation(): void
    {
        // Copied from a label with spaces or in small letters, it is still the same number.
        $this->merge([
            'tracking_number' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('tracking_number'))) ?: null,
            'problem_note' => trim((string) $this->input('problem_note')) ?: null,
        ]);
    }
}
