@php
    $bag = $errors->getBag('maile');
    $names = ['pl' => 'Po polsku', 'en' => 'Po angielsku — w mailach do zamówień z angielskiej strony'];
    $parts = [
        'subject' => 'Temat',
        'intro' => 'Zdanie na początek',
        'outro' => 'Zdanie na koniec',
        'signature' => 'Podpis',
        'footer' => 'Stopka pod mailem',
    ];

    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';
    $invalidMails = collect($fields)->filter(fn (array $field) => $field['mail'] !== null && $bag->has($field['setting']))->pluck('mail')->unique()->all();
@endphp
<x-admin::layout title="Maile" lead="Maile, które sklep wysyła klientkom. Układ, kwoty i dane zamówienia biorą się same — tu zmienisz temat, pierwsze i ostatnie zdanie oraz podpis i stopkę.">
    <form id="maile" method="post" action="{{ route('admin.mail-texts.update') }}" novalidate class="max-w-[880px] scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]">
        @csrf
        @method('PUT')
        {{-- Enter in a field saves the texts instead of opening a preview. --}}
        <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Puste pole wysyła tekst, który widzisz w nim na szaro. Słowo w klamrach, np. {numer}, wstawia się samo z zamówienia. Zdanie na koniec mówi o odpisaniu na maila, więc pokazuje się, gdy w Ustawieniach jest e-mail kontaktowy.</p>
        @if ($bag->any())
            <p role="alert" class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <h2 class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Podpis i stopka — w każdym mailu</h2>
        <div class="mb-6 grid gap-5">
            @foreach ($locales as $locale)
                <fieldset class="min-w-0 rounded-[4px] border border-sand-dark bg-linen p-3.5">
                    <legend class="px-1 text-[12.5px] text-graphite">{{ $names[$locale] ?? $locale }}</legend>
                    <div class="grid gap-3">
                        @foreach (\App\Modules\Shared\Mail\MailTexts::COMMON as $part => $limits)
                            @include('settings::admin.mail-texts.field', ['key' => \App\Modules\Shared\Mail\MailTexts::setting($part, $locale)])
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <h2 class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Maile do klientek</h2>
        <div class="grid gap-3">
            @foreach ($mails as $mail)
                @php $mailLocales = array_values(array_intersect($mail->locales, $locales)); @endphp
                <details id="mail-{{ $mail->key }}" @if (in_array($mail->key, $invalidMails, true)) open @endif class="min-w-0 scroll-mt-6 rounded-[4px] border border-sand-dark bg-linen">
                    <summary class="flex min-h-11 cursor-pointer flex-col justify-center gap-0.5 px-3.5 py-3">
                        <span class="text-[15px] text-ink">{{ $mail->label }}</span>
                        <span class="text-[12.5px] leading-[1.5] text-label">{{ $mail->when }}</span>
                    </summary>
                    <div class="grid gap-4 border-t border-sand-dark p-3.5">
                        @if ($mail->placeholders !== [])
                            <p class="text-[12.5px] leading-[1.6] text-label">Możesz wstawić: @foreach ($mail->placeholders as $name => $meaning)<span class="text-graphite">{{ '{'.$name.'}' }}</span> — {{ $meaning }}{{ $loop->last ? '.' : ', ' }}@endforeach</p>
                        @endif
                        @foreach ($mailLocales as $locale)
                            <fieldset class="min-w-0">
                                @if (count($mailLocales) > 1)
                                    <legend class="mb-2 text-[12.5px] text-graphite">{{ $names[$locale] ?? $locale }}</legend>
                                @endif
                                <div class="grid gap-3">
                                    @foreach ($mail->parts as $part)
                                        @include('settings::admin.mail-texts.field', ['key' => \App\Modules\Shared\Mail\MailTexts::setting($part, $locale, $mail->key)])
                                    @endforeach
                                </div>
                                <button name="preview" value="{{ $mail->key }}:{{ $locale }}" formaction="{{ route('admin.mail-texts.preview') }}" formtarget="_blank"
                                        class="mt-3 min-h-11 rounded-full border border-line-strong px-5 py-3 text-[13.5px] text-ink hover:border-ink hover:bg-sand-dark">Podgląd{{ count($mailLocales) > 1 ? ' — '.($locale === 'pl' ? 'po polsku' : 'po angielsku') : '' }}</button>
                            </fieldset>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">Podgląd otwiera w nowej karcie mail dla przykładowego zamówienia z tym, co jest teraz w polach — jeszcze bez zapisywania. Zapisane teksty mają wszystkie maile wysłane od tej chwili. Maile do Ciebie (nowe zamówienie, nowy zapis) się nie zmieniają.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz teksty maili</button>
    </form>
</x-admin::layout>
