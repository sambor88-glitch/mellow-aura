@php
    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream px-[26px] py-7';
    $heading = 'mb-1 font-serif text-[25px]';
    $intro = 'mb-5 text-[13.5px] leading-[1.6] text-label';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3.5 py-3 text-[14.5px] text-ink placeholder:text-hint focus:border-ink';
    $button = 'mt-[18px] min-h-11 w-full rounded-full bg-ink p-4 text-[14px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]';
    $textErrors = $errors->getBag('teksty');
    $finderErrors = $errors->getBag('szukam-prezentu');
    $budgetRows = array_values((array) old('budgets', $budgets));
    // One empty row for a new range; left empty, it is not saved.
    $budgetRows = [...$budgetRows, ['min' => '', 'max' => '']];
    $savedBudgets = count((array) old('budgets', $budgets));
    $voucherErrors = $errors->getBag('vouchery');

    $textFields = [
        ['text_bundles_heading', 'Zestawy — nagłówek', 2, 120],
        ['text_bundles_lead', 'Zestawy — wstęp', 3, 400],
        ['text_gift_wrap_heading', 'Pakowanie — nagłówek', 1, 80],
        ['text_gift_wrap_lead', 'Pakowanie — opis', 3, 400],
    ];
    $voucherNumbers = [
        ['voucher_validity_months', 'Ważny przez', 'mies.'],
        ['voucher_recipient_name_max_chars', 'Imię — najwyżej', 'znaków'],
        ['voucher_dedication_max_chars', 'Dedykacja — najwyżej', 'znaków'],
    ];
