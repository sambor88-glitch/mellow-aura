{{-- Warsztaty (MA-320): the calendar first, the price list in a tab of its own, each workshop folded to its name and price. --}}
@php
    $bag = $errors->getBag('warsztaty');
    $fields = \App\Modules\Workshops\Http\Requests\Admin\SaveWorkshopTypesRequest::FIELDS;

    // The saved rows, or what came back with a mistake, and one empty row for a new workshop.
    $rows = collect((array) old('workshops', $workshops))
        ->filter(fn (mixed $row) => is_array($row))
        ->reject(fn (array $row) => blank($row['name'] ?? null) && blank($row['price'] ?? null))
        ->values();
    $filled = $rows->count();
    $rows = [...$rows->all(), array_fill_keys($fields, '')];

    $card = 'scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';

    $dateErrors = $errors->getBag('terminy');
    $seats = fn (int $count) => $count.' '.($count === 1 ? 'miejsce' : (in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) ? 'miejsca' : 'miejsc'));
@endphp
<x-admin::layout title="Warsztaty" lead="Cennik na stronie warsztatów i terminarz. Terminy pokażą się na stronie razem z zapisami online — do tego czasu klientki piszą na WhatsAppie albo przez formularz.">
  <x-admin::tabs :tabs="['terminy' => ['label' => 'Terminarz', 'count' => $dates->count()], 'cennik' => 'Cennik']" label="Części ekranu warsztatów">
    <x-admin::tab-panel name="terminy">
    <section id="terminy" aria-labelledby="terminy-tytul" class="flex max-w-[880px] scroll-mt-24 flex-wrap items-start gap-[22px]">
        <div class="{{ $card }} min-w-0 flex-[1_1_380px]">
            <h2 id="terminy-tytul" class="mb-1.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Terminarz</h2>
            <p class="mb-4 text-[13.5px] leading-[1.6] text-muted">Najbliższe terminy. Te, które minęły, znikają z listy same.</p>
            @if ($dates->isEmpty())
                <p class="rounded-[4px] border border-dashed border-line-strong px-4 py-5 text-[14px] text-muted">Nie ma jeszcze żadnego terminu. Dodaj pierwszy obok.</p>
            @else
                <ul class="overflow-hidden rounded-[4px] border border-line bg-white">
                    @foreach ($dates as $date)
                        <li id="termin-{{ $date->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-line px-4 py-3 last:border-b-0">
                            <div class="min-w-0 flex-[1_1_180px]">
                                <div class="text-[14.5px] text-ink">{{ $date->starts_at->translatedFormat('l, j F') }}</div>
                                <div class="mt-0.5 text-[12.5px] text-muted">{{ collect([$date->starts_at->format('H:i').($date->ends_at ? ' – '.$date->ends_at->format('H:i') : ''), $names[$date->workshop_code] ?? 'warsztatu nie ma już w cenniku'])->join(' · ') }}</div>
                            </div>
                            <span class="text-[12.5px] text-graphite tabular-nums">{{ $seats($date->seats) }}</span>
                            <a href="{{ route('admin.workshops.dates.bookings', $date) }}" class="inline-flex min-h-11 items-center text-[13px] tabular-nums">Zapisy · zajęte {{ $date->seatsTaken() }}</a>
                            <form method="post" action="{{ route('admin.workshops.dates.destroy', $date) }}"
                                  x-data x-on:submit="confirm('Usunąć ten termin z terminarza?') || $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <button aria-label="Usuń termin: {{ $names[$date->workshop_code] ?? 'warsztat' }}, {{ $date->starts_at->translatedFormat('j F, H:i') }}"
                                        class="min-h-11 rounded-full px-3 text-[13px] text-hint hover:text-error">Usuń</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <form method="post" action="{{ route('admin.workshops.dates.store') }}" novalidate class="{{ $card }} grid min-w-0 flex-[0_1_320px] gap-3">
            @csrf
            <h2 class="text-[11.5px] tracking-[0.16em] text-label uppercase">Dodaj termin</h2>
            @if ($names->isEmpty())
                <p class="text-[13.5px] leading-[1.6] text-muted">Najpierw wpisz w cenniku warsztat z nazwą i ceną.</p>
            @else
                @if ($dateErrors->any())
                    <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby dodać termin.</p>
                @endif
                @foreach ([
                    'workshop' => ['Warsztat', null],
                    'date' => ['Dzień', ['type' => 'date', 'min' => now()->format('Y-m-d')]],
                    'start' => ['Początek', ['type' => 'time', 'step' => 300]],
                    'end' => ['Koniec — jeśli chcesz podać', ['type' => 'time', 'step' => 300]],
                    'seats' => ['Ile miejsc', ['type' => 'number', 'min' => 1, 'max' => \App\Modules\Workshops\Http\Requests\Admin\StoreWorkshopDateRequest::MAX_SEATS, 'inputmode' => 'numeric', 'placeholder' => 'np. 6']],
                ] as $field => [$text, $extra])
                    @php
                        $has = $dateErrors->has($field);
                    @endphp
                    <div class="min-w-0">
                        <label for="termin-{{ $field }}" class="{{ $label }}">{{ $text }}</label>
                        @if ($field === 'workshop')
                            <select id="termin-workshop" name="workshop" @if ($has) aria-invalid="true" aria-describedby="termin-workshop-note" @endif
                                    @class([$input, 'border-error' => $has, 'border-line' => ! $has])>
                                @foreach ($names as $code => $name)
                                    <option value="{{ $code }}" @selected(old('workshop') === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="termin-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}"
                                   @foreach ($extra as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach
                                   @if ($has) aria-invalid="true" aria-describedby="termin-{{ $field }}-note" @endif
                                   @class([$input, 'tabular-nums', 'border-error' => $has, 'border-line' => ! $has])>
                        @endif
                        @if ($has)
                            <p id="termin-{{ $field }}-note" class="mt-1.5 text-[13px] text-error">{{ $dateErrors->first($field) }}</p>
                        @endif
                    </div>
                @endforeach
                <p class="text-[12.5px] leading-[1.6] text-label">Dzień wybierasz z kalendarza. Sesję 1:1 bez stałej daty umawiasz dalej na WhatsAppie.</p>
                <button class="mt-1 min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Dodaj do terminarza</button>
            @endif
        </form>
    </section>
    </x-admin::tab-panel>
    <x-admin::tab-panel name="cennik">
    <form id="cennik" method="post" action="{{ route('admin.workshops.update') }}" novalidate class="{{ $card }} max-w-[880px]">
        @csrf
        @method('PUT')
        {{-- Enter in a field saves the card instead of pressing the first arrow. --}}
        <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

        <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-x-4">
            <h2 class="text-[11.5px] tracking-[0.16em] text-label uppercase">Cennik warsztatów</h2>
            <a href="{{ route('workshops.index') }}" target="_blank" rel="noopener" class="text-[13px]">Zobacz stronę →</a>
        </div>
        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Warsztat bez nazwy albo ceny nie pokaże się na stronie. Puste pole — np. czas trwania — po prostu się nie wyświetli.</p>
        @if ($bag->any())
            <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <div class="mb-5 min-w-0">
            <label for="warsztaty-lead" class="{{ $label }}">Zdanie pod nagłówkiem „Zanurz dłonie w glinie”</label>
            <textarea id="warsztaty-lead" name="text_workshops_lead" rows="2" maxlength="300"
                      @class([$input, 'resize-y leading-[1.6]', 'border-error' => $bag->has('text_workshops_lead'), 'border-line' => ! $bag->has('text_workshops_lead')])>{{ old('text_workshops_lead', $lead) }}</textarea>
            @if ($bag->has('text_workshops_lead'))
                <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('text_workshops_lead') }}</p>
            @endif
        </div>

        <div class="mb-5 min-w-0">
            <label for="warsztaty-zabrac" class="{{ $label }}">Co zabrać ze sobą — po jednym w linii</label>
            <textarea id="warsztaty-zabrac" name="workshop_what_to_bring" rows="3" maxlength="400" aria-describedby="warsztaty-zabrac-podpowiedz"
                      placeholder="np. ubranie, którego nie szkoda pobrudzić"
                      @class([$input, 'resize-y leading-[1.6]', 'border-error' => $bag->has('workshop_what_to_bring'), 'border-line' => ! $bag->has('workshop_what_to_bring')])>{{ old('workshop_what_to_bring', $whatToBring) }}</textarea>
            <p id="warsztaty-zabrac-podpowiedz" class="mt-1.5 text-[13px] text-muted">Trafi tylko do maila po zapisie i do przypomnienia dzień przed. Puste pole — w mailu nie ma tej części.</p>
            @if ($bag->has('workshop_what_to_bring'))
                <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('workshop_what_to_bring') }}</p>
            @endif
        </div>

        <div class="grid gap-3">
            @foreach ($rows as $index => $row)
                @php
                    $new = $index >= $filled;
                    $name = 'workshops['.$index.']';
                    $id = 'warsztat-'.$index;
                    $has = fn (string $field) => $bag->has('workshops.'.$index.'.'.$field);
                    $error = fn (string $field) => $bag->first('workshops.'.$index.'.'.$field);
                @endphp
                <details @class(['group rounded-[4px] border', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])
                         @if (collect($fields)->contains(fn ($field) => $has($field))) open @endif>
                    <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 px-3.5 py-2.5 [&::-webkit-details-marker]:hidden">
                        <span class="min-w-0 flex-1 truncate text-[14.5px] text-ink">{{ $new ? '+ Nowy warsztat' : ($row['name'] ?: 'Warsztat '.($index + 1)) }}</span>
                        @unless ($new)
                            <span class="flex-none text-[13px] text-label tabular-nums">{{ filled($row['price'] ?? null) ? $row['price'].' zł' : 'bez ceny' }}</span>
                        @endunless
                        <span aria-hidden="true" class="flex-none text-[15px] text-label transition-transform group-open:rotate-180">⌄</span>
                    </summary>
                <fieldset class="px-3.5 pb-3.5">
                    <legend class="sr-only">{{ $new ? 'Nowy warsztat' : ($row['name'] ?: 'Warsztat '.($index + 1)) }}</legend>
                    <input type="hidden" name="{{ $name }}[code]" value="{{ $row['code'] ?? '' }}">
                    <input type="hidden" name="{{ $name }}[unit]" value="{{ $row['unit'] ?? '' }}">
                    <div class="grid gap-2.5 sm:grid-cols-[minmax(0,3fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="min-w-0">
                            <label for="{{ $id }}-name" class="{{ $label }}">{{ $new ? 'Nowy warsztat — nazwa' : 'Nazwa' }}</label>
                            <input id="{{ $id }}-name" name="{{ $name }}[name]" value="{{ $row['name'] ?? '' }}" maxlength="60" placeholder="np. Lepienie z ręki"
                                   @class([$input, 'border-error' => $has('name'), 'border-line' => ! $has('name')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-price" class="{{ $label }}">Cena, zł</label>
                            <input id="{{ $id }}-price" name="{{ $name }}[price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="220"
                                   @class([$input, 'text-right tabular-nums', 'border-error' => $has('price'), 'border-line' => ! $has('price')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-unit" class="{{ $label }}">Za</label>
                            <input id="{{ $id }}-unit" name="{{ $name }}[unit_label]" value="{{ $row['unit_label'] ?? '' }}" maxlength="30" placeholder="os."
                                   @class([$input, 'border-error' => $has('unit_label'), 'border-line' => ! $has('unit_label')])>
                        </div>
                        <div class="min-w-0 sm:col-span-1">
                            <label for="{{ $id }}-duration" class="{{ $label }}">Czas</label>
                            <input id="{{ $id }}-duration" name="{{ $name }}[duration_label]" value="{{ $row['duration_label'] ?? '' }}" maxlength="30" placeholder="2,5 godziny"
                                   @class([$input, 'border-error' => $has('duration_label'), 'border-line' => ! $has('duration_label')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-compare" class="{{ $label }}">Przed obniżką, zł</label>
                            <input id="{{ $id }}-compare" name="{{ $name }}[compare_at]" value="{{ $row['compare_at'] ?? '' }}" inputmode="decimal" placeholder="—"
                                   @class([$input, 'text-right tabular-nums', 'border-error' => $has('compare_at'), 'border-line' => ! $has('compare_at')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-group" class="{{ $label }}">Grupa</label>
                            <input id="{{ $id }}-group" name="{{ $name }}[group_label]" value="{{ $row['group_label'] ?? '' }}" maxlength="40" placeholder="grupa do 6 osób"
                                   @class([$input, 'border-error' => $has('group_label'), 'border-line' => ! $has('group_label')])>
                        </div>
                        <div class="min-w-0 sm:col-span-3">
                            <label for="{{ $id }}-summary" class="{{ $label }}">Opis — jedno, dwa zdania</label>
                            <textarea id="{{ $id }}-summary" name="{{ $name }}[summary]" rows="2" maxlength="220"
                                      @class([$input, 'resize-y leading-[1.55]', 'border-error' => $has('summary'), 'border-line' => ! $has('summary')])>{{ $row['summary'] ?? '' }}</textarea>
                        </div>
                        <div class="min-w-0 sm:col-span-3">
                            <label for="{{ $id }}-includes" class="{{ $label }}">Co w cenie — każda rzecz w osobnej linii</label>
                            <textarea id="{{ $id }}-includes" name="{{ $name }}[includes]" rows="3" maxlength="400"
                                      @class([$input, 'resize-y leading-[1.55]', 'border-error' => $has('includes'), 'border-line' => ! $has('includes')])>{{ $row['includes'] ?? '' }}</textarea>
                        </div>
                    </div>
                    @foreach ($fields as $field)
                        @if ($has($field))
                            <p class="mt-1.5 text-[13px] text-error">{{ $error($field) }}</p>
                        @endif
                    @endforeach
                    @unless ($new)
                        <x-shared::admin.row-actions :name="$name" :index="$index" :last="$filled - 1" :label="$row['name'] ?: 'warsztat '.($index + 1)" :removed="(bool) ($row['remove'] ?? false)" />
                    @endunless
                </fieldset>
                </details>
            @endforeach
        </div>
        @if ($bag->has('workshops'))
            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('workshops') }}</p>
        @endif
        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">W pusty wiersz na dole wpisz nowy warsztat. Strzałki zapisują cały cennik i przestawiają warsztat o jedno miejsce. „Przed obniżką” wpisz tylko przy promocji: strona przekreśli tę cenę, gdy obniżysz cenę, i sama poda najniższą cenę z 30 dni.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz cennik warsztatów</button>
    </form>
    </x-admin::tab-panel>
  </x-admin::tabs>
</x-admin::layout>
