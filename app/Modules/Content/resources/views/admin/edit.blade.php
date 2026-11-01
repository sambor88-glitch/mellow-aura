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
    $button = 'mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $fieldLabel = 'mb-1 block text-[12.5px] text-graphite';
    $small = 'grid min-h-11 min-w-11 place-items-center rounded-full border border-line-strong px-3 text-[13px] text-ink hover:border-ink hover:bg-sand-dark';
    $manualErrors = $errors->getBag('instagram-reczne');
    $tabs = [
        'teksty' => 'Teksty na stronach',
        'czeste-pytania' => ['label' => 'Częste pytania', 'count' => count($questions)],
        'pracownia' => 'Pracownia',
        'zamowienia-indywidualne' => 'Zamówienia indywidualne',
        'gastronomia' => 'Dla lokali',
        'kontakt' => 'Sprawy w formularzu',
        'instagram' => 'Instagram',
    ];
    // The pages under „Teksty na stronach” fold away; the one with a mistake stays open.
    $pageOpen = fn (array $page) => collect(array_keys($page['fields']))->contains(fn ($key) => $textErrors->has($key));
@endphp
<x-admin::layout title="Treści" lead="Każde zdanie na stronach poza sklepem i częste pytania. Zmiany widać na stronie od razu.">
    {{-- One card at a time (MA-317). Every card keeps its own form and its #id, so saving comes back to its tab. --}}
  <x-admin::tabs :tabs="$tabs" label="Części treści">
    <div class="grid max-w-[880px] gap-[22px]">
      <x-admin::tab-panel name="teksty">
        <form id="teksty" method="post" action="{{ route('admin.content.texts') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Teksty na stronach</h2>
            <p class="{{ $intro }}">Pisz tak, jak mówisz do klienta. Puste pole nie pokaże się na stronie.</p>
            @if ($textErrors->any())
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid">
                @foreach (SavePageTextsRequest::PAGES as $page)
                    <details class="group border-t border-sand-dark first:border-t-0" @if ($pageOpen($page)) open @endif>
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-x-4 py-2 font-serif text-[20px] [&::-webkit-details-marker]:hidden">
                            {{ $page['title'] }}
                            <span class="flex flex-none items-center gap-2 font-sans text-[12.5px] text-label">{{ trans_choice(':count pole|:count pola|:count pól', count($page['fields']), [], 'pl') }}
                                <span aria-hidden="true" class="text-[15px] transition-transform group-open:rotate-180">⌄</span></span>
                        </summary>
                      <div class="grid gap-3.5 pt-1 pb-5">
                        @if ($page['route'] && Route::has($page['route']))
                            <a href="{{ route($page['route']) }}" target="_blank" rel="noopener" class="text-[13px]">Zobacz stronę →</a>
                        @endif
                        @foreach ($page['fields'] as $key => $field)
                            @php
                                [$label, $rows, $max] = $field;
                                $fieldHint = $field[3] ?? null;
                                $describedBy = implode(' ', array_filter([$fieldHint ? 'teksty-'.$key.'-hint' : null, $textErrors->has($key) ? 'teksty-'.$key.'-error' : null]));
                            @endphp
                            <div class="min-w-0">
                                <label for="teksty-{{ $key }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                                <textarea id="teksty-{{ $key }}" name="{{ $key }}" rows="{{ $rows }}" maxlength="{{ $max }}"
                                          @if ($textErrors->has($key)) aria-invalid="true" @endif
                                          @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                                          @class([$input, 'resize-y leading-[1.6]', 'border-error' => $textErrors->has($key), 'border-line' => ! $textErrors->has($key)])>{{ old($key, $texts[$key]) }}</textarea>
                                @if ($fieldHint)
                                    <p id="teksty-{{ $key }}-hint" class="mt-1.5 text-[12.5px] leading-[1.5] text-label">{{ $fieldHint }}</p>
                                @endif
                                @if ($textErrors->has($key))
                                    <p id="teksty-{{ $key }}-error" class="mt-1.5 text-[13px] text-error">{{ $textErrors->first($key) }}</p>
                                @endif
                            </div>
                        @endforeach
                      </div>
                    </details>
                @endforeach
            </div>
            <p class="{{ $hint }}">Kafelki o glinie, jedwabiu i złocie na „O mnie” zmieniasz w <a href="{{ route('admin.settings.edit') }}#materialy">Ustawieniach</a>.</p>
            <button class="{{ $button }}">Zapisz teksty na stronach</button>
        </form>
      </x-admin::tab-panel>

        @foreach ($lists as $list)
            @php
                $bag = $errors->getBag($list['id']);
                [$filled, $rows] = $listRows($list['name'], $list['rows'], $list['fields']);
            @endphp
          <x-admin::tab-panel :name="$list['id']">
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
                    <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
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
                                  <details class="group" @if ($new || $has('question') || $has('answer')) open @endif>
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-[14px] text-ink group-open:mb-2 [&::-webkit-details-marker]:hidden">
                                        <span class="min-w-0 truncate">{{ $new ? 'Nowe pytanie' : ($row['question'] ?? '') }}</span>
                                        <span aria-hidden="true" class="flex-none text-[15px] text-label transition-transform group-open:rotate-180">⌄</span>
                                    </summary>
                                    <div class="grid gap-2">
                                        <input name="{{ $name }}[question]" value="{{ $row['question'] ?? '' }}" maxlength="160"
                                               aria-label="Pytanie {{ $index + 1 }}" placeholder="{{ $new ? 'Nowe pytanie' : 'Pytanie' }}"
                                               @class([$input, 'border-error' => $has('question'), 'border-line' => ! $has('question')])>
                                        <textarea name="{{ $name }}[answer]" rows="3" maxlength="1500"
                                                  aria-label="Odpowiedź na pytanie {{ $index + 1 }}" placeholder="Odpowiedź — dwa, trzy zdania"
                                                  @class([$input, 'resize-y leading-[1.6]', 'border-error' => $has('answer'), 'border-line' => ! $has('answer')])>{{ $row['answer'] ?? '' }}</textarea>
                                    </div>
                                  </details>
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
          </x-admin::tab-panel>
        @endforeach

      <x-admin::tab-panel name="instagram" class="grid gap-[22px]">
        <form id="instagram" method="post" action="{{ route('admin.content.instagram') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <h2 class="{{ $heading }}">Instagram na stronie głównej</h2>
            <p class="{{ $intro }}">Sekcja pokazuje {{ \App\Modules\Content\Actions\RefreshInstagramFeed::POSTS }} zdjęć: najpierw te, które dodasz ręcznie niżej, potem najnowsze z kanału Behold.so. Strona trzyma zdjęcia u siebie, więc odwiedzający nie łączą się z Instagramem.</p>
            @if ($errors->hasBag('instagram'))
                <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <label class="mb-3 flex min-h-11 items-center gap-2.5 text-[14px] text-graphite">
                <input type="hidden" name="home_show_instagram_feed" value="0">
                <input type="checkbox" name="home_show_instagram_feed" value="1" @checked($errors->hasBag('instagram') ? old('home_show_instagram_feed') : $instagram['home_show_instagram_feed']) class="size-4 accent-ink">
                Pokazuj sekcję z Instagrama na stronie głównej
            </label>
            <div class="grid gap-3">
                <x-shared::field name="instagram_feed_url" id="instagram-kanal" bag="instagram" type="url" label="Adres kanału JSON z Behold"
                                 :value="old('instagram_feed_url', $instagram['instagram_feed_url'])" placeholder="https://feeds.behold.so/…"
                                 hint="Behold → Twój kanał → JSON. Puste pole zdejmuje zdjęcia z kanału; posty dodane ręcznie zostają." />
                <x-shared::field name="instagram_feed_hashtag" id="instagram-hashtag" bag="instagram" label="Tylko posty z hashtagiem — nieobowiązkowe"
                                 :value="old('instagram_feed_hashtag', $instagram['instagram_feed_hashtag'])" placeholder="np. #zpracowni" />
            </div>
            @if ($instagram['posts']->isNotEmpty())
                <p class="mt-3.5 text-[12.5px] text-label">{{ $instagram['home_show_instagram_feed'] ? 'Na stronie teraz' : 'Pokażą się po włączeniu sekcji' }}, ostatni post {{ $instagram['posts']->max('posted_at')->diffForHumans() }}. Strona tnie zdjęcia do kwadratu — jeśli coś ważnego się ucina, przesuń kadr suwakami.</p>
                @if ($errors->getBag('instagram')->has('focus.*'))
                    <p class="mt-1.5 text-[13px] text-error">{{ $errors->getBag('instagram')->first('focus.*') }}</p>
                @endif
                <div class="mt-3 grid grid-cols-2 gap-3.5 min-[520px]:grid-cols-3">
                    @foreach ($instagram['posts'] as $post)
                        @php($point = old('focus.'.$post->id, ['x' => $post->focus_x, 'y' => $post->focus_y]))
                        <x-shared::focus-picker name="focus[{{ $post->id }}]" :src="$post->imageUrl()" :x="$point['x']" :y="$point['y']"
                                                label="Kadr zdjęcia {{ $loop->iteration }}: {{ $post->alt() }}" />
                    @endforeach
                </div>
            @endif
            <button class="{{ $button }}">Zapisz i pobierz zdjęcia</button>
        </form>

        {{-- Posts added by hand (MA-274): Kasia's photo and a link to the post, before the feed's posts and in her order. --}}
        <section id="instagram-reczne" aria-labelledby="instagram-reczne-tytul" class="{{ $card }}">
            <h2 id="instagram-reczne-tytul" class="{{ $heading }}">Instagram — posty dodane ręcznie</h2>
            <p class="{{ $intro }}">Bez kanału Behold albo gdy chcesz pokazać konkretne posty. Stoją przed zdjęciami z kanału, w tej kolejności. Najlepiej wyglądają 3 albo 6 — wtedy wypełniają całe rzędy.</p>

            @if ($instagram['manual']->isNotEmpty())
                <ol class="mb-4 grid gap-2.5">
                    @foreach ($instagram['manual'] as $post)
                        <li class="flex flex-wrap items-center gap-3 rounded-[4px] border border-line bg-white p-2.5">
                            <img src="{{ $post->imageUrl() }}" alt="" width="64" height="64" style="object-position: {{ $post->objectPosition() }}" class="size-16 rounded-[3px] object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] text-ink">{{ $post->alt() }}</p>
                                <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="block truncate text-[12.5px] text-label hover:text-navy">{{ $post->permalink }}</a>
                            </div>
                            <div class="flex items-center gap-2">
                                @unless ($loop->first)
                                    <form method="post" action="{{ route('admin.content.instagram-posts.move', $post) }}">
                                        @csrf
                                        <input type="hidden" name="kierunek" value="gora">
                                        <button aria-label="Przesuń wyżej post {{ $loop->iteration }}" class="{{ $small }}">↑</button>
                                    </form>
                                @endunless
                                @unless ($loop->last)
                                    <form method="post" action="{{ route('admin.content.instagram-posts.move', $post) }}">
                                        @csrf
                                        <input type="hidden" name="kierunek" value="dol">
                                        <button aria-label="Przesuń niżej post {{ $loop->iteration }}" class="{{ $small }}">↓</button>
                                    </form>
                                @endunless
                                <form method="post" action="{{ route('admin.content.instagram-posts.destroy', $post) }}"
                                      x-data x-on:submit="confirm('Usunąć ten post ze strony? Na Instagramie zostaje.') || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button class="min-h-11 rounded-full px-3 text-[13px] text-hint hover:text-error">Usuń</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <form method="post" action="{{ route('admin.content.instagram-posts.store') }}" enctype="multipart/form-data" novalidate
                  class="rounded-[4px] border border-dashed border-line-strong p-3.5">
                @csrf
                <h3 class="mb-2.5 font-serif text-[19px]">Nowy post</h3>
                @if ($manualErrors->any())
                    <p role="alert" data-error class="{{ $alert }}">Popraw zaznaczone pola, żeby dodać post.</p>
                @endif
                <div class="grid gap-2.5">
                    <div class="min-w-0">
                        <label for="instagram-zdjecie" class="{{ $fieldLabel }}">Zdjęcie — JPG, PNG albo WebP, do 15 MB</label>
                        <input id="instagram-zdjecie" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                               @if ($manualErrors->has('photo')) aria-invalid="true" aria-describedby="instagram-zdjecie-blad" @endif
                               class="block w-full text-[14px] text-graphite file:mr-3 file:min-h-11 file:rounded-full file:border file:border-line-strong file:bg-linen file:px-4 file:text-[13px] file:text-ink">
                        @if ($manualErrors->has('photo'))
                            <p id="instagram-zdjecie-blad" class="mt-1.5 text-[13px] text-error">{{ $manualErrors->first('photo') }}</p>
                        @endif
                    </div>
                    <x-shared::field name="permalink" id="instagram-link" bag="instagram-reczne" type="url" label="Link do posta na Instagramie"
                                     :value="old('permalink')" placeholder="https://www.instagram.com/p/…"
                                     hint="Na Instagramie: ⋯ przy poście → Kopiuj link." />
                    <x-shared::field name="alt" id="instagram-opis" bag="instagram-reczne" label="Co widać na zdjęciu"
                                     :value="old('alt')" placeholder="np. kubek w szkliwie lazur na drewnianym stole"
                                     hint="Czyta to czytnik ekranu osobom niewidomym." />
                </div>
                <button class="{{ $button }}">Dodaj post</button>
            </form>
        </section>
      </x-admin::tab-panel>
    </div>
  </x-admin::tabs>
</x-admin::layout>