@endphp
<x-admin::layout title="Prezenty i zestawy" lead="Zestawy z rabatem, „Szukam prezentu” i vouchery. Zmiany widać na stronie od razu.">
    <div class="flex flex-wrap items-start gap-[26px]">
        <div class="grid min-w-0 flex-[1_1_420px] gap-[22px]">
            <section class="{{ $card }}">
                <h2 class="{{ $heading }}">Zestawy prezentowe</h2>
                <p class="{{ $intro }}">Cena zestawu liczy się sama z cen wybranych rzeczy minus Twój rabat. Gdy któraś rzecz się wyprzeda albo ją ukryjesz, zestaw sam zniknie ze strony i wróci razem z nią.</p>
                @if ($bundles->isEmpty())
                    <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
                        <div class="mb-2 font-serif text-[22px]">Jeszcze nie ma zestawów</div>
                        <p class="mx-auto max-w-[42ch] text-[13.5px] text-label">Pierwszy złożysz w karcie „Nowy zestaw” — z dwóch do czterech rzeczy ze sklepu.</p>
                    </div>
                @else
                    <div class="grid gap-4">
                        @foreach ($bundles as $bundle)
                            <div id="zestaw-{{ $bundle->id }}" class="scroll-mt-6 rounded-[4px] border border-sand-dark bg-linen p-4">
                                @include('gifts::admin.bundle-form', ['bundle' => $bundle, 'formKey' => 'zestaw-'.$bundle->id])
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="nowy-zestaw" class="{{ $card }}">
                <h2 class="{{ $heading }}">Nowy zestaw</h2>
                <p class="{{ $intro }}">Dwie do czterech rzeczy ze sklepu, sprzedawane razem taniej. Nowy zestaw stanie na końcu strony.</p>
                @include('gifts::admin.bundle-form', ['bundle' => null, 'formKey' => 'nowy-zestaw'])
            </section>
        </div>

        <div class="grid min-w-0 flex-[1_1_320px] gap-[22px]">
            <form id="teksty" method="post" action="{{ route('admin.gifts.texts') }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Teksty na stronie zestawów</h2>
                <p class="{{ $intro }}">Enter w nagłówku przenosi wyraz do nowej linii — dokładnie tak, jak zobaczy to klientka.</p>
                @if ($textErrors->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-3.5">
                    @foreach ($textFields as [$key, $label, $rows, $max])
                        <div class="min-w-0">
                            <label for="teksty-{{ $key }}" class="mb-1.5 block text-[11.5px] tracking-[0.1em] text-label uppercase">{{ $label }}</label>
                            <textarea id="teksty-{{ $key }}" name="{{ $key }}" rows="{{ $rows }}" maxlength="{{ $max }}"
                                      @class([$input, 'resize-y leading-[1.55]', 'border-error' => $textErrors->has($key), 'border-line' => ! $textErrors->has($key)])>{{ old($key, $texts[$key]) }}</textarea>
                            @if ($textErrors->has($key))
                                <p class="mt-1.5 text-[13px] text-error">{{ $textErrors->first($key) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="mt-3.5 text-[12.5px] leading-[1.6] text-hint">Puste zdanie nie pokaże się na stronie. Cenę pakowania zmieniasz w Ustawieniach, razem z dostawą.</p>
                <button class="{{ $button }}">Zapisz teksty na stronie</button>
            </form>

            <form id="szukam-prezentu" method="post" action="{{ route('admin.gifts.finder') }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Szukam prezentu</h2>
                <p class="{{ $intro }}">Przedziały budżetu to przyciski na stronie. Produkt trafia do przedziału według swojej najniższej ceny. Okazje i „dla kogo” zaznaczasz przy każdym produkcie.</p>
                @if ($finderErrors->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <fieldset>
                    <legend class="mb-2 text-[11.5px] tracking-[0.1em] text-label uppercase">Budżet</legend>
                    <div class="grid gap-2">
                        @foreach ($budgetRows as $index => $row)
                            <div class="flex flex-wrap items-center gap-2 text-[13.5px] text-label">
                                <span>od</span>
                                <input name="budgets[{{ $index }}][min]" value="{{ $row['min'] ?? '' }}" inputmode="numeric" aria-label="Przedział {{ $index + 1 }} — od, zł"
                                       @class(['min-h-11 w-[76px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink', 'border-error' => $finderErrors->has('budgets.'.$index.'.min'), 'border-line' => ! $finderErrors->has('budgets.'.$index.'.min')])>
                                <span>do</span>
                                <input name="budgets[{{ $index }}][max]" value="{{ $row['max'] ?? '' }}" inputmode="numeric" aria-label="Przedział {{ $index + 1 }} — do, zł" placeholder="—"
                                       @class(['min-h-11 w-[76px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink', 'border-error' => $finderErrors->has('budgets.'.$index.'.max'), 'border-line' => ! $finderErrors->has('budgets.'.$index.'.max')])>
                                <span>zł</span>
                                @if ($index < $savedBudgets)
                                    <label class="ml-auto flex min-h-11 items-center gap-1.5 text-[12.5px]">
                                        <input type="checkbox" name="budgets[{{ $index }}][remove]" value="1" class="size-4 accent-error"> usuń
                                    </label>
                                @endif
                            </div>
                            @foreach (['min', 'max'] as $field)
                                @if ($finderErrors->has('budgets.'.$index.'.'.$field))
                                    <p class="text-[13px] text-error">{{ $finderErrors->first('budgets.'.$index.'.'.$field) }}</p>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                    <p class="mt-2.5 text-[12.5px] leading-[1.5] text-hint">Puste „do” to przedział bez górnej granicy — na stronie „powyżej 400 zł”. W pusty wiersz wpisz nowy przedział.</p>
                </fieldset>
                <div class="mt-4 grid gap-3.5">
                    <div class="min-w-0">
                        <label for="szukam-lead" class="mb-1.5 block text-[11.5px] tracking-[0.1em] text-label uppercase">Zdanie pod nagłówkiem</label>
                        <textarea id="szukam-lead" name="text_gifts_lead" rows="3" maxlength="400"
                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $finderErrors->has('text_gifts_lead'), 'border-line' => ! $finderErrors->has('text_gifts_lead')])>{{ old('text_gifts_lead', $finderTexts['text_gifts_lead']) }}</textarea>
                    </div>
                    <div class="min-w-0">
                        <label for="szukam-voucher" class="mb-1.5 block text-[11.5px] tracking-[0.1em] text-label uppercase">Pod listą — o voucherach</label>
                        <textarea id="szukam-voucher" name="text_gifts_voucher_note" rows="2" maxlength="200"
                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $finderErrors->has('text_gifts_voucher_note'), 'border-line' => ! $finderErrors->has('text_gifts_voucher_note')])>{{ old('text_gifts_voucher_note', $finderTexts['text_gifts_voucher_note']) }}</textarea>
                        <p class="mt-1.5 text-[12.5px] text-hint">Ważność vouchera dopisze się sama, z karty „Vouchery”.</p>
                    </div>
                </div>
                <button class="{{ $button }}">Zapisz „Szukam prezentu”</button>
            </form>

            <form id="vouchery" method="post" action="{{ route('admin.gifts.vouchers') }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Vouchery</h2>
                <p class="{{ $intro }}">Imię i dedykacja wpisane przy zakupie trafiają na voucher w PDF. Zmiany dotyczą vouchera kupionego od teraz.</p>
                @if ($voucherErrors->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-3">
                    @foreach ($voucherNumbers as [$key, $label, $unit])
                        <div>
                            <div class="flex items-center justify-between gap-3.5">
                                <label for="vouchery-{{ $key }}" class="min-w-0 flex-1 text-[14px] text-graphite">{{ $label }}</label>
                                <span class="flex items-center gap-1.5">
                                    <input id="vouchery-{{ $key }}" name="{{ $key }}" value="{{ old($key, $voucherSettings[$key]) }}" inputmode="numeric"
                                           @if ($voucherErrors->has($key)) aria-invalid="true" aria-describedby="vouchery-{{ $key }}-error" @endif
                                           @class([
                                               'min-h-11 w-[72px] min-w-0 rounded-[4px] border bg-white px-2.5 text-right text-[14px] text-ink focus:border-ink',
                                               'border-error' => $voucherErrors->has($key),
                                               'border-line' => ! $voucherErrors->has($key),
                                           ])>
                                    <span class="w-[46px] text-[13px] text-label">{{ $unit }}</span>
                                </span>
                            </div>
                            @if ($voucherErrors->has($key))
                                <p id="vouchery-{{ $key }}-error" class="mt-1.5 text-[13px] text-error">{{ $voucherErrors->first($key) }}</p>
                            @endif
                        </div>
                    @endforeach
                    <div class="min-w-0">
                        <label for="vouchery-jak" class="mb-1.5 block text-[13.5px] text-graphite">Jak go wykorzystać — zdanie na dole vouchera</label>
                        <textarea id="vouchery-jak" name="text_voucher_how_to_use" rows="3" maxlength="400"
                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $voucherErrors->has('text_voucher_how_to_use'), 'border-line' => ! $voucherErrors->has('text_voucher_how_to_use')])>{{ old('text_voucher_how_to_use', $voucherSettings['text_voucher_how_to_use']) }}</textarea>
                        @if ($voucherErrors->has('text_voucher_how_to_use'))
                            <p class="mt-1.5 text-[13px] text-error">{{ $voucherErrors->first('text_voucher_how_to_use') }}</p>
                        @endif
                    </div>
                </div>
                <p class="mt-3.5 text-[12.5px] leading-[1.6] text-hint">
                    Obok zdania stoi numer WhatsApp z <a href="{{ route('admin.settings.edit') }}#pracownia">Ustawień → Dane pracowni</a> — ten sam co w stopce i na stronie kontaktu.
                    @unless ($hasWhatsApp)
                        Teraz jest pusty, więc na voucherze go nie ma.
                    @endunless
                    Pod spodem e-mail, adres strony i Instagram.
                </p>
                <button class="{{ $button }}">Zapisz vouchery</button>
            </form>

            @if ($voucherProducts->isNotEmpty())
                @php($noteErrors = $errors->getBag('opis-warsztatu'))
                <form id="opis-warsztatu" method="post" action="{{ route('admin.gifts.voucher-notes') }}" novalidate class="{{ $card }}">
                    @csrf
                    @method('PUT')
                    <h2 class="{{ $heading }}">Opis warsztatu na voucherze</h2>
                    <p class="{{ $intro }}">Cztery krótkie punkty pod dedykacją, osobno dla każdego vouchera. Puste pole nie pokaże się na voucherze — przy voucherze kwotowym zostaw wszystkie puste.</p>
                    @if ($noteErrors->any())
                        <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                    @endif
                    <div class="grid gap-5">
                        @foreach ($voucherProducts as $product)
                            <fieldset class="grid gap-3 border-t border-sand-dark pt-4 first:border-t-0 first:pt-0">
                                <legend class="float-left mb-1 w-full font-serif text-[19px]">{{ $product->name }}</legend>
                                @foreach (['expect' => 'Czego się spodziewać', 'activities' => 'Co będziecie robili', 'takeaway' => 'Z czym wyjdziecie', 'preparation' => 'Jak się przygotować'] as $field => $label)
                                    @php($key = 'notes.'.$product->slug.'.'.$field)
                                    <div class="min-w-0">
                                        <label for="opis-{{ $product->id }}-{{ $field }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                                        <textarea id="opis-{{ $product->id }}-{{ $field }}" name="notes[{{ $product->slug }}][{{ $field }}]" rows="2" maxlength="{{ \App\Modules\Gifts\Http\Requests\Admin\SaveVoucherNotesRequest::MAX }}"
                                                  @if ($noteErrors->has($key)) aria-invalid="true" aria-describedby="opis-{{ $product->id }}-{{ $field }}-error" @endif
                                                  @class([$input, 'resize-y leading-[1.55]', 'border-error' => $noteErrors->has($key), 'border-line' => ! $noteErrors->has($key)])>{{ old('notes.'.$product->slug.'.'.$field, $voucherNotes[$product->slug][$field] ?? '') }}</textarea>
                                        @if ($noteErrors->has($key))
                                            <p id="opis-{{ $product->id }}-{{ $field }}-error" class="mt-1.5 text-[13px] text-error">{{ $noteErrors->first($key) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                    <button class="{{ $button }}">Zapisz opisy na voucherach</button>
                </form>
            @endif
        </div>
    </div>
</x-admin::layout>
