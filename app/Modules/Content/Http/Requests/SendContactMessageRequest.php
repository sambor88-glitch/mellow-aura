<?php

namespace App\Modules\Content\Http\Requests;

use App\Modules\Settings\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The contact form: name, e-mail, topic from the panel's list and the message. The „website” field is
 * hidden from people; a form that fills it in comes from a bot.
 */
class SendContactMessageRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'topic' => ['nullable', Rule::in((array) $this->container->make(Settings::class)->get('contact_form_topics', []))],
            'message' => ['required', 'string', 'max:3000'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać',
            'name.max' => 'Imię zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — na niego odpiszę',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'topic.in' => 'Wybierz sprawę z listy',
            'message.required' => 'Napisz, w czym mogę pomóc',
            'message.max' => 'Wiadomość zmieszczę do :max znaków — resztę dopiszesz w odpowiedzi',
        ];
    }

    public function isFromBot(): bool
    {
        return filled($this->input('website'));
    }

    protected function getRedirectUrl(): string
    {
        return route('content.contact').'#formularz';
    }
}
