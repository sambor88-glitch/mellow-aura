@inject('settings', 'App\Modules\Settings\Settings')
@php
    $email = $settings->get('contact_email');
    $scope = old('scope', 'whole');
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[4px] border border-line bg-white px-[18px] py-4 has-checked:border-ink has-checked:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout title="Odstąpienie od umowy | MellowAura" description="Formularz „Odstąp od umowy tutaj”: 14 dni od odebrania paczki, bez podawania przyczyny. Potwierdzenie z datą i godziną przyjdzie mailem." :noindex="true">
    <div class="mx-auto max-w-[900px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">zwroty</div>
        <h1 class="mb-6 font-serif text-[length:clamp(38px,5.2vw,64px)] leading-[1.04] font-light tracking-[-0.02em]">Odstąpienie od umowy</h1>
        <p class="mb-4 max-w-[58ch] text-[17px] leading-[1.7] text-pretty text-lead">
            Na odstąpienie od umowy masz 14 dni od odebrania paczki, bez podawania przyczyny. Wypełnij formularz i kliknij „Potwierdź odstąpienie od umowy” —
            od razu dostaniesz maila z treścią oświadczenia, datą i godziną.
        </p>
        <p class="mb-10 max-w-[58ch] text-[14.5px] leading-[1.7] text-muted">
            Prawo odstąpienia nie dotyczy rzeczy zrobionych specjalnie dla Ciebie, np. kubka z Twoim napisem, ani warsztatów w wyznaczonym terminie.
            Wszystkie zasady są w&nbsp;<a href="{{ route('content.terms') }}#regulamin-12-prawo-odstapienia-od-umowy">regulaminie, w&nbsp;§12</a>.
        </p>

        <div class="rounded-[4px] border border-divider bg-cream px-[30px] py-8">
            @if (session('withdrawal_throttled'))
                <p role="alert" class="mb-5 rounded-[4px] border border-alert-line bg-alert p-4 text-[14px] leading-[1.6] text-alert-text">
                    Z tego urządzenia przyszło w ostatniej godzinie kilka oświadczeń. Kolejne wyślesz za godzinę{{ $email ? ' albo od razu mailem na '.$email : '' }} — Twoje dane są nadal w formularzu.
                </p>
            @endif

            <form method="post" action="{{ route('withdrawal.store') }}" novalidate class="grid gap-[13px]">
                @csrf
                <x-shared::field name="name" id="withdrawal-name" label="Imię i nazwisko" autocomplete="name" />
                <x-shared::field name="email" id="withdrawal-email" label="E-mail" type="email" autocomplete="email" hint="Na ten adres wyślę potwierdzenie — najlepiej ten z zamówienia" />
                <x-shared::field name="order_number" id="withdrawal-order" label="Numer zamówienia" :value="old('order_number', $orderNumber)" autocapitalize="characters" placeholder="np. MA-2026-1047" hint="Jest w mailu z potwierdzeniem zamówienia" />

                <fieldset class="mt-2" @error('scope') aria-describedby="withdrawal-scope-error" @enderror>
                    <legend class="mb-2.5 text-[13.5px] text-graphite">Od czego odstępujesz?</legend>
                    <div class="grid gap-2.5">
                        @foreach (['whole' => 'Od całego zamówienia', 'part' => 'Od części zamówienia'] as $value => $label)
                            <label class="{{ $radio }}">
                                <input type="radio" name="scope" value="{{ $value }}" @checked($scope === $value) class="sr-only">
                                <span class="grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                                <span class="text-[15px]">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('scope')
                        <p id="withdrawal-scope-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="min-w-0">
                    <label for="withdrawal-items" class="mb-1.5 block text-[13.5px] text-graphite">Które rzeczy? — tylko przy części zamówienia</label>
                    <textarea id="withdrawal-items" name="items" rows="3" maxlength="2000"
                              aria-describedby="withdrawal-items-note"
                              @error('items') aria-invalid="true" @enderror
                              @class([
                                  'w-full min-w-0 resize-y rounded-[4px] border bg-white px-4 py-[14px] text-[15px] leading-[1.6] text-ink placeholder:text-hint focus:border-ink',
                                  'border-error' => $errors->has('items'),
                                  'border-line' => ! $errors->has('items'),
                              ])
                              placeholder="np. Kubek „Królowa matka”, 1 szt.">{{ old('items') }}</textarea>
                    @error('items')
                        <p id="withdrawal-items-note" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                    @else
                        <p id="withdrawal-items-note" class="mt-1.5 text-[12.5px] text-hint">Nazwa i rozmiar wystarczą</p>
                    @enderror
                </div>

                <button class="mt-2 rounded-full bg-ink p-4 text-[14.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Potwierdź odstąpienie od umowy</button>
                <p class="text-[12px] leading-[1.5] text-hint">Dane z formularza zapisuję, żeby rozliczyć zwrot. Więcej w <a href="{{ route('content.privacy') }}">polityce prywatności</a>.</p>
            </form>
        </div>
    </div>
</x-shared::layout>
