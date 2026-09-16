@use('App\Modules\Content\Http\Requests\Admin\SavePageTextsRequest')
@php
    // Four forms on one page: each card saves on its own and keeps its own mistakes.
    $textErrors = $errors->getBag('teksty');

    // A list shows the saved rows (or what was just sent back with a mistake) and two empty rows for new entries.
    $listRows = function (string $name, array $saved, array $fields) {
        $rows = collect((array) old($name, $saved))
            ->filter(fn (mixed $row) => is_array($row))
            ->reject(fn (array $row) => collect($fields)->every(fn (string $field) => blank($row[$field] ?? null)))
            ->values();

        return [$rows->count(), [...$rows->all(), ...array_fill(0, 2, array_fill_keys($fields, ''))]];
    };

    $lists = [
        ['id' => 'czeste-pytania', 'name' => 'faq', 'fields' => ['question', 'answer'], 'rows' => $questions, 'route' => 'admin.content.faq'],
        ['id' => 'pracownia', 'name' => 'facts', 'fields' => ['title', 'text'], 'rows' => $facts, 'route' => 'admin.content.facts'],
        ['id' => 'zamowienia-indywidualne', 'name' => 'steps', 'fields' => ['title', 'text'], 'rows' => $customOrderSteps, 'route' => 'admin.content.custom-order-steps'],
        ['id' => 'gastronomia', 'name' => 'b2b', 'fields' => ['title', 'text'], 'rows' => $b2bFacts, 'route' => 'admin.content.b2b-facts'],
        ['id' => 'kontakt', 'name' => 'topics', 'fields' => ['label'], 'rows' => $topics, 'route' => 'admin.content.topics'],
    ];

    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $heading = 'mb-1.5 text-[11.5px] tracking-[0.16em] text-label uppercase';
    $intro = 'mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $hint = 'mt-3.5 text-[12.5px] leading-[1.6] text-label';
    $button = 'mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
