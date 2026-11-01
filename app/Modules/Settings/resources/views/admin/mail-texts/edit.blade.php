{{-- Maile (MA-324): the mails to customers folded to their names in one tab, the common signature, footer and image in the other. --}}
@use('App\Modules\Admin\Support\Tabs')
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
    $imageBag = $errors->getBag('grafika-podpisu');
    $tabs = ['maile' => ['label' => 'Maile do klientów', 'count' => count($mails)], 'podpis' => 'Podpis, stopka i grafika'];
@endphp
<x-admin::layout title="Maile" lead="Maile, które sklep wysyła klientom. Układ, kwoty i dane zamówienia biorą się same — tu zmienisz temat, pierwsze i ostatnie zdanie, podpis z grafiką i stopkę.">
  <x-admin::tabs :tabs="$tabs" label="Części ekranu maili" :active="$imageBag->any() ? 'podpis' : null">
    <form id="maile" method="post" action="{{ route('admin.mail-texts.update') }}" novalidate class="max-w-[880px] scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]">
        @csrf
        @method('PUT')
        {{-- Enter in a field saves the texts instead of opening a preview. --}}
        <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Puste pole wysyła tekst, który widzisz w nim na szaro. Słowo w klamrach, np. {numer}, wstawia się samo z zamówienia. Zdanie na koniec mówi o odpisaniu na maila, więc pokazuje się, gdy w Ustawieniach jest e-mail kontaktowy.</p>
        @if ($bag->any())
            <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <x-admin::tab-panel name="maile">
        <h2 class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Maile do klientów</h2>
        <div class="grid gap-2">
            @foreach ($mails as $mail)
                @php $mailLocales = array_values(array_intersect($mail->locales, $locales)); @endphp
                <details id="mail-{{ $mail->key }}" @if (in_array($mail->key, $invalidMails, true)) open @endif class="group min-w-0 scroll-mt-24 rounded-[4px] border border-sand-dark bg-linen">
                    <summary class="flex min-h-11 cursor-pointer flex-col justify-center gap-0.5 px-3.5 py-2.5">
                        <span class="text-[15px] text-ink">{{ $mail->label }}</span>
                        <span class="line-clamp-1 text-[12.5px] leading-[1.5] text-label group-open:line-clamp-none">{{ $mail->when }}</span>
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

        </x-admin::tab-panel>
        <x-admin::tab-panel name="podpis">
        <h2 class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Podpis i stopka — w każdym mailu</h2>
        <div class="grid gap-5">
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

        </x-admin::tab-panel>

        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">Podgląd otwiera w nowej karcie mail dla przykładowego zamówienia z tym, co jest teraz w polach — jeszcze bez zapisywania. Zapisane teksty mają wszystkie maile wysłane od tej chwili. Maile do Ciebie (nowe zamówienie, nowy zapis) się nie zmieniają.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz teksty maili</button>
    </form>

    {{-- The image has forms of its own, so it stands outside the texts form, shown with the signature tab. --}}
    <div data-tab-panel="podpis" x-show="tab === 'podpis'" @if (Tabs::active($tabs, $imageBag->any() ? 'podpis' : null) !== 'podpis') style="display: none" @endif class="mt-[22px]">
    <section id="grafika-podpisu" aria-labelledby="grafika-podpisu-tytul" class="max-w-[880px] scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]">
        <h2 id="grafika-podpisu-tytul" class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Grafika nad podpisem — w każdym mailu</h2>
        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Np. Twój odręczny podpis albo logo, w mailach po polsku i po angielsku. Pod grafiką zostaje podpis z pola „Podpis”, bo część programów pocztowych nie pokazuje obrazków. Przezroczyste tło dostaje kremowy kolor maila.</p>
        <div class="grid items-start gap-[18px] sm:grid-cols-2">
            <div class="min-w-0">
                <p class="mb-1 text-[12.5px] text-graphite">Tak to zobaczą klienci</p>
                @if ($signatureImage)
                    <div class="rounded-[3px] border border-sand-dark bg-cream px-[18px] py-4">
                        <img src="{{ $signatureImage['url'] }}" width="{{ $signatureImage['width'] }}" height="{{ $signatureImage['height'] }}" alt="Grafika nad podpisem" class="block h-auto max-w-full">
                        <p class="mt-1.5 font-[Georgia,'Times_New_Roman',serif] text-[18px] text-brown italic">{{ $signature }}</p>
                    </div>
                @else
                    <div class="grid min-h-[120px] place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark px-4 text-center text-[13px] text-hint">Bez grafiki — maile mają sam podpis</div>
                @endif
            </div>
            <div class="min-w-0">
                <form method="post" action="{{ route('admin.mail-signature-image.store') }}" enctype="multipart/form-data" novalidate x-data="{ sending: false }">
                    @csrf
                    <label class="block cursor-pointer rounded-[4px] border border-dashed border-line-strong bg-linen p-4 text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                        <span class="block text-[14px] text-lead" x-text="sending ? 'Wgrywam grafikę…' : @js($signatureImage ? 'Wgraj inną grafikę' : 'Wybierz grafikę z telefonu albo komputera')">{{ $signatureImage ? 'Wgraj inną grafikę' : 'Wybierz grafikę z telefonu albo komputera' }}</span>
                        <span class="mt-1 block text-[12.5px] text-hint">JPG, PNG albo WebP, co najmniej {{ \App\Modules\Settings\Http\Requests\Admin\StoreMailSignatureImageRequest::MIN_WIDTH }} px szerokości</span>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               x-on:change="if ($el.files.length) { sending = true; $el.form.requestSubmit() }">
                    </label>
                    @if ($imageBag->has('image'))
                        <p role="alert" class="mt-1.5 text-[13px] text-error">{{ $imageBag->first('image') }}</p>
                    @endif
                    <noscript><button class="mt-3 min-h-11 w-full rounded-full bg-ink px-4 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wgraj wybraną grafikę</button></noscript>
                </form>
                @if ($signatureImage)
                    <form method="post" action="{{ route('admin.mail-signature-image.destroy') }}" class="mt-1">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 text-[12.5px] text-label hover:text-ink">Zdejmij grafikę z maili</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
    </div>
  </x-admin::tabs>
</x-admin::layout>
