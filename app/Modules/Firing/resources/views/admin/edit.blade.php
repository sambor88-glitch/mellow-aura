@php
    $bag = $errors->getBag('wypaly');
    $fields = \App\Modules\Firing\Http\Requests\Admin\SaveKilnPricesRequest::FIELDS;

    // The saved rows, or what came back with a mistake, and one empty row for a new service.
    $rows = collect((array) old('prices', $prices))
        ->filter(fn (mixed $row) => is_array($row))
        ->reject(fn (array $row) => blank($row['label'] ?? null) && blank($row['price'] ?? null))
        ->values();
    $filled = $rows->count();
    $rows = [...$rows->all(), array_fill_keys($fields, '')];

    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';
@endphp
<x-admin::layout title="Wypały" lead="Cennik na stronie wypałów na zlecenie. Formularz zgłoszenia wsadu dojdzie po świętach — do tego czasu klienci piszą na WhatsAppie albo przez formularz.">
    <form id="cennik" method="post" action="{{ route('admin.firing.update') }}" novalidate class="max-w-[880px] scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]">
        @csrf
        @method('PUT')
        {{-- Enter in a field saves the card instead of pressing the first arrow. --}}
        <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

        <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-x-4">
            <h2 class="text-[11.5px] tracking-[0.16em] text-label uppercase">Cennik wypałów</h2>
            <a href="{{ route('firing.index') }}" target="_blank" rel="noopener" class="text-[13px]">Zobacz stronę →</a>
        </div>
        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Usługa bez nazwy albo ceny nie pokaże się na stronie. „Za” to dopisek przy cenie, np. / l albo / szt. — przy cenie za całość zostaw puste.</p>
        @if ($bag->any())
            <p role="alert" class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <div class="mb-5 grid gap-3">
            @foreach (['text_kiln_lead' => ['Zdanie pod nagłówkiem „Wypalę Twoje prace”', 300], 'text_kiln_note' => ['Notka pod cennikiem — np. kiedy zbierasz wsad', 400]] as $key => [$text, $max])
                <div class="min-w-0">
                    <label for="wypaly-{{ $key }}" class="{{ $label }}">{{ $text }}</label>
                    <textarea id="wypaly-{{ $key }}" name="{{ $key }}" rows="2" maxlength="{{ $max }}"
                              @class([$input, 'resize-y leading-[1.6]', 'border-error' => $bag->has($key), 'border-line' => ! $bag->has($key)])>{{ old($key, $key === 'text_kiln_lead' ? $lead : $note) }}</textarea>
                    @if ($bag->has($key))
                        <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($key) }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-3">
            @foreach ($rows as $index => $row)
                @php
                    $new = $index >= $filled;
                    $name = 'prices['.$index.']';
                    $id = 'wypal-'.$index;
                    $has = fn (string $field) => $bag->has('prices.'.$index.'.'.$field);
                @endphp
                <fieldset @class(['rounded-[4px] border p-3.5', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])>
                    <legend class="sr-only">{{ $new ? 'Nowa usługa' : ($row['label'] ?: 'Usługa '.($index + 1)) }}</legend>
                    <input type="hidden" name="{{ $name }}[code]" value="{{ $row['code'] ?? '' }}">
                    <input type="hidden" name="{{ $name }}[unit]" value="{{ $row['unit'] ?? '' }}">
                    <div class="grid gap-2.5 sm:grid-cols-[minmax(0,3fr)_minmax(0,1fr)_minmax(0,1fr)]">
                        <div class="min-w-0">
                            <label for="{{ $id }}-label" class="{{ $label }}">{{ $new ? 'Nowa usługa — nazwa' : 'Nazwa' }}</label>
                            <input id="{{ $id }}-label" name="{{ $name }}[label]" value="{{ $row['label'] ?? '' }}" maxlength="80" placeholder="np. Wypał na ostro do 1240°C"
                                   @class([$input, 'border-error' => $has('label'), 'border-line' => ! $has('label')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-price" class="{{ $label }}">Cena, zł</label>
                            <input id="{{ $id }}-price" name="{{ $name }}[price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="40"
                                   @class([$input, 'text-right tabular-nums', 'border-error' => $has('price'), 'border-line' => ! $has('price')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-unit" class="{{ $label }}">Za</label>
                            <input id="{{ $id }}-unit" name="{{ $name }}[unit_label]" value="{{ $row['unit_label'] ?? '' }}" maxlength="20" placeholder="/ l"
                                   @class([$input, 'border-error' => $has('unit_label'), 'border-line' => ! $has('unit_label')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-note" class="{{ $label }}">Dopisek pod nazwą</label>
                            <input id="{{ $id }}-note" name="{{ $name }}[note]" value="{{ $row['note'] ?? '' }}" maxlength="120" placeholder="np. liczony od litra zajętego miejsca"
                                   @class([$input, 'border-error' => $has('note'), 'border-line' => ! $has('note')])>
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $id }}-compare" class="{{ $label }}">Przed obniżką, zł</label>
                            <input id="{{ $id }}-compare" name="{{ $name }}[compare_at]" value="{{ $row['compare_at'] ?? '' }}" inputmode="decimal" placeholder="—"
                                   @class([$input, 'text-right tabular-nums', 'border-error' => $has('compare_at'), 'border-line' => ! $has('compare_at')])>
                        </div>
                    </div>
                    @foreach ($fields as $field)
                        @if ($has($field))
                            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('prices.'.$index.'.'.$field) }}</p>
                        @endif
                    @endforeach
                    @unless ($new)
                        <x-shared::admin.row-actions :name="$name" :index="$index" :last="$filled - 1" :label="$row['label'] ?: 'usługa '.($index + 1)" :removed="(bool) ($row['remove'] ?? false)" />
                    @endunless
                </fieldset>
            @endforeach
        </div>
        @if ($bag->has('prices'))
            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('prices') }}</p>
        @endif
        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">W pusty wiersz na dole wpisz nową usługę. Strzałki zapisują cały cennik i przestawiają pozycję o jedno miejsce. „Przed obniżką” wpisz tylko przy promocji: strona przekreśli tę cenę, gdy obniżysz cenę, i sama poda najniższą cenę z 30 dni.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Zapisz cennik wypałów</button>
    </form>
</x-admin::layout>