@endphp
<x-admin::layout title="Treści" lead="Każde zdanie na stronach „O mnie”, „Pracownia”, „Zamówienia indywidualne”, „Dla kawiarni i restauracji”, „Kontakt” i w częstych pytaniach. Zmiany widać na stronie od razu.">
    <nav aria-label="Karty na tej stronie" class="mb-[22px] flex flex-wrap gap-2.5 text-[13px]">
        @foreach (['teksty' => 'Teksty na stronach', 'czeste-pytania' => 'Częste pytania', 'pracownia' => 'Pracownia — dobrze wiedzieć', 'zamowienia-indywidualne' => 'Zamówienia indywidualne — kroki', 'gastronomia' => 'Dla lokali — karty', 'kontakt' => 'Sprawy w formularzu'] as $id => $label)
            <a href="#{{ $id }}" class="rounded-full border border-line-strong px-4 py-2 text-lead hover:border-ink hover:bg-sand hover:text-ink">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="grid max-w-[880px] gap-[22px]">
        <form id="teksty" method="post" action="{{ route('admin.content.texts') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Teksty na stronach</h2>
            <p class="{{ $intro }}">Pisz tak, jak mówisz do klientki. Puste pole nie pokaże się na stronie.</p>
            @if ($textErrors->any())
                <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-6">
                @foreach (SavePageTextsRequest::PAGES as $page)
                    <fieldset class="grid gap-3.5 border-t border-sand-dark pt-4 first:border-t-0 first:pt-0">
                        <legend class="float-left mb-1 flex w-full flex-wrap items-baseline justify-between gap-x-4 font-serif text-[20px]">
                            {{ $page['title'] }}
                            @if ($page['route'] && Route::has($page['route']))
                                <a href="{{ route($page['route']) }}" target="_blank" rel="noopener" class="font-sans text-[13px]">Zobacz stronę →</a>
                            @endif
                        </legend>
                        @foreach ($page['fields'] as $key => [$label, $rows, $max])
                            <div class="min-w-0">
                                <label for="teksty-{{ $key }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                                <textarea id="teksty-{{ $key }}" name="{{ $key }}" rows="{{ $rows }}" maxlength="{{ $max }}"
                                          @if ($textErrors->has($key)) aria-invalid="true" aria-describedby="teksty-{{ $key }}-error" @endif
                                          @class([$input, 'resize-y leading-[1.6]', 'border-error' => $textErrors->has($key), 'border-line' => ! $textErrors->has($key)])>{{ old($key, $texts[$key]) }}</textarea>
                                @if ($textErrors->has($key))
                                    <p id="teksty-{{ $key }}-error" class="mt-1.5 text-[13px] text-error">{{ $textErrors->first($key) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <p class="{{ $hint }}">Kafelki o glinie, jedwabiu i złocie na „O mnie” zmieniasz w <a href="{{ route('admin.settings.edit') }}#materialy">Ustawieniach</a>.</p>
            <button class="{{ $button }}">Zapisz teksty na stronach</button>
        </form>

        @foreach ($lists as $list)
            @php
                $bag = $errors->getBag($list['id']);
                [$filled, $rows] = $listRows($list['name'], $list['rows'], $list['fields']);
            @endphp
            <form id="{{ $list['id'] }}" method="post" action="{{ route($list['route']) }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                {{-- Enter in a field saves the card instead of pressing the first arrow. --}}
                <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

                @switch($list['name'])
                    @case('faq')
                        <h2 class="{{ $heading }}">Częste pytania</h2>
                        <p class="{{ $intro }}">Na stronie <a href="{{ route('content.faq') }}" target="_blank" rel="noopener">„Wysyłka i pielęgnacja”</a>, w tej kolejności. Pytanie bez odpowiedzi nie pokaże się na stronie.</p>
                        @break
                    @case('facts')
                        <h2 class="{{ $heading }}">Pracownia — dobrze wiedzieć</h2>
                        <p class="{{ $intro }}">Karta obok opisu na stronie <a href="{{ route('content.studio') }}" target="_blank" rel="noopener">„Pracownia”</a>: krótki fakt i jedno zdanie pod nim.</p>
                        @break
                    @case('steps')
                        <h2 class="{{ $heading }}">Zamówienia indywidualne — kroki</h2>
                        <p class="{{ $intro }}">Numerowane kroki na stronie <a href="{{ route('custom-orders.index') }}" target="_blank" rel="noopener">„Zamówienia indywidualne”</a>, od pierwszej wiadomości do paczki.</p>
                        @break
                    @case('b2b')
                        <h2 class="{{ $heading }}">Dla kawiarni i restauracji — karty</h2>
                        <p class="{{ $intro }}">Karty pod zdjęciem na stronie <a href="{{ route('content.b2b') }}" target="_blank" rel="noopener">„Dla kawiarni i restauracji”</a>. Z ich nagłówków składa się też opis strony w Google.</p>
                        @break
                    @default
                        <h2 class="{{ $heading }}">Sprawy w formularzu kontaktowym</h2>
                        <p class="{{ $intro }}">Lista „W jakiej sprawie?” na stronie <a href="{{ route('content.contact') }}" target="_blank" rel="noopener">„Kontakt”</a>. Bez żadnej sprawy pole zniknie z formularza.</p>
                @endswitch

                @if ($bag->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif

                <div class="grid gap-2.5">
                    @foreach ($rows as $index => $row)
                        @php
                            $new = $index >= $filled;
                            $name = $list['name'].'['.$index.']';
                            $rowLabel = $new ? 'nowy wiersz' : ($row[$list['fields'][0]] ?? '');
                            $has = fn (string $field) => $bag->has($list['name'].'.'.$index.'.'.$field);
                        @endphp
                        <div @class(['rounded-[4px] border p-3', 'border-sand-dark bg-linen' => ! $new, 'border-dashed border-line-strong' => $new])>
                            @switch($list['name'])
                                @case('faq')
                                    <div class="grid gap-2">
                                        <input name="{{ $name }}[question]" value="{{ $row['question'] ?? '' }}" maxlength="160"
                                               aria-label="Pytanie {{ $index + 1 }}" placeholder="{{ $new ? 'Nowe pytanie' : 'Pytanie' }}"
                                               @class([$input, 'border-error' => $has('question'), 'border-line' => ! $has('question')])>
                                        <textarea name="{{ $name }}[answer]" rows="3" maxlength="1500"
                                                  aria-label="Odpowiedź na pytanie {{ $index + 1 }}" placeholder="Odpowiedź — dwa, trzy zdania"
                                                  @class([$input, 'resize-y leading-[1.6]', 'border-error' => $has('answer'), 'border-line' => ! $has('answer')])>{{ $row['answer'] ?? '' }}</textarea>
                                    </div>
                                    @break
                                @case('steps')
                                    <div class="grid gap-2">
                                        <input name="{{ $name }}[title]" value="{{ $row['title'] ?? '' }}" maxlength="60"
                                               aria-label="Krok {{ $index + 1 }} — nazwa" placeholder="{{ $new ? 'Nowy krok' : 'Nazwa kroku' }}"
                                               @class([$input, 'border-error' => $has('title'), 'border-line' => ! $has('title')])>
                                        <textarea name="{{ $name }}[text]" rows="2" maxlength="240"
                                                  aria-label="Krok {{ $index + 1 }} — opis" placeholder="Jedno, dwa zdania"
                                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $has('text'), 'border-line' => ! $has('text')])>{{ $row['text'] ?? '' }}</textarea>
                                    </div>
                                    @break
                                @case('b2b')
                                    <div class="grid gap-2">
                                        <input name="{{ $name }}[title]" value="{{ $row['title'] ?? '' }}" maxlength="40"
                                               aria-label="Karta {{ $index + 1 }} — nagłówek" placeholder="{{ $new ? 'Nowa karta, np. Próbka przed serią' : 'Nagłówek' }}"
                                               @class([$input, 'border-error' => $has('title'), 'border-line' => ! $has('title')])>
                                        <textarea name="{{ $name }}[text]" rows="2" maxlength="200"
                                                  aria-label="Karta {{ $index + 1 }} — zdanie" placeholder="Jedno, dwa zdania"
                                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $has('text'), 'border-line' => ! $has('text')])>{{ $row['text'] ?? '' }}</textarea>
                                    </div>
                                    @break
                                @case('facts')
                                    <div class="grid gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                                        <input name="{{ $name }}[title]" value="{{ $row['title'] ?? '' }}" maxlength="40"
                                               aria-label="Fakt {{ $index + 1 }}" placeholder="{{ $new ? 'Nowy fakt, np. Do 6 osób' : 'Fakt' }}"
                                               @class([$input, 'border-error' => $has('title'), 'border-line' => ! $has('title')])>
                                        <input name="{{ $name }}[text]" value="{{ $row['text'] ?? '' }}" maxlength="160"
                                               aria-label="Zdanie pod faktem {{ $index + 1 }}" placeholder="Jedno zdanie"
                                               @class([$input, 'border-error' => $has('text'), 'border-line' => ! $has('text')])>
                                    </div>
                                    @break
                                @default
                                    <input name="{{ $name }}[label]" value="{{ $row['label'] ?? '' }}" maxlength="60"
                                           aria-label="Sprawa {{ $index + 1 }}" placeholder="{{ $new ? 'Nowa sprawa' : 'Sprawa' }}"
                                           @class([$input, 'border-error' => $has('label'), 'border-line' => ! $has('label')])>
                            @endswitch

                            @foreach ($list['fields'] as $field)
                                @if ($has($field))
                                    <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($list['name'].'.'.$index.'.'.$field) }}</p>
                                @endif
                            @endforeach

                            @unless ($new)
                                <x-shared::admin.row-actions :name="$name" :index="$index" :last="$filled - 1" :label="$rowLabel" :removed="(bool) ($row['remove'] ?? false)" />
                            @endunless
                        </div>
                    @endforeach
                </div>
                @if ($bag->has($list['name']))
                    <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($list['name']) }}</p>
                @endif
                <p class="{{ $hint }}">W puste wiersze na dole wpisz nowe. Strzałki zapisują całą kartę i przestawiają wiersz o jedno miejsce.</p>
                <button class="{{ $button }}">{{ ['faq' => 'Zapisz częste pytania', 'facts' => 'Zapisz fakty o pracowni', 'steps' => 'Zapisz kroki zamówienia', 'b2b' => 'Zapisz karty dla lokali', 'topics' => 'Zapisz sprawy w formularzu'][$list['name']] }}</button>
            </form>
        @endforeach
    </div>
</x-admin::layout>
