@inject('consent', 'App\Modules\Consent\Support\Consent')
@if ($consent->isNeeded())
    @php
        $decided = $consent->decided(request());
        $config = [
            'open' => ! $decided,
            'decided' => $decided,
            'analytics' => $consent->allowsAnalytics(request()),
            'measurementId' => $consent->measurementId(),
        ];
        $t = fn (string $key) => __('consent::consent.'.$key);
        $button = 'min-h-11 flex-1 rounded-full bg-linen px-5 py-2.5 sm:px-6 sm:py-3 text-[14px] whitespace-nowrap text-ink transition duration-300 hover:bg-rose active:scale-[.97] sm:flex-none';
    @endphp
    {{-- Shown by the server until a choice is made, so it works without JavaScript; the footer link opens it again. --}}
    <section x-data="consentBanner(@js($config))" x-show="open" @unless ($config['open']) style="display: none" @endunless
             aria-labelledby="consent-title"
             class="focus-on-dark fixed inset-x-0 bottom-0 z-70 border-t border-line-dark bg-ink text-on-dark print:hidden">
        {{-- On a phone the banner stays low: shorter words and smaller gaps, so the page keeps most of the screen. --}}
        <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-10 gap-y-3 pr-[max(clamp(16px,4vw,28px),env(safe-area-inset-right))] pl-[max(clamp(16px,4vw,28px),env(safe-area-inset-left))] pt-4 pb-[calc(16px+env(safe-area-inset-bottom))] sm:gap-y-4 sm:pt-5 sm:pb-[calc(20px+env(safe-area-inset-bottom))]">
            <div class="min-w-0 flex-[1_1_420px]">
                <h2 id="consent-title" class="mb-1.5 text-[11px] tracking-[0.2em] text-label-dark uppercase">{{ $t('banner_heading') }}</h2>
                <p class="max-w-[76ch] text-[13.5px] leading-[1.55] text-pretty sm:text-[14px] sm:leading-[1.6]">
                    {{ $t('banner_text') }}
                    {{-- The way to take consent back is said before it is given (RODO art. 7 ust. 3), on a phone in fewer words. --}}
                    <span class="max-sm:hidden">{{ $t('banner_change') }}</span>
                    <span class="sm:hidden">{{ $t('banner_change_short') }}</span>
                    <a href="{{ route('content.privacy') }}" class="text-sand underline decoration-line-dark underline-offset-4 hover:text-rose">{{ $t('privacy') }}</a>
                </p>
                <p x-show="decided" x-cloak class="mt-1.5 text-[13px] text-on-dark-muted" x-text="analytics ? @js($t('now_on')) : @js($t('now_off'))"></p>
            </div>
            <form method="post" action="{{ route('consent.store') }}" x-on:submit="choose($event)" class="flex flex-[1_1_300px] flex-wrap items-center justify-end gap-2.5">
                @csrf
                <button name="analytics" value="1" class="{{ $button }}">{{ $t('accept') }}</button>
                <button name="analytics" value="0" class="{{ $button }}">{{ $t('reject') }}</button>
                <button type="button" x-show="decided" x-cloak x-on:click="open = false" aria-label="{{ $t('close') }}"
                        class="relative grid size-11 place-items-center text-[22px] leading-none text-on-dark-muted hover:text-sand">×</button>
            </form>
        </div>
    </section>
@endif
