@use('App\Modules\Localization\Support\Locales')
@php
    // Offered, never forced: the browser's first language decides only whether the offer shows up.
    $preferred = Locales::preferred(request());
    $show = $preferred !== null && $preferred !== Locales::current() && Locales::existsIn(request(), $preferred);
@endphp
@if ($show)
    <div x-data="{ open: true }" x-init="try { if (localStorage.getItem('locale-hint-closed')) open = false } catch (e) {}" x-show="open" x-cloak
         lang="{{ $preferred }}" class="relative z-61 px-3 pt-3.5 print:hidden">
        <div class="glass mx-auto flex max-w-[1120px] flex-wrap items-center gap-x-4 gap-y-2 rounded-[22px] py-2 pr-2 pl-[22px] text-[13.5px] text-graphite">
            <p class="m-0">{{ __('localization::switcher.hint', [], $preferred) }}</p>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ Locales::switchUrl(request(), $preferred) }}" hreflang="{{ $preferred }}"
                   class="fill-btn flex min-h-11 items-center rounded-full bg-ink px-4 text-linen [--fill:var(--color-navy)] hover:text-linen">{{ __('localization::switcher.hint_action', [], $preferred) }}</a>
                <button type="button" x-on:click="open = false; try { localStorage.setItem('locale-hint-closed', '1') } catch (e) {}"
                        class="flex min-h-11 items-center rounded-full border border-line-strong px-4 text-ink">{{ __('localization::switcher.hint_close', [], $preferred) }}</button>
            </div>
        </div>
    </div>
@endif
