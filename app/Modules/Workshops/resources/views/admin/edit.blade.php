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

    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';
@endphp
<x-admin::layout title="Warsztaty" lead="Cennik na stronie warsztatów. Zapisy i terminy dojdą po świętach — do tego czasu klientki piszą na WhatsAppie albo przez formularz.">
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
            <p role="alert" class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <div class="mb-5 min-w-0">
            <label for="warsztaty-lead" class="{{ $label }}">Zdanie pod nagłówkiem „Zanurz dłonie w glinie”</label>
            <textarea id="warsztaty-lead" name="text_workshops_lead" rows="2" maxlength="300"
                      @class([$input, 'resize-y leading-[1.6]', 'border-error' => $bag->has('text_workshops_lead'), 'border-line' => ! $bag->has('text_workshops_lead')])>{{ old('text_workshops_lead', $lead) }}</textarea>
            @if ($bag->has('text_workshops_lead'))
                <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('text_workshops_lead') }}</p>
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
                <fieldset @class(['rounded-[4px] border p-3.5', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])>
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
            @endforeach
        </div>
        @if ($bag->has('workshops'))
            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('workshops') }}</p>
        @endif
        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">W pusty wiersz na dole wpisz nowy warsztat. Strzałki zapisują cały cennik i przestawiają warsztat o jedno miejsce. „Przed obniżką” wpisz tylko przy promocji: strona przekreśli tę cenę, gdy obniżysz cenę, i sama poda najniższą cenę z 30 dni.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz cennik warsztatów</button>
    </form>
</x-admin::layout>
