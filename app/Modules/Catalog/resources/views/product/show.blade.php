@use('App\Modules\Catalog\Enums\CategoryGroup')
@use('App\Modules\Catalog\Support\ProductPhoto')
@use('App\Modules\Catalog\Support\VariantAnalyticsItem')
@use('App\Modules\Shared\Support\AnalyticsItem')
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
    <x-consent::analytics-event name="view_item" :params="AnalyticsItem::params($variant->price_gross, [VariantAnalyticsItem::make($variant)])" />

    <div class="mx-auto max-w-[1360px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-9 pb-24">
        <nav aria-label="Okruszki" class="mb-7 text-[12.5px] text-label">
            <a href="{{ route('shop.index') }}" class="text-label hover:text-navy">Sklep</a>
            &middot; <a href="{{ route('shop.category', $product->category) }}" class="text-label hover:text-navy">{{ $product->category->name }}</a>
            &middot; <span class="text-lead">{{ $product->name }}</span>
        </nav>

        <div class="flex flex-wrap items-start gap-[clamp(28px,5vw,72px)]">
            <div class="min-w-0 flex-[1_1_460px]" x-data="{ active: 0 }">
                @if ($images->isEmpty())
                    <div class="aspect-square w-full rounded-[26px] bg-linen"></div>
                @else
                    {{-- Photos change by blurring into each other; the mouse moves a slight zoom around (resources/js/aura.js). --}}
                    <button type="button" x-ref="zoomTrigger" x-on:click="$refs.zoom.showModal()" title="Kliknij, żeby powiększyć" data-clay data-zoom-follow
                            class="relative block aspect-square w-full cursor-zoom-in overflow-hidden rounded-[26px] bg-linen">
                        @foreach ($images as $index => $image)
                            @php($srcset = ProductPhoto::srcset($image))
                            {{-- Half of a 1224 px page on a computer, the whole width minus padding on a phone. --}}
                            <img src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}"
                                 @if ($srcset) srcset="{{ $srcset }}" sizes="(min-width: 872px) 584px, calc(100vw - 56px)" @endif
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" x-cloak @endif
                                 x-show="active === {{ $index }}" width="1200" height="1200"
                                 x-transition:enter="transition duration-700 ease-clay" x-transition:enter-start="opacity-0 blur-md scale-105"
                                 x-transition:leave="transition duration-300 ease-in" x-transition:leave-end="opacity-0 blur-md"
                                 class="absolute inset-0 block size-full object-cover">
                        @endforeach
                        <span class="glass absolute top-3.5 right-3.5 rounded-full px-3.5 py-2 text-[12px] text-graphite">Powiększ fakturę <span aria-hidden="true">⌕</span></span>
                    </button>

                    {{-- Esc, a click anywhere or the × closes the zoom, and focus goes back to the photo. --}}
                    <dialog x-ref="zoom" x-on:click="$el.close()" x-on:keydown.escape="$el.close()" x-on:close="$refs.zoomTrigger.focus()" aria-label="Powiększone zdjęcie"
                            class="focus-on-dark fixed inset-0 size-full max-h-none max-w-none animate-ma-in cursor-zoom-out items-center justify-center bg-ink/55 p-7 backdrop-blur-[20px] open:flex backdrop:bg-transparent">
                        @foreach ($images as $index => $image)
                            <img src="{{ $image->getUrl() }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" loading="lazy"
                                 x-show="active === {{ $index }}"
                                 class="size-full rounded-[18px] object-contain">
                        @endforeach
                        <button type="button" aria-label="Zamknij powiększenie"
                                class="absolute top-5 right-5 grid size-11 place-items-center rounded-full bg-linen text-[24px] leading-none text-ink hover:bg-rose">×</button>
                    </dialog>
                @endif
                @if ($isGoods && $images->isNotEmpty())
                    @if ($product->is_exact_piece)
                        <p class="mt-3.5 flex flex-wrap items-center gap-2.5 text-[13.5px] text-graphite">
                            <span class="rounded-full bg-rose px-3 py-[5px] text-[11.5px] tracking-[0.06em] text-ink">ta sztuka</span>
                            <span>Na zdjęciach jest dokładnie rzecz, którą dostaniesz.</span>
                        </p>
                    @else
                        <p class="mt-3.5 text-[12.5px] text-label">Zdjęcia pokazują przykładową sztukę — każdą robię ręcznie, więc Twoja będzie trochę inna.</p>
                    @endif
                @endif
                @if ($images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        @foreach ($images as $index => $image)
                            <button type="button" x-on:click="active = {{ $index }}" x-bind:aria-pressed="active === {{ $index }}"
                                    x-bind:class="active === {{ $index }} ? 'border-ink opacity-100' : 'border-line opacity-65'"
                                    aria-label="Pokaż zdjęcie {{ $loop->iteration }}"
                                    class="size-[74px] overflow-hidden rounded-[12px] border bg-transparent p-0 transition-[opacity,border-color,transform] duration-400 ease-clay hover:-translate-y-0.5 hover:opacity-100">
                                <img src="{{ $image->getAvailableUrl(['thumb']) }}" alt="" loading="lazy" width="74" height="74" class="block size-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-[1_1_400px] min-[872px]:sticky min-[872px]:top-24">
                <p class="eyebrow mb-4">
                    {{ $product->category->name }}@if ($product->is_one_off) &middot; unikat @endif
                </p>
                <h1 class="mb-5 font-serif text-[length:clamp(44px,5.6vw,88px)] leading-[.94] font-light tracking-[-0.025em] text-balance"><span class="blur-in">{{ $product->name }}</span></h1>

                <div class="mb-[26px]">
                    <div class="flex items-baseline gap-3.5">
                        <span class="font-serif text-[42px] leading-none font-light tabular-nums">{{ Money::format($variant->price_gross) }}</span>
                        @if ($lowestBeforeDiscount !== null)
                            <span class="text-[16px] text-label line-through">{{ Money::format($variant->compare_at_price) }}</span>
                        @endif
                    </div>
                    @if ($lowestBeforeDiscount !== null)
                        <p class="mt-2 text-[12.5px] text-label">Najniższa cena z 30 dni przed obniżką: {{ Money::format($lowestBeforeDiscount) }}</p>
                    @endif
                </div>

                @if ($product->description)
                    <p class="mb-[26px] text-[16.5px] leading-[1.72] text-pretty text-lead">{{ $product->description }}</p>
                @endif

                @if ($dimensions || $product->food_contact)
                    <div class="mb-[30px]">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($dimensions as $label => $value)
                                <span class="glass flex items-baseline gap-2 rounded-full px-3.5 py-2 text-[13px]"><span class="text-label">{{ $label }}</span><span class="text-ink">{{ $value }}</span></span>
                            @endforeach
                            @if ($product->food_contact)
                                <span class="glass rounded-full px-3.5 py-2 text-[13px] text-ink">{{ $product->food_contact->label() }}</span>
                            @endif
                        </div>
                        @if ($dimensions && $tolerance && $isGoods)
                            <p class="mt-2.5 text-[12.5px] text-label">Ręczna robota — wymiary mogą różnić się do {{ $tolerance }}.</p>
                        @endif
                    </div>
                @endif

                @if ($product->variants->count() > 1)
                    <div class="mb-3 text-[13.5px] font-medium text-graphite">Rozmiar i cena</div>
                    <div class="mb-[30px] flex flex-wrap gap-2.5">
                        @foreach ($product->variants as $option)
                            <a href="{{ $canonical }}?wariant={{ $option->id }}" @if ($option->is($variant)) aria-current="true" @endif @class([
                                'block rounded-[20px] border px-[18px] py-3 text-left transition-[border-color,background-color] duration-300',
                                'border-ink bg-ink text-linen hover:text-linen' => $option->is($variant),
                                'glass text-lead hover:border-ink hover:text-lead' => ! $option->is($variant),
                            ])>
                                <span class="block text-[13.5px]">{{ $option->label }}</span>
                                <span @class(['mt-[3px] block text-[12.5px]', 'text-line-strong' => $option->is($variant), 'text-label' => ! $option->is($variant)])>{{ Money::format($option->price_gross) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($product->is_one_off && $product->stamp_enabled)
                    <div class="glass mb-[22px] flex items-center gap-2.5 rounded-[16px] px-4 py-[13px] text-[13.5px] text-graphite">
                        <span class="size-[7px] flex-none rounded-full bg-error"></span>
                        <span>Każdy kubek jest jeden — po 1 sztuce z każdego napisu. Kolejny zrobię na zamówienie.</span>
                    </div>
                @endif

                @if ($product->deviation)
                    <div class="mb-5 rounded-[16px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] leading-[1.55] text-alert-text">
                        <span class="font-medium">Zwróć uwagę:</span> {{ rtrim($product->deviation, '. ') }}. Przy zamówieniu poproszę, żebyś to potwierdziła osobnym polem.
                    </div>
                @endif

                @if ($variant->stock !== null && $variant->stock <= 1)
                    <div class="mb-5 flex items-center gap-2.5 rounded-[16px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
                        <span class="size-[7px] flex-none rounded-full bg-error"></span>
                        <span>{{ $variant->stock === 1 ? 'ostatnia sztuka' : $variant->stock.' szt. na półce' }} — kolejną zrobię na zamówienie</span>
                    </div>
                @endif

                @if (Route::has('cart.store') && $variant->isInStock())
                    @php($stampMax = (int) $settings->get('stamp_text_max_chars', 22))
                    <form id="add-to-cart" method="post" action="{{ route('cart.store') }}" x-data="{ quantity: 1, text: @js((string) old('custom_text')) }"
                          x-on:submit.prevent="$store.cart.send($el)" data-fly-to-cart class="mb-[26px]">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <input type="hidden" name="quantity" value="1" x-bind:value="quantity">

                        @if ($variant->takesCustomText())
                            <div class="glass mb-[26px] animate-ma-up-quick rounded-[20px] px-5 py-[22px]">
                                <label for="custom-text" class="mb-3 block text-[11.5px] tracking-[0.16em] text-label uppercase">Co mam wbić w glinę?</label>
                                <input id="custom-text" name="custom_text" x-model="text" maxlength="{{ $stampMax }}" required autocomplete="off"
                                       placeholder="NA PRZYKŁAD: JESZCZE NIE TERAZ" aria-describedby="custom-text-hint"
                                       class="w-full min-w-0 rounded-[14px] border border-line bg-white px-4 py-[15px] text-[17px] tracking-[0.12em] text-ink uppercase placeholder:text-label focus:border-ink focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none">
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
                            <div class="glass flex items-center rounded-full">
                                <button type="button" x-on:click="quantity = Math.max(1, quantity - 1)" aria-label="Mniej sztuk" class="h-[46px] w-11 text-[18px] text-muted">−</button>
                                <span x-text="quantity" aria-live="polite" class="min-w-[26px] text-center text-[15px]">1</span>
                                <button type="button" x-on:click="quantity++" @if ($variant->stock !== null) x-bind:disabled="quantity >= {{ $variant->stock }}" @endif
                                        aria-label="Więcej sztuk" class="h-[46px] w-11 text-[18px] text-muted disabled:cursor-not-allowed disabled:text-line-strong">+</button>
                            </div>
                            <button type="submit" data-magnet class="fill-btn min-h-[54px] flex-[1_1_200px] rounded-full bg-ink px-5 py-4 text-[14.5px] font-medium tracking-[0.03em] whitespace-nowrap text-linen transition duration-300 [--fill:var(--color-navy)] hover:bg-navy active:scale-[.97]">
                                Dodaj do koszyka &middot; <span x-text="$store.cart.format({{ $variant->price_gross }} * quantity)">{{ Money::format($variant->price_gross) }}</span>
                            </button>
                            <x-catalog::favorite-button :product="$product"
                                                        class="glass size-[54px] flex-none rounded-full text-[20px] hover:border-ink" />
                        </div>
                    </form>
                @endif

                <div class="mb-[34px] flex items-center gap-2.5 text-[12.5px] text-muted">
                    <span class="rounded-full bg-navy px-2.5 py-[3px] text-[10px] font-semibold text-white">BLIK</span>
                    <span>zapłacisz w 10 sekund kodem z aplikacji banku</span>
                </div>

                <div class="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-2.5 text-[13.5px] leading-[1.55] text-muted [&>div]:glass [&>div]:rounded-[18px] [&>div]:px-[18px] [&>div]:py-4">
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
                    <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-5 border-t border-line pt-6 text-[13px] leading-[1.6] text-muted">
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
            <div class="mt-[clamp(90px,14vh,150px)]">
                <p class="eyebrow mb-[18px]">pasuje do tego</p>
                <h2 class="mb-9 font-serif text-[length:clamp(36px,4.4vw,64px)] leading-[.98] font-light tracking-[-0.02em]">Z tej samej półki</h2>
                <div data-aura-rise class="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-[clamp(16px,2vw,28px)]">
                    @foreach ($related as $item)
                        @php($cover = $item->getFirstMedia('images'))
                        <a href="{{ route('product.show', $item) }}" class="group block text-ink hover:text-ink">
                            <div data-clay class="mb-3.5 overflow-hidden rounded-[18px] bg-linen transition-[box-shadow,transform] duration-500 ease-clay group-hover:-translate-y-1 group-hover:shadow-card-hover">
                                @if ($cover)
                                    @php($srcset = ProductPhoto::srcset($cover))
                                    <img src="{{ $cover->getAvailableUrl(['card']) }}" alt="{{ $cover->getCustomProperty('alt') ?: $item->name }}" loading="lazy"
                                         @if ($srcset) srcset="{{ $srcset }}" sizes="(min-width: 640px) 300px, calc(100vw - 56px)" @endif
                                         width="1200" height="1500" class="block aspect-[4/5] w-full object-cover transition-transform duration-[1.1s] ease-clay group-hover:scale-[1.06]">
                                @else
                                    <div class="aspect-[4/5] w-full"></div>
                                @endif
                            </div>
                            <div class="font-serif text-[22px] leading-[1.15] font-light">{{ $item->name }}</div>
                            <div class="mt-[3px] text-[13.5px] text-muted"><x-catalog::price-label :product="$item" /></div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- On a phone the price and the button stay at the bottom of the screen (mellowaura-aplikacja). Where the photo
             and the form stand side by side (from 872 px) they are in view anyway, so the bar is gone. --}}
        @if (Route::has('cart.store') && $variant->isInStock())
            <div x-data="buyBar" x-cloak x-bind:class="shown || 'invisible translate-y-full'"
                 class="glass sticky bottom-[calc(12px+env(safe-area-inset-bottom))] z-50 mt-10 flex items-center gap-3 rounded-full py-2 pr-2 pl-5 transition duration-500 ease-clay min-[872px]:hidden">
                <div class="min-w-0 flex-auto">
                    <div class="font-serif text-[22px] leading-none">{{ Money::format($variant->price_gross) }}</div>
                    @if ($variant->label)
                        <div class="mt-1 truncate text-[11.5px] text-label">{{ $variant->label }}</div>
                    @endif
                </div>
                <button type="submit" form="add-to-cart"
                        class="fill-btn flex-none rounded-full bg-ink px-6 py-[14px] text-[14px] text-linen transition duration-300 [--fill:var(--color-navy)] hover:bg-navy active:scale-[.97]">Do koszyka</button>
            </div>
        @endif
    </div>
</x-shared::layout>
