<?php

namespace App\Modules\Checkout\Http\Requests\Admin;

use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\ComplaintRemedy;
use App\Modules\Checkout\Enums\MediationConsent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The answer to a complaint: who gets it, when the complaint came, the decision and, when the decision leaves the
 * dispute open, the reason and the statement on mediation, which the law otherwise reads as a yes.
 */
class AnswerComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $openDispute = ComplaintDecision::tryFrom((string) $this->input('decision'))?->leavesDispute() ?? false;

        return [
            'order_number' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'decision' => ['required', Rule::enum(ComplaintDecision::class)],
            'remedy' => [Rule::requiredIf($this->input('decision') === ComplaintDecision::Accepted->value), 'nullable', Rule::enum(ComplaintRemedy::class)],
            'details' => [$openDispute ? 'required' : 'nullable', 'string', 'max:2000'],
            'mediation' => [$openDispute ? 'required' : 'nullable', Rule::enum(MediationConsent::class)],
            'intent' => ['nullable', 'in:preview,send'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $date = 'Wpisz, kiedy reklamacja do Ciebie doszła — od tego dnia liczy się 14 dni';
        $remedy = 'Wybierz, jak załatwisz reklamację: naprawa, wymiana, obniżenie ceny albo zwrot';
        $mediation = 'Zaznacz, czy zgadzasz się na mediację — bez tego prawo uznaje, że się zgadzasz';

        return [
            'name.required' => 'Wpisz imię i nazwisko osoby, która złożyła reklamację',
            'name.max' => 'Imię i nazwisko zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — na niego wyślę odpowiedź',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'order_number.max' => 'Numer zamówienia zmieszczę do :max znaków, np. MA-2026-1047',
            'received_on.required' => $date,
            'received_on.date_format' => $date,
            'received_on.before_or_equal' => 'Ta data jest w przyszłości — wpisz dzień, w którym reklamacja doszła',
            'decision.required' => 'Wybierz, czy uznajesz reklamację',
            'decision.enum' => 'Wybierz, czy uznajesz reklamację',
            'remedy.required' => $remedy,
            'remedy.enum' => $remedy,
            'details.required' => 'Napisz, dlaczego nie uznajesz reklamacji albo którą część uznajesz',
            'details.max' => 'Uzasadnienie zmieszczę do :max znaków',
            'mediation.required' => $mediation,
            'mediation.enum' => $mediation,
        ];
    }

    public function receivedOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->validated('received_on'))->startOfDay();
    }

    public function decision(): ComplaintDecision
    {
        return ComplaintDecision::from($this->validated('decision'));
    }

    /**
     * An accepted complaint names its remedy; the other decisions don't.
     */
    public function remedy(): ?ComplaintRemedy
    {
        return $this->decision() === ComplaintDecision::Accepted ? ComplaintRemedy::tryFrom((string) $this->validated('remedy')) : null;
    }

    /**
     * The statement on mediation belongs only to a decision that leaves the dispute open.
     */
    public function mediation(): ?MediationConsent
    {
        return $this->decision()->leavesDispute() ? MediationConsent::tryFrom((string) $this->validated('mediation')) : null;
    }

    public function previewOnly(): bool
    {
        return $this->validated('intent') === 'preview';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => filled($number = $this->input('order_number')) ? mb_strtoupper(trim((string) $number)) : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.complaints.index').'#odpowiedz';
    }
}
