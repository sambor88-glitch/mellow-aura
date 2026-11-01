<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use App\Modules\Checkout\Models\Order;
use App\Modules\Shipping\Http\Controllers\Admin\ShipmentController;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The size of the parcel, and the customer's locker or the parcel's weight, depending on how it travels.
 */
class CreateShipmentRequest extends FormRequest
{
    /** Only a paid parcel that travels with InPost, and only once ShipX is connected. */
    public function authorize(): bool
    {
        return ShipmentController::sendable($this->order()) && ShipXClient::configured();
    }

    /** Nothing to send here is a page that is not there, not a refusal. */
    protected function failedAuthorization(): never
    {
        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locker = $this->order()->shipping_method === 'parcel_locker';

        return [
            'size' => ['required', Rule::in(array_keys(Shipment::SIZES))],
            'target_point' => $locker ? ['required', 'regex:/^[A-Z0-9-]{4,16}$/'] : ['exclude'],
            'weight' => $locker ? ['exclude'] : ['required', 'numeric', 'min:0.1', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size.required' => 'Wybierz rozmiar paczki',
            'size.in' => 'Wybierz rozmiar paczki',
            'target_point.required' => 'Wpisz kod paczkomatu klienta, np. KRA01M',
            'target_point.regex' => 'Kod paczkomatu to litery i cyfry, np. KRA01M',
            'weight.required' => 'Wpisz wagę paczki w kilogramach',
            'weight.numeric' => 'Wagę wpisz liczbą, np. 1,5',
            'weight.min' => 'Paczka waży co najmniej 0,1 kg',
            'weight.max' => 'Kurier InPost bierze paczki do 30 kg',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target_point' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('target_point'))) ?: null,
            // A comma is how Kasia writes 1,5 kg.
            'weight' => str_replace(',', '.', (string) $this->input('weight')) ?: null,
        ]);
    }

    private function order(): Order
    {
        return $this->route('order');
    }
}
