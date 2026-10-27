<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use App\Modules\Workshops\Http\Controllers\BookingController;
use App\Modules\Workshops\Support\WorkshopTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One voucher in the panel (MA-137): which workshop and how many seats it books with its code, until when, and
 * whether it is used. Kasia fills in a voucher that came without them, extends one that ran out and marks one she
 * booked by hand as used. Each voucher has its own form,
 * so mistakes come back in its own error bag.
 */
class SaveVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function formKey(): string
    {
        return 'voucher-'.$this->route('voucher')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workshop_code' => ['nullable', 'string', Rule::in(app(WorkshopTypes::class)->all()->map(WorkshopTypes::key(...))->all())],
            'seats' => ['nullable', 'integer', 'min:1', 'max:'.BookingController::MAX_SEATS],
            'valid_until' => ['required', 'date_format:Y-m-d'],
            'redeemed' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $seats = 'Wpisz liczbę miejsc od 1 do '.BookingController::MAX_SEATS;

        return [
            'workshop_code.in' => 'Wybierz warsztat z cennika',
            'seats.integer' => $seats,
            'seats.min' => $seats,
            'seats.max' => $seats,
            'valid_until.required' => 'Wpisz, do kiedy voucher jest ważny',
            'valid_until.date_format' => 'Wybierz datę z kalendarza',
        ];
    }

    /**
     * @return array{workshop_code: ?string, seats: ?int, valid_until: string, redeemed: bool}
     */
    public function voucher(): array
    {
        $data = $this->validated();
        $workshop = filled($data['workshop_code'] ?? null) ? $data['workshop_code'] : null;

        return [
            'workshop_code' => $workshop,
            // A workshop voucher books at least one seat; an amount voucher books none.
            'seats' => $workshop === null ? null : (int) ($data['seats'] ?? 1),
            'valid_until' => $data['valid_until'],
            'redeemed' => $this->boolean('redeemed'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->formKey();
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#'.$this->formKey();
    }
}
