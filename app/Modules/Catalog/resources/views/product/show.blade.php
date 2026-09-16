@use('App\Modules\Catalog\Enums\CategoryGroup')
@use('App\Modules\Shared\Support\DispatchTime')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Shared\Support\Seo')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $suffix = match ($product->category->group) {
        CategoryGroup::Ceramics => ' — ceramika handmade',
        CategoryGroup::Crafts => ' — rękodzieło z jedwabiu',
        default => '',
    };
    $canonical = route('product.show', $product);
    $images = $product->getMedia('images');
    $lowestBeforeDiscount = $variant->lowestPriceBeforeDiscount();
    $dimensions = $product->dimensionLabels();
    $care = $product->care_note ?: ($product->category->group === CategoryGroup::Ceramics ? $settings->get('care_rule_ceramics') : null);
    $freeShipping = $settings->get('free_shipping_threshold');
    // A voucher is not a thing in a parcel: no dimensions note, certificate or producer details.
    $isGoods = ! $product->isVoucher();
    $tolerance = $product->size_tolerance ?: $settings->get('size_tolerance');
    // The product safety rules (GPSR) want the maker's name, postal address and e-mail on every product page.
    $producer = collect(['company_name', 'company_address', 'contact_email'])->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->filter();
@endphp

<x-shared::layout :title="Seo::title($product->name, $suffix)" :description="Seo::description($product->seo_description ?: $product->description)" :canonical="$canonical" type="product" :image="$images->first()?->getAvailableUrl(['card'])">
    @isset($structuredData)
        <x-slot:head>{!! $structuredData !!}</x-slot:head>
    @endisset

    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-11 pb-24">
        <nav aria-label="Okruszki" class="mb-7 text-[12px] text-hint">
            <a href="{{ route('shop.index') }}" class="text-hint hover:text-navy">Sklep</a>
            / <a href="{{ route('shop.category', $product->category) }}" class="text-hint hover:text-navy">{{ $product->category->name }}</a>
            / <span class="text-lead">{{ $product->name }}</span>
        </nav>

        <div class="flex flex-wrap gap-14">
            <div class="min-w-0 flex-[1_1_400px]" x-data="{ active: 0 }">
                @if ($images->isEmpty())
                    <div class="aspect-square w-full rounded-[6px] bg-line-soft"></div>
                @else
                    <button type="button" x-ref="zoomTrigger" x-on:click="$refs.zoom.showModal()" title="Kliknij, żeby powiększyć"
                            class="relative block w-full cursor-zoom-in overflow-hidden rounded-[6px] bg-line-soft">
                        @foreach ($images as $index => $image)
                            <img src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}"
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" x-cloak @endif
                                 x-show="active === {{ $index }}"
                                 class="block aspect-square w-full object-cover">
                        @endforeach
                        <span class="absolute right-3.5 bottom-3.5 rounded-full bg-cream/92 px-3.5 py-[7px] text-[12px] text-lead">Powiększ fakturę <span aria-hidden="true">⌕</span></span>
                    </button>

                    {{-- Esc, a click anywhere or the × closes the zoom, and focus goes back to the photo. --}}
                    <dialog x-ref="zoom" x-on:click="$el.close()" x-on:keydown.escape="$el.close()" x-on:close="$refs.zoomTrigger.focus()" aria-label="Powiększone zdjęcie"
                            class="focus-on-dark fixed inset-0 size-full max-h-none max-w-none animate-ma-in cursor-zoom-out items-center justify-center bg-scrim/88 p-7 open:flex backdrop:bg-transparent">
                        @foreach ($images as $index => $image)
                            <img src="{{ $image->getUrl() }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" loading="lazy"
                                 x-show="active === {{ $index }}"
                                 class="size-full object-contain">
                        @endforeach
                        <button type="button" aria-label="Zamknij powiększenie"
                                class="absolute top-5 right-6 px-2.5 py-1.5 text-[30px] leading-none text-divider hover:text-white">×</button>
                    </dialog>
                @endif
                @if ($isGoods && $images->isNotEmpty())
                    @if ($product->is_exact_piece)
                        <p class="mt-3 flex flex-wrap items-center gap-2.5 text-[13.5px] text-graphite">
                            <span class="rounded-full bg-rose px-3 py-[5px] text-[11.5px] tracking-[0.06em] text-ink">ta sztuka</span>
                            <span>Na zdjęciach jest dokładnie rzecz, którą dostaniesz.</span>
                        </p>
                    @else
                        <p class="mt-3 text-[12.5px] text-hint">Zdjęcia pokazują przykładową sztukę — każdą robię ręcznie, więc Twoja będzie trochę inna.</p>
                    @endif
                @endif
                @if ($images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach ($images as $index => $image)
                            <button type="button" x-on:click="active = {{ $index }}" x-bind:aria-pressed="active === {{ $index }}"
                                    x-bind:class="active === {{ $index }} ? 'border-ink' : 'border-divider'"
                                    aria-label="Pokaż zdjęcie {{ $loop->iteration }}"
                                    class="size-[74px] overflow-hidden rounded-[4px] border bg-transparent p-0">
                                <img src="{{ $image->getAvailableUrl(['thumb']) }}" alt="" loading="lazy" class="block size-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-[1_1_360px]">
                <div class="mb-4 text-[10.5px] tracking-[0.28em] text-brown uppercase">
                    {{ $product->category->name }}@if ($product->is_one_off) &middot; unikat @endif
                </div>
                <h1 class="mb-[18px] font-serif text-[length:clamp(32px,4vw,50px)] leading-[1.08] font-light">{{ $product->name }}</h1>

                <div class="mb-[26px]">
                    <div class="flex items-baseline gap-3">
                        <span class="font-serif text-[26px]">{{ Money::format($variant->price_gross) }}</span>
                        @if ($lowestBeforeDiscount !== null)
                            <span class="text-[16px] text-hint line-through">{{ Money::format($variant->compare_at_price) }}</span>
                        @endif
                    </div>
                    @if ($lowestBeforeDiscount !== null)
                        <p class="mt-1.5 text-[12.5px] text-hint">Najniższa cena z 30 dni przed obniżką: {{ Money::format($lowestBeforeDiscount) }}</p>
                    @endif
                </div>

                @if ($product->description)
                    <p class="mb-[26px] text-[16.5px] leading-[1.72] text-pretty text-lead">{{ $product->description }}</p>
                @endif

                @if ($dimensions || $product->food_contact)
                    <div class="mb-[30px]">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($dimensions as $label => $value)
                                <span class="flex items-baseline gap-2 rounded-[4px] bg-sand-dark px-[13px] py-2 text-[13px]"><span class="text-label">{{ $label }}</span><span class="text-ink">{{ $value }}</span></span>
                            @endforeach
                            @if ($product->food_contact)
                                <span class="rounded-[4px] bg-sand-dark px-[13px] py-2 text-[13px] text-ink">{{ $product->food_contact->label() }}</span>
                            @endif
                        </div>
                        @if ($dimensions && $tolerance && $isGoods)
                            <p class="mt-2.5 text-[12.5px] text-hint">Ręczna robota — wymiary mogą różnić się do {{ $tolerance }}.</p>
                        @endif
                    </div>
                @endif

                @if ($product->variants->count() > 1)
                    <div class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">Rozmiar i cena</div>
                    <div class="mb-[30px] flex flex-wrap gap-2.5">
                        @foreach ($product->variants as $option)
                            <a href="{{ $canonical }}?wariant={{ $option->id }}" @if ($option->is($variant)) aria-current="true" @endif @class([
                                'block rounded-[6px] border px-[18px] py-3 text-left',
                                'border-ink bg-ink text-linen hover:text-linen' => $option->is($variant),
                                'border-line bg-cream text-lead hover:border-ink hover:text-lead' => ! $option->is($variant),
                            ])>
                                <span class="block text-[13.5px]">{{ $option->label }}</span>
                                <span @class(['mt-[3px] block text-[12.5px]', 'text-line-strong' => $option->is($variant), 'text-label' => ! $option->is($variant)])>{{ Money::format($option->price_gross) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($product->is_one_off && $product->stamp_enabled)
                    <div class="mb-[22px] flex items-center gap-2.5 rounded-[4px] bg-sand-dark px-4 py-[13px] text-[13.5px] text-graphite">
                        <span class="size-[7px] flex-none rounded-full bg-error"></span>
                        <span>Każdy kubek jest jeden — po 1 sztuce z każdego napisu. Kolejny zrobię na zamówienie.</span>
                    </div>
                @endif

                @if ($product->deviation)
                    <div class="mb-5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] leading-[1.55] text-alert-text">
                        <span class="font-medium">Zwróć uwagę:</span> {{ rtrim($product->deviation, '. ') }}. Przy zamówieniu poproszę, żebyś to potwierdziła osobnym polem.
                    </div>
                @endif

                @if ($variant->stock !== null && $variant->stock <= 1)
                    <div class="mb-5 flex items-center gap-2.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
                        <span class="size-[7px] flex-none rounded-full bg-error"></span>
                        <span>{{ $variant->stock === 1 ? 'ostatnia sztuka' : $variant->stock.' szt. na półce' }} — kolejną zrobię na zamówienie</span>
                    </div>
                @endif

                @if (Route::has('cart.store') && $variant->isInStock())
                    @php($stampMax = (int) $settings->get('stamp_text_max_chars', 22))
                    <form method="post" action="{{ route('cart.store') }}" x-data="{ quantity: 1, text: @js((string) old('custom_text')) }"
                          x-on:submit.prevent="$store.cart.send($el)" class="mb-[26px]">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <input type="hidden" name="quantity" value="1" x-bind:value="quantity">

                        @if ($variant->takesCustomText())
                            <div class="mb-[26px] animate-ma-up-quick rounded-[4px] border border-line bg-cream px-5 py-[22px]">
                                <label for="custom-text" class="mb-3 block text-[11.5px] tracking-[0.16em] text-label uppercase">Co mam wbić w glinę?</label>
                                <input id="custom-text" name="custom_text" x-model="text" maxlength="{{ $stampMax }}" required autocomplete="off"
                                       placeholder="NA PRZYKŁAD: JESZCZE NIE TERAZ" aria-describedby="custom-text-hint"
                                       class="w-full min-w-0 rounded-[4px] border border-line bg-white px-4 py-[15px] text-[17px] tracking-[0.12em] text-ink uppercase placeholder:text-hint focus:border-ink">
                                <div id="custom-text-hint" class="mt-2.5 flex justify-between gap-3 text-[12.5px] text-label">
                                    <span>Stempluję wielkimi literami, litera po literze</span>
                                    <span x-text="text.length + ' / {{ $stampMax }}'">0 / {{ $stampMax }}</span>
                                </div>
                                @error('custom_text')
                                    <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- A voucher asks for the name and dedication to print; the Gifts module brings the fields. --}}
                        @if ($product->isVoucher())
                            @includeIf('gifts::product.voucher-fields', ['product' => $product])
                        @endif

                        <div class="flex flex-wrap items-center gap-3.5">
                            <div class="flex items-center rounded-full border border-line bg-cream">
                                <button type="button" x-on:click="quantity = Math.max(1, quantity - 1)" aria-label="Mniej sztuk" class="h-[46px] w-11 text-[18px] text-muted">−</button>
                                <span x-text="quantity" aria-live="polite" class="min-w-[26px] text-center text-[15px]">1</span>
                                <button type="button" x-on:click="quantity++" @if ($variant->stock !== null) x-bind:disabled="quantity >= {{ $variant->stock }}" @endif
                                        aria-label="Więcej sztuk" class="h-[46px] w-11 text-[18px] text-muted disabled:cursor-not-allowed disabled:text-line-strong">+</button>
                            </div>
                            <button type="submit" class="flex-[1_1_200px] rounded-full bg-ink px-[30px] py-4 text-[14.5px] tracking-[0.03em] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">
                                Dodaj do koszyka &middot; <span x-text="$store.cart.format({{ $variant->price_gross }} * quantity)">{{ Money::format($variant->price_gross) }}</span>
                            </button>
                        </div>
                    </form>
                @endif

                <div class="mb-[34px] flex items-center gap-2.5 text-[12.5px] text-muted">
                    <span class="rounded-[3px] bg-navy px-[7px] py-[3px] text-[10px] font-semibold text-white">BLIK</span>
                    <span>zapłacisz w 10 sekund kodem z aplikacji banku</span>
                </div>

                <div class="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-5 border-t border-divider pt-6 text-[13.5px] leading-[1.55] text-muted">
                    {{-- A voucher doesn't wait days for a parcel: its PDF comes by e-mail once it is paid. --}}
                    @if (! $product->isVoucher() && ($shippingNote = collect([DispatchTime::label($settings), $freeShipping ? 'gratis od '.Money::format((int) $freeShipping) : null])->filter()->join(', ')))
                        <div>
                            <div class="mb-1 text-ink">Wysyłka</div>
                            {{ Str::ucfirst($shippingNote) }}
                        </div>
                    @endif
                    @if ($isGoods)
                        <div>
                            <div class="mb-1 text-ink">Certyfikat unikatu</div>
                            {{ $product->category->group === CategoryGroup::Ceramics ? 'W paczce karta z numerem, datą wypału i podpisem' : 'W paczce karta z numerem i podpisem' }}
                        </div>
                    @endif
                    @if ($care)
                        <div>
                            <div class="mb-1 text-ink">Pielęgnacja</div>
                            {{ $care }}
                        </div>
                    @endif
                    @if ($isGoods)
                        <div>
                            <div class="mb-1 text-ink">Chcesz inaczej?</div>
                            Napis, rozmiar, szkliwo — wszystko robię na zamówienie.
                            @if (Route::has('custom-orders.index'))
                                <a href="{{ route('custom-orders.index') }}">Napisz, co wymyśliłaś</a>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($isGoods && ($product->safety_warnings || $producer->isNotEmpty()))
                    <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-5 border-t border-divider pt-6 text-[13px] leading-[1.6] text-muted">
                        @if ($product->safety_warnings)
                            <div>
                                <div class="mb-1 text-ink">Ostrzeżenia</div>
                                <p class="whitespace-pre-line">{{ $product->safety_warnings }}</p>
                            </div>
                        @endif
                        @if ($producer->isNotEmpty())
                            <div>
                                <div class="mb-1 text-ink">Producent</div>
                                @foreach ($producer as $key => $value)
                                    <div class="[overflow-wrap:anywhere]">
                                        @if ($key === 'contact_email')
                                            <a href="mailto:{{ $value }}">{{ $value }}</a>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="mt-22 border-t border-divider pt-11">
                <h2 class="mb-7 font-serif text-[32px] font-light">Z tej samej półki</h2>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-6">
                    @foreach ($related as $item)
                        @php($cover = $item->getFirstMedia('images'))
                        <a href="{{ route('product.show', $item) }}" class="group block text-ink hover:text-ink">
                            <div class="mb-3 overflow-hidden rounded-[6px] bg-line-soft">
                                @if ($cover)
                                    <img src="{{ $cover->getAvailableUrl(['card']) }}" alt="{{ $cover->getCustomProperty('alt') ?: $item->name }}" loading="lazy" class="block aspect-[4/5] w-full object-cover transition-transform duration-600 group-hover:scale-[1.04]">
                                @else
                                    <div class="aspect-[4/5] w-full"></div>
                                @endif
                            </div>
                            <div class="font-serif text-[18px] leading-[1.25]">{{ $item->name }}</div>
                            <div class="mt-[3px] text-[13.5px] text-muted"><x-catalog::price-label :product="$item" /></div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-shared::layout>
