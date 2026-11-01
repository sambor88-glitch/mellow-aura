@use('App\Modules\Content\Enums\Service')
@use('App\Modules\Content\Http\Requests\Admin\SaveServiceTextsRequest')
@use('App\Modules\Shared\Support\FocalPoint')
@php
    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $heading = 'text-[11.5px] tracking-[0.16em] text-label uppercase';
    $intro = 'mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $hint = 'mt-3.5 text-[12.5px] leading-[1.6] text-label';
    $button = 'mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';
    $small = 'grid min-h-11 min-w-11 place-items-center rounded-full border border-line-strong px-3 text-[13px] text-ink hover:border-ink hover:bg-sand-dark';

    $isScarf = $service === Service::Scarf;
    $pageUrl = route($service->route());

    // Saved rows (or what came back with a mistake) and one empty row for a new entry.
    $listRows = function (string $name, array $saved, array $fields) {
        $rows = collect((array) old($name, $saved))
            ->filter(fn (mixed $row) => is_array($row))
            ->reject(fn (array $row) => collect($fields)->every(fn (string $field) => blank($row[$field] ?? null)))
            ->values();

        return [$rows->count(), [...$rows->all(), array_fill_keys($fields, '')]];
    };
    [$filledPrices, $priceRows] = $listRows('prices', $prices, ['label', 'price', 'note', 'compare_at']);
    [$filledSteps, $stepRows] = $listRows('steps', $steps, ['title', 'text']);
    // Blade can't mix @php(...) with @php blocks in one view, so every bag is picked here.
    $pairErrors = $errors->getBag('przed-i-po');
    $textErrors = $errors->getBag('teksty');
    $priceErrors = $errors->getBag('cennik');
    $stepErrors = $errors->getBag('kroki');
    $small = 'mb-1 block text-[12.5px] text-graphite';
