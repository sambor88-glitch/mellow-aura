<?php

namespace App\Modules\Workshops\Http\Requests\Admin;

use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopTypes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new date on the workshop calendar: a workshop from the price list, a day from the calendar,
 * the start and optionally the end time, and the number of seats.
 */
class StoreWorkshopDateRequest extends FormRequest
{
    public const MAX_SEATS = 30;

    /** @var string */
    protected $errorBag = 'terminy';

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
            'workshop' => ['required', Rule::in(app(WorkshopTypes::class)->all()->map(WorkshopTypes::key(...))->all())],
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start' => ['bail', 'required', 'date_format:H:i'],
            'end' => ['bail', 'nullable', 'date_format:H:i', 'after:start'],
            'seats' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SEATS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop.required' => 'Wybierz warsztat',
            'workshop.in' => 'Tego warsztatu nie ma już w cenniku — wybierz inny',
            'date.required' => 'Wybierz dzień z kalendarza',
            'date.date_format' => 'Wybierz dzień z kalendarza',
            'date.after_or_equal' => 'Ten dzień już minął — wybierz dzisiejszy albo późniejszy',
            'start.required' => 'Wpisz godzinę początku, np. 11:00',
            'start.date_format' => 'Wpisz godzinę jak 11:00',
            'end.date_format' => 'Wpisz godzinę jak 13:00',
            'end.after' => 'Koniec musi być po początku',
            'seats.required' => 'Wpisz liczbę miejsc',
            'seats.integer' => 'Wpisz liczbę miejsc, np. 6',
            'seats.min' => 'Wpisz co najmniej jedno miejsce',
            'seats.max' => 'Zmieszczę do :max miejsc',
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->startsAt()->isPast()) {
                $validator->errors()->add('start', 'Ta godzina już minęła — wpisz późniejszą');
            } elseif (WorkshopDate::query()->where('workshop_code', $this->validated('workshop'))->where('starts_at', $this->startsAt())->exists()) {
                $validator->errors()->add('start', 'Ten termin już jest w terminarzu');
            }
        }];
    }

    /**
     * @return array{workshop_code: string, starts_at: CarbonImmutable, ends_at: ?CarbonImmutable, seats: int}
     */
    public function workshopDate(): array
    {
        return [
            'workshop_code' => (string) $this->validated('workshop'),
            'starts_at' => $this->startsAt(),
            'ends_at' => filled($end = $this->validated('end')) ? $this->at($end) : null,
            'seats' => (int) $this->validated('seats'),
        ];
    }

    private function startsAt(): CarbonImmutable
    {
        return $this->at((string) $this->input('start'));
    }

    /**
     * The day and time as typed in the panel, in the application's time zone (Warsaw).
     */
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->input('date').' '.$time)->startOfMinute();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.workshops.edit').'#terminy';
    }
}
