@inject('settings', 'App\Modules\Settings\Settings')
@use('App\Modules\Localization\Support\Locales')
@php
    // A link shows up once its page exists in the language of this page; an empty setting is not shown.
    // On an English page the studio services held in Polish drop out on their own.
    $link = fn (string $key, string $route, mixed $parameters = []) => Locales::has($route, Locales::current())
        ? [__('shared::footer.'.$key), route($route, $parameters)]
        : null;
    $polish = Locales::current() === Locales::default();

    $shopLinks = array_filter([
        $link('all', 'shop.index'),
        $link('mug', 'mug.index'),
        $link('gift_finder', 'gifts.index'),
        $link('bundles', 'bundles.index'),
        // The voucher category is Polish-only, like the workshops it pays for.
        $link('vouchers', 'vouchers.index') ?? ($polish ? $link('vouchers', 'shop.category', 'vouchery') : null),
        $link('shipping', 'content.faq'),
    ]);

    $studioLinks = array_filter([
        $link('about', 'content.about'),
        $link('studio_page', 'content.studio'),
        $link('workshops', 'workshops.index'),
        $link('custom_orders', 'custom-orders.index'),
        $link('scarf', 'content.scarf'),
        $link('imprint', 'content.imprint'),
        $link('b2b', 'content.b2b'),
        $link('firing', 'firing.index'),
    ]);

    $tagline = $settings->get('text_footer_tagline');
    $instagram = $settings->get('instagram_handle');
    $phone = $settings->get('contact_phone');
    $email = $settings->get('contact_email');
    $city = $settings->get('footer_city');
    $nip = $settings->get('company_nip');
    $whatsApp = $phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $phone) : null;
    // The number rings on a phone; WhatsApp has its own pill above.
    $call = $phone ? 'tel:'.preg_replace('/[^\d+]+/', '', $phone) : null;
    $newTab = __('shared::links.new_tab');

    $social = array_filter([
        'Instagram' => $instagram ? 'https://www.instagram.com/'.ltrim($instagram, '@').'/' : null,
        'Facebook' => $settings->get('facebook_url'),
        'WhatsApp' => $whatsApp,
    ]);
@endphp
<footer class="focus-on-dark mt-auto overflow-hidden rounded-t-[36px] bg-ink text-on-dark print:hidden">
    <div class="mx-auto flex max-w-[1280px] flex-wrap gap-12 px-7 pt-16 pb-[30px]">
        <div class="min-w-0 flex-[1_1_280px]">
            <div class="mb-3.5 font-serif text-[25px] tracking-[0.16em] text-sand uppercase">mellowaura</div>
            @if ($tagline)
                <p class="mb-5 max-w-[34ch] text-[14.5px] leading-[1.7] text-on-dark-muted">{{ $tagline }}</p>
            @endif
            @if ($social)
                <div class="flex flex-wrap gap-3">
                    @foreach ($social as $label => $href)
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-full border border-line-dark px-[17px] text-[12.5px] text-sand hover:border-rose hover:text-rose">{{ $label }} <span aria-hidden="true">↗</span><span class="sr-only"> ({{ $newTab }})</span></a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($shopLinks)
            <div class="basis-full sm:flex-[0_1_200px]">
                <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">{{ __('shared::footer.shop') }}</div>
                <div class="flex flex-col gap-[11px] text-[14.5px]">
                    @foreach ($shopLinks as [$label, $href])
                        <a href="{{ $href }}" class="text-on-dark hover:text-rose">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($studioLinks)
            <div class="basis-full sm:flex-[0_1_200px]">
                <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">{{ __('shared::footer.studio') }}</div>
                <div class="flex flex-col gap-[11px] text-[14.5px]">
                    @foreach ($studioLinks as [$label, $href])
                        <a href="{{ $href }}" class="text-on-dark hover:text-rose">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="basis-full sm:flex-[0_1_200px]">
            <div class="mb-4 text-[10.5px] tracking-[0.24em] text-label-dark uppercase">{{ __('shared::footer.contact') }}</div>
            <div class="flex flex-col gap-[11px] text-[14.5px] text-on-dark-muted">
                @if ($call)
                    <a href="{{ $call }}" class="text-on-dark hover:text-rose">{{ $phone }}</a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="text-on-dark [overflow-wrap:anywhere] hover:text-rose">{{ $email }}</a>
                @endif
                @if ($write = $link('write', 'content.contact'))
                    <a href="{{ $write[1] }}" class="text-on-dark hover:text-rose">{{ $write[0] }}</a>
                @endif
                @if ($city)
                    <span>{{ $city }}</span>
                @endif
                {{-- A fact, not a link: „Napisz do mnie” above already leads to the contact page. --}}
                <span>{{ __('shared::footer.by_appointment') }}</span>
            </div>
        </div>
    </div>

    {{-- The word mark bleeding off the bottom; its letters rise with the scroll (resources/js/aura.js). --}}
    <div data-aura-giant aria-hidden="true" class="overflow-hidden pb-[.06em] text-center font-serif text-[clamp(80px,19.5vw,330px)] leading-[.8] font-light tracking-[-0.045em] whitespace-nowrap text-sand">
        @foreach (mb_str_split('Mellow') as $letter)<span class="inline-block">{{ $letter }}</span>@endforeach<em class="text-rose">@foreach (mb_str_split('Aura') as $letter)<span class="inline-block">{{ $letter }}</span>@endforeach</em>
    </div>

    <div class="mx-auto flex max-w-[1280px] flex-wrap items-center justify-between gap-x-7 gap-y-2 border-t border-divider-dark px-7 pt-3 pb-8 text-[12.5px] text-label-dark">
        <span>© {{ now()->year }} MellowAura &middot; Katarzyna Samborska{{ $nip ? ' · NIP '.$nip : '' }}</span>
        <div class="flex flex-wrap items-center gap-x-[22px]">
            @foreach (array_filter([$link('terms', 'content.terms'), $link('privacy', 'content.privacy'), $link('withdrawal', 'withdrawal.create')]) as [$label, $href])
                <a href="{{ $href }}" class="inline-flex min-h-11 items-center text-label-dark hover:text-rose">{{ $label }}</a>
            @endforeach
            @if ($cookies = $link('cookies', 'consent.edit'))
                {{-- With the banner on the page, the link opens it in place. --}}
                <a href="{{ $cookies[1] }}" data-consent-open class="inline-flex min-h-11 items-center text-label-dark hover:text-rose">{{ $cookies[0] }}</a>
            @endif
            @if ($payments = __('shared::footer.payments'))
                <span>{{ $payments }}</span>
            @endif
        </div>
    </div>
</footer>