@endphp
<x-admin::layout title="Usługi" lead="Strony „Z Twojej apaszki” i „Odcisk Twojej rośliny”: zdjęcia przed i po, teksty, cennik i kroki. Zmiany widać na stronie od razu.">
    <div class="mb-3.5 flex flex-wrap items-center gap-x-2.5 gap-y-1">
        <nav aria-label="Usługi" class="pill-grid flex-[1_1_100%] md:flex-none">
            @foreach (Service::cases() as $tab)
                <a href="{{ route('admin.services.edit', $tab->slug()) }}" @if ($tab === $service) aria-current="page" @endif
                   @class([
                       'rounded-full border px-[18px] py-[9px] text-[13.5px]',
                       'border-ink bg-ink text-linen hover:text-linen' => $tab === $service,
                       'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink' => $tab !== $service,
                   ])>{{ $tab->label() }}</a>
            @endforeach
        </nav>
        <a href="{{ $pageUrl }}" target="_blank" rel="noopener" class="ml-auto inline-flex min-h-11 items-center text-[13px]">Zobacz stronę →</a>
    </div>

    {{-- One card at a time (MA-323); every card keeps its #id, so saving comes back to its tab. --}}
  <x-admin::tabs :tabs="['przed-i-po' => ['label' => 'Przed i po', 'count' => count($examples)], 'teksty' => 'Teksty', 'cennik' => 'Cennik', 'kroki' => 'Kroki']" label="Części strony usługi">
    <div class="grid max-w-[880px] gap-[22px]">
      <x-admin::tab-panel name="przed-i-po">
        <section id="przed-i-po" class="{{ $card }}">
            <h2 class="{{ $heading }} mb-1.5">Przed i po</h2>
            <p class="{{ $intro }}">
                {{ $isScarf ? 'Zdjęcie tkaniny, którą dostałaś, obok tego, co z niej uszyłaś.' : 'Zdjęcie przysłanej rośliny obok gotowej ceramiki.' }}
                Strona pokazuje oba zdjęcia w tym samym pionowym kadrze 4:5, więc najlepiej robić je w pionie. Jeśli coś ważnego się ucina, przesuń kadr suwakami pod zdjęciem. Bez par sekcja na stronie się nie pokazuje.
            </p>

            @if ($examples->isEmpty())
                <div class="mb-4 rounded-[4px] border border-dashed border-line-strong bg-linen px-6 py-7 text-center">
                    <div class="mb-1.5 font-serif text-[20px]">Jeszcze nie ma par</div>
                    <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">Pierwszą dodasz poniżej — zdjęcie „przed” i zdjęcie „po”.</p>
                </div>
            @else
                <div class="mb-4 grid gap-3">
                    @foreach ($examples as $example)
                        @php
                            $bag = $errors->getBag('para-'.$example->id);
                        @endphp
                        <article id="para-{{ $example->id }}" class="scroll-mt-6 rounded-[4px] border border-sand-dark bg-linen p-3.5">
                            <form method="post" action="{{ route('admin.services.examples.update', $example) }}" novalidate class="flex flex-wrap gap-3.5">
                                @csrf
                                @method('PUT')
                                <div class="flex w-[240px] max-w-full flex-none gap-2">
                                    @foreach (['before' => 'przed', 'after' => 'po'] as $side => $word)
                                        <figure class="m-0 min-w-0 flex-1">
                                            @if ($media = $example->getFirstMedia($side))
                                                @php
                                                    $point = $bag->any() ? old('focus.'.$side, FocalPoint::of($media)) : FocalPoint::of($media);
                                                @endphp
                                                <x-shared::focus-picker name="focus[{{ $side }}]" :src="$media->getAvailableUrl(['thumb'])" :x="$point['x'] ?? 50" :y="$point['y'] ?? 50"
                                                                        label="Kadr zdjęcia „{{ $word }}”" :frames="['aspect-[4/5]' => null]" />
                                            @else
                                                <div class="grid aspect-[4/5] w-full place-items-center rounded-[3px] border border-dashed border-line-strong text-center text-[11px] text-error">brak zdjęcia</div>
                                            @endif
                                            <figcaption class="mt-1 text-center text-[11px] tracking-[0.12em] text-label uppercase">{{ $word }}</figcaption>
                                        </figure>
                                    @endforeach
                                </div>
                                <div class="grid min-w-0 flex-[1_1_300px] content-start gap-2">
                                    @if ($bag->any())
                                        <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-3 py-2 text-[13px] text-alert-text">{{ $bag->first() }}</p>
                                    @endif
                                    <div class="min-w-0">
                                        <label for="para-{{ $example->id }}-caption" class="{{ $label }}">Podpis pod zdjęciami</label>
                                        <input id="para-{{ $example->id }}-caption" name="caption" value="{{ $bag->any() ? old('caption') : $example->caption }}" maxlength="160"
                                               placeholder="{{ $isScarf ? 'np. apaszka babci z lat 70.' : 'np. bukiet ślubny, talerz 24 cm' }}" class="{{ $input }} border-line">
                                    </div>
                                    <details @if ($bag->any()) open @endif class="group">
                                        <summary class="flex min-h-11 cursor-pointer list-none items-center text-[13px] text-brown [&::-webkit-details-marker]:hidden">
                                            <span class="group-open:hidden">Opisy zdjęć dla czytnika ekranu +</span><span class="hidden group-open:inline">Zwiń opisy −</span>
                                        </summary>
                                        <div class="grid gap-2">
                                            @foreach (['before_alt' => 'Co widać na zdjęciu „przed”', 'after_alt' => 'Co widać na zdjęciu „po”'] as $name => $text)
                                                <div class="min-w-0">
                                                    <label for="para-{{ $example->id }}-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                                                    <input id="para-{{ $example->id }}-{{ $name }}" name="{{ $name }}" value="{{ $bag->any() ? old($name) : $example->{$name} }}" maxlength="160"
                                                           placeholder="{{ $service->defaultAlts()[$name === 'before_alt' ? 'before' : 'after'] }}" class="{{ $input }} border-line">
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button class="min-h-11 rounded-full border border-ink px-4 text-[13px] text-ink hover:bg-sand-dark">Zapisz opis i kadr</button>
                                    </div>
                                </div>
                            </form>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2 border-t border-dashed border-sand-dark pt-2.5">
                                @unless ($loop->first)
                                    <form method="post" action="{{ route('admin.services.examples.move', $example) }}">
                                        @csrf
                                        <input type="hidden" name="kierunek" value="gora">
                                        <button aria-label="Przesuń wyżej parę {{ $loop->iteration }}" class="{{ $small }}">↑</button>
                                    </form>
                                @endunless
                                @unless ($loop->last)
                                    <form method="post" action="{{ route('admin.services.examples.move', $example) }}">
                                        @csrf
                                        <input type="hidden" name="kierunek" value="dol">
                                        <button aria-label="Przesuń niżej parę {{ $loop->iteration }}" class="{{ $small }}">↓</button>
                                    </form>
                                @endunless
                                <form method="post" action="{{ route('admin.services.examples.destroy', $example) }}" class="ml-auto"
                                      x-data x-on:submit="confirm('Usunąć tę parę zdjęć? Nie da się tego cofnąć.') || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button class="min-h-11 rounded-full px-3 text-[13px] text-hint hover:text-error">Usuń parę</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('admin.services.examples.store', $service->slug()) }}" enctype="multipart/form-data" novalidate
                  x-data="pairUpload(@js($photoLimits))" x-on:submit="submit($event)"
                  class="rounded-[4px] border border-dashed border-line-strong p-3.5">
                @csrf
                <h3 class="mb-2.5 font-serif text-[19px]">Nowa para</h3>
                @if ($pairErrors->any())
                    <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby dodać parę.</p>
                @endif
                <p x-show="error" x-text="error" x-cloak role="alert" class="{{ $alert }}"></p>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    @foreach (['before' => ['Zdjęcie „przed”', $isScarf ? 'tkanina, która przyszła' : 'przysłana roślina'], 'after' => ['Zdjęcie „po”', $isScarf ? 'to, co z niej uszyłaś' : 'ceramika z odciskiem']] as $side => [$title, $what])
                        <div class="min-w-0">
                            <label class="flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-1 rounded-[4px] border border-dashed border-line-strong bg-linen p-3 text-center transition duration-300 hover:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                <img x-show="previews.{{ $side }}" x-cloak x-bind:src="previews.{{ $side }}" alt="" width="72" height="90" class="mb-1 h-[90px] w-[72px] rounded-[3px] object-cover">
                                <span class="text-[14px] text-ink">{{ $title }}</span>
                                <span class="text-[12.5px] text-hint">{{ $what }} — JPG, PNG albo WebP</span>
                                <input type="file" name="{{ $side }}" accept="image/jpeg,image/png,image/webp" x-on:change="choose('{{ $side }}', $event.target)" class="sr-only">
                            </label>
                            @if ($pairErrors->has($side))
                                <p class="mt-1.5 text-[13px] text-error">{{ $pairErrors->first($side) }}</p>
                            @endif
                        </div>
                    @endforeach
                    <div class="min-w-0 sm:col-span-2">
                        <label for="nowa-para-caption" class="{{ $label }}">Podpis pod zdjęciami — nieobowiązkowy</label>
                        <input id="nowa-para-caption" name="caption" value="{{ old('caption') }}" maxlength="160" placeholder="{{ $isScarf ? 'np. apaszka babci z lat 70.' : 'np. bukiet ślubny, talerz 24 cm' }}"
                               @class([$input, 'border-error' => $pairErrors->has('caption'), 'border-line' => ! $pairErrors->has('caption')])>
                    </div>
                    @foreach (['before_alt' => 'Co widać na zdjęciu „przed”', 'after_alt' => 'Co widać na zdjęciu „po”'] as $name => $text)
                        <div class="min-w-0">
                            <label for="nowa-para-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                            <input id="nowa-para-{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" maxlength="160" placeholder="{{ $service->defaultAlts()[$name === 'before_alt' ? 'before' : 'after'] }}"
                                   @class([$input, 'border-error' => $pairErrors->has($name), 'border-line' => ! $pairErrors->has($name)])>
                        </div>
                    @endforeach
                </div>
                <p class="{{ $hint }}">Opisy czyta czytnik ekranu osobom niewidomym. Puste zastąpię ogólnym opisem.</p>
                <button x-bind:disabled="sending" class="{{ $button }}"><span x-text="sending ? 'Wysyłam zdjęcia…' : 'Dodaj parę zdjęć'">Dodaj parę zdjęć</span></button>
            </form>
        </section>
      </x-admin::tab-panel>

        {{-- Teksty --}}
      <x-admin::tab-panel name="teksty">
        <form id="teksty" method="post" action="{{ route('admin.services.texts', $service->slug()) }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }} mb-1.5">Teksty na stronie</h2>
            <p class="{{ $intro }}">Enter w nagłówku przenosi wyraz do nowej linii — dokładnie tak, jak zobaczy to klient. Puste pole się nie pokaże.</p>
            @if ($textErrors->any())
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-3">
                @foreach (['heading' => ['Nagłówek', 3], 'lead' => ['Pierwszy akapit — większym pismem', 4], 'lead_2' => ['Drugi akapit', 3], 'note' => ['Notka pod cennikiem', 3]] as $name => [$text, $rows])
                    <div class="min-w-0">
                        <label for="teksty-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                        <textarea id="teksty-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" maxlength="{{ SaveServiceTextsRequest::FIELDS[$name] }}"
                                  @class([$input, 'resize-y leading-[1.6]', 'border-error' => $textErrors->has($name), 'border-line' => ! $textErrors->has($name)])>{{ old($name, $texts[$name]) }}</textarea>
                        @if ($textErrors->has($name))
                            <p class="mt-1.5 text-[13px] text-error">{{ $textErrors->first($name) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <button class="{{ $button }}">Zapisz teksty</button>
        </form>
      </x-admin::tab-panel>

        {{-- Cennik --}}
      <x-admin::tab-panel name="cennik">
        <form id="cennik" method="post" action="{{ route('admin.services.prices', $service->slug()) }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>
            <h2 class="{{ $heading }} mb-1.5">Cennik</h2>
            <p class="{{ $intro }}">Pozycja bez nazwy albo ceny nie pokaże się na stronie. „Przed obniżką” wpisz tylko przy promocji: strona przekreśli tę cenę, gdy obniżysz cenę, i sama poda najniższą cenę z 30 dni.</p>
            @if ($priceErrors->any())
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-2.5">
                @foreach ($priceRows as $index => $row)
                    @php
                        $new = $index >= $filledPrices;
                        $has = fn (string $name) => $priceErrors->has('prices.'.$index.'.'.$name);
                    @endphp
                    <fieldset @class(['rounded-[4px] border p-3', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])>
                        <legend class="sr-only">{{ $new ? 'Nowa pozycja' : ($row['label'] ?: 'Pozycja '.($index + 1)) }}</legend>
                        {{-- Every field says what it is: a price without a label read like the promotional one (MA-335). --}}
                        <div class="grid grid-cols-2 gap-x-2.5 gap-y-2">
                            <label class="col-span-2 min-w-0">
                                <span class="{{ $small }}">{{ $new ? 'Nowa pozycja — nazwa' : 'Nazwa' }}</span>
                                <input name="prices[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" maxlength="80" placeholder="np. Opaska szeroka"
                                       @class([$input, 'border-error' => $has('label'), 'border-line' => ! $has('label')])>
                            </label>
                            <label class="col-span-2 min-w-0">
                                <span class="{{ $small }}">Dopisek pod nazwą</span>
                                <input name="prices[{{ $index }}][note]" value="{{ $row['note'] ?? '' }}" maxlength="120" placeholder="np. z apaszki 65 × 65 cm"
                                       @class([$input, 'border-error' => $has('note'), 'border-line' => ! $has('note')])>
                            </label>
                            <label class="min-w-0">
                                <span class="{{ $small }}">Cena, zł</span>
                                <input name="prices[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="np. 119"
                                       @class([$input, 'text-right tabular-nums', 'border-error' => $has('price'), 'border-line' => ! $has('price')])>
                            </label>
                            <label class="min-w-0">
                                <span class="{{ $small }}">Przed obniżką, zł</span>
                                <input name="prices[{{ $index }}][compare_at]" value="{{ $row['compare_at'] ?? '' }}" inputmode="decimal" placeholder="tylko przy promocji"
                                       @class([$input, 'text-right tabular-nums', 'border-error' => $has('compare_at'), 'border-line' => ! $has('compare_at')])>
                            </label>
                        </div>
                        @foreach (['label', 'price', 'note', 'compare_at'] as $name)
                            @if ($has($name))
                                <p class="mt-1.5 text-[13px] text-error">{{ $priceErrors->first('prices.'.$index.'.'.$name) }}</p>
                            @endif
                        @endforeach
                        @unless ($new)
                            <x-shared::admin.row-actions :name="'prices['.$index.']'" :index="$index" :last="$filledPrices - 1" :label="$row['label'] ?: 'pozycja '.($index + 1)" :removed="(bool) ($row['remove'] ?? false)" />
                        @endunless
                    </fieldset>
                @endforeach
            </div>
            <button class="{{ $button }}">Zapisz cennik</button>
        </form>
      </x-admin::tab-panel>

        {{-- Kroki --}}
      <x-admin::tab-panel name="kroki">
        <form id="kroki" method="post" action="{{ route('admin.services.steps', $service->slug()) }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>
            <h2 class="{{ $heading }} mb-1.5">Kroki</h2>
            <p class="{{ $intro }}">Numerowane kroki pod zdjęciem. Kod Twojego Paczkomatu z <a href="{{ route('admin.settings.edit') }}#pracownia">Ustawień</a> pokazuje się pod nimi sam.</p>
            @if ($stepErrors->any())
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-2.5">
                @foreach ($stepRows as $index => $row)
                    @php
                        $new = $index >= $filledSteps;
                        $has = fn (string $name) => $stepErrors->has('steps.'.$index.'.'.$name);
                    @endphp
                    <fieldset @class(['rounded-[4px] border p-3', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])>
                        <legend class="sr-only">{{ $new ? 'Nowy krok' : 'Krok '.($index + 1) }}</legend>
                        <div class="grid gap-2">
                            <input name="steps[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" maxlength="60" aria-label="Krok {{ $index + 1 }} — nazwa" placeholder="{{ $new ? 'Nowy krok' : 'Nazwa kroku' }}"
                                   @class([$input, 'border-error' => $has('title'), 'border-line' => ! $has('title')])>
                            <textarea name="steps[{{ $index }}][text]" rows="2" maxlength="240" aria-label="Krok {{ $index + 1 }} — opis" placeholder="Jedno, dwa zdania"
                                      @class([$input, 'resize-y leading-[1.55]', 'border-error' => $has('text'), 'border-line' => ! $has('text')])>{{ $row['text'] ?? '' }}</textarea>
                        </div>
                        @foreach (['title', 'text'] as $name)
                            @if ($has($name))
                                <p class="mt-1.5 text-[13px] text-error">{{ $stepErrors->first('steps.'.$index.'.'.$name) }}</p>
                            @endif
                        @endforeach
                        @unless ($new)
                            <x-shared::admin.row-actions :name="'steps['.$index.']'" :index="$index" :last="$filledSteps - 1" :label="$row['title'] ?: 'krok '.($index + 1)" :removed="(bool) ($row['remove'] ?? false)" />
                        @endunless
                    </fieldset>
                @endforeach
            </div>
            <button class="{{ $button }}">Zapisz kroki</button>
        </form>
      </x-admin::tab-panel>
    </div>
  </x-admin::tabs>
</x-admin::layout>
