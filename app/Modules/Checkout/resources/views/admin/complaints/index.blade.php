@use('App\Modules\Checkout\Enums\ComplaintDecision')
@use('App\Modules\Checkout\Enums\ComplaintRemedy')
@use('App\Modules\Checkout\Enums\MediationConsent')
@use('Carbon\CarbonImmutable')
@php
    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3.5 py-3 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1.5 block text-[13.5px] text-graphite';
    $chip = 'flex min-h-11 cursor-pointer items-center rounded-full border border-line bg-white px-4 text-[13.5px] text-lead has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
    $letter = session('complaint_letter');
    $receivedOn = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) old('received_on')), null, false);
    $deadline = $receivedOn?->addDays(14);
    $field = fn (string $name) => $errors->has($name) ? 'border-error' : 'border-line';
@endphp
<x-admin::layout title="Reklamacje" lead="Na reklamację odpowiadasz w 14 dni od dnia, w którym doszła — bez odpowiedzi prawo uznaje ją za przyjętą. Tu składasz odpowiedź, widzisz gotowy list i wysyłasz go e-mailem.">
    <div id="odpowiedz" class="flex scroll-mt-6 flex-wrap items-start gap-[22px]">
        <form method="post" action="{{ route('admin.complaints.store') }}" novalidate x-data="{ decision: @js((string) old('decision')) }"
              class="{{ $card }} grid min-w-0 flex-[1_1_380px] gap-4">
            @csrf
            <h2 class="text-[11.5px] tracking-[0.16em] text-label uppercase">Odpowiedź na reklamację</h2>
            @if ($errors->any())
                <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zobaczyć list.</p>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <x-shared::field name="name" id="reklamacja-imie" label="Imię i nazwisko" :value="old('name', $order?->name)" autocomplete="off" />
                <x-shared::field name="email" id="reklamacja-email" type="email" label="E-mail — tu pójdzie odpowiedź" :value="old('email', $order?->email)" autocomplete="off" />
                <x-shared::field name="order_number" id="reklamacja-zamowienie" label="Numer zamówienia — jeśli jest" :value="old('order_number', $order?->number)" placeholder="np. MA-2026-1047" />
                <x-shared::field name="received_on" id="reklamacja-data" type="date" label="Kiedy reklamacja doszła" :value="old('received_on', now()->format('Y-m-d'))" :max="now()->format('Y-m-d')"
                                 hint="Od tego dnia liczysz 14 dni na odpowiedź" />
            </div>

            <fieldset>
                <legend class="{{ $label }}">Twoja decyzja</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (ComplaintDecision::cases() as $decision)
                        <label class="{{ $chip }}">
                            <input type="radio" name="decision" value="{{ $decision->value }}" x-model="decision" @checked(old('decision') === $decision->value) class="sr-only">{{ $decision->label() }}
                        </label>
                    @endforeach
                </div>
                @error('decision')
                    <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset x-show="decision === '' || decision === @js(ComplaintDecision::Accepted->value)">
                <legend class="{{ $label }}">Jak ją załatwisz — przy uznaniu</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (ComplaintRemedy::cases() as $remedy)
                        <label class="{{ $chip }}">
                            <input type="radio" name="remedy" value="{{ $remedy->value }}" @checked(old('remedy') === $remedy->value) class="sr-only">{{ $remedy->label() }}
                        </label>
                    @endforeach
                </div>
                @error('remedy')
                    <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="min-w-0">
                <label for="reklamacja-szczegoly" class="{{ $label }}">Uzasadnienie i szczegóły</label>
                <textarea id="reklamacja-szczegoly" name="details" rows="5" maxlength="2000" aria-describedby="reklamacja-szczegoly-note"
                          placeholder="np. Pęknięcie na zdjęciu biegnie od uderzenia w brzeg, a nie od wady szkliwa."
                          class="{{ $input }} {{ $field('details') }} resize-y leading-[1.6]">{{ old('details') }}</textarea>
                @error('details')
                    <p id="reklamacja-szczegoly-note" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                @else
                    <p id="reklamacja-szczegoly-note" class="mt-1.5 text-[12.5px] text-hint">Przy odmowie napisz, dlaczego. Przy uznaniu możesz dopisać kwotę, termin i jak odesłać rzecz.</p>
                @enderror
            </div>

            <fieldset x-show="decision !== @js(ComplaintDecision::Accepted->value)">
                <legend class="{{ $label }}">Mediacja — gdy nie uznajesz całej reklamacji</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (MediationConsent::cases() as $consent)
                        <label class="{{ $chip }}">
                            <input type="radio" name="mediation" value="{{ $consent->value }}" @checked(old('mediation') === $consent->value) class="sr-only">{{ $consent->label() }}
                        </label>
                    @endforeach
                </div>
                @error('mediation')
                    <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                @else
                    <p class="mt-1.5 text-[12.5px] text-hint">List musi to powiedzieć. Bez tego oświadczenia prawo uznaje, że zgadzasz się na mediację.</p>
                @enderror
            </fieldset>

            <div class="flex flex-wrap gap-2.5">
                <button name="intent" value="preview" class="min-h-11 rounded-full border border-line-strong px-6 py-3 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.97]">Pokaż list</button>
                @if ($letter)
                    <button name="intent" value="send" class="min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wyślij odpowiedź e-mailem</button>
                @endif
            </div>
        </form>

        <section aria-labelledby="podglad-listu" class="min-w-0 flex-[1_1_320px]">
            <h2 id="podglad-listu" class="mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Tak dostanie to klientka</h2>
            @if ($letter)
                @if ($deadline)
                    <p @class(['mb-3 rounded-[4px] px-4 py-3 text-[13.5px]', 'border border-alert-line bg-alert text-alert-text' => $deadline->isPast() && ! $deadline->isToday(), 'bg-sand-dark text-graphite' => ! $deadline->isPast() || $deadline->isToday()])>
                        @if ($deadline->isPast() && ! $deadline->isToday())
                            Minęło 14 dni od {{ $receivedOn->format('j.m') }} — prawo traktuje tę reklamację jako uznaną. Odpowiedź i tak warto wysłać.
                        @else
                            Odpowiedz do {{ $deadline->format('j.m.Y') }}.
                        @endif
                    </p>
                @endif
                <div class="rounded-[4px] border border-line bg-white px-5 py-[18px] text-[14.5px] leading-[1.65] text-ink">
                    @foreach ($letter as $paragraph)
                        <p class="mb-3 whitespace-pre-line [overflow-wrap:anywhere] last:mb-0">{{ $paragraph }}</p>
                    @endforeach
                </div>
                <p class="mt-2.5 text-[12.5px] text-hint">Zmieniasz coś w formularzu? Kliknij „Pokaż list” jeszcze raz, zanim wyślesz.</p>
            @else
                <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-6 py-9 text-center text-[13.5px] text-label">
                    Wypełnij formularz i kliknij „Pokaż list” — tu zobaczysz całą odpowiedź, zanim ją wyślesz.
                </div>
            @endif
        </section>
    </div>

    <h2 class="mt-11 mb-3.5 font-serif text-[26px] font-light">Wysłane odpowiedzi</h2>
    @if ($answers->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Jeszcze nic tu nie ma</div>
            <p class="mx-auto max-w-[46ch] text-[13.5px] text-label">Każda wysłana odpowiedź zostaje tu słowo w słowo, z datą — to dowód, co i kiedy dostała klientka.</p>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($answers as $answer)
                <article class="rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="flex flex-wrap items-start gap-x-5 gap-y-2">
                        <div class="flex-none text-[12.5px] text-hint tabular-nums">{{ $answer->created_at->format('j.m.Y, H:i') }}</div>
                        <div class="min-w-0 flex-[1_1_240px]">
                            <h3 class="text-[14.5px] text-ink">{{ $answer->name }} <span class="text-[12.5px] text-label [overflow-wrap:anywhere]">· {{ $answer->email }}</span></h3>
                            <div class="mt-1 text-[13px] text-lead">
                                @if ($answer->order)
                                    <a href="{{ route('admin.orders.show', $answer->order) }}">{{ $answer->order_number }}</a> ·
                                @elseif ($answer->order_number)
                                    {{ $answer->order_number }} ·
                                @endif
                                reklamacja z {{ $answer->received_on->format('j.m.Y') }} · {{ $answer->decision->label() }}{{ $answer->mediation ? ' · '.Str::lcfirst($answer->mediation->label()) : '' }}
                            </div>
                        </div>
                        @if ($answer->emailed_at)
                            <span class="flex-none rounded-full bg-sand-dark px-3 py-[5px] text-[11.5px] text-label">Wysłana e-mailem</span>
                        @else
                            <span class="flex-none rounded-full bg-alert px-3 py-[5px] text-[11.5px] text-error">E-mail nie wyszedł — wyślij list ręcznie</span>
                        @endif
                    </div>
                    <details class="mt-2.5">
                        <summary class="cursor-pointer text-[13px] text-brown">Treść odpowiedzi</summary>
                        <p class="mt-2 rounded-[4px] bg-white px-4 py-3 text-[13.5px] leading-[1.6] whitespace-pre-line [overflow-wrap:anywhere] text-graphite">{{ $answer->letter }}</p>
                    </details>
                </article>
            @endforeach
        </div>
        @if ($answers->hasPages())
            <div class="mt-6 flex justify-between gap-4 text-[13.5px]">
                @if ($answers->previousPageUrl())
                    <a href="{{ $answers->previousPageUrl() }}">← Nowsze</a>
                @else
                    <span></span>
                @endif
                @if ($answers->nextPageUrl())
                    <a href="{{ $answers->nextPageUrl() }}">Starsze →</a>
                @endif
            </div>
        @endif
    @endif
</x-admin::layout>
