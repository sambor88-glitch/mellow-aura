{{--
    Tabs on a long panel screen (MA-315): one part of the screen at a time instead of every form under the other.
    tabs: key => label, or key => ['label' => …, 'count' => …, 'note' => …, 'dot' => 'error'|'todo'].
    active: the tab the screen wants open (a mistake in its form); otherwise ?tab= or the first one (Admin\Support\Tabs).
    Each part is an <x-admin::tab-panel name="…">. Forms keep their own routes: the tabs only show and hide.
    head: what stands above the tabs and may follow the open tab too (the product card's ‹ › links).
--}}
@props(['tabs', 'label', 'active' => null])
@use('App\Modules\Admin\Support\Tabs')
@php
    $open = Tabs::active($tabs, $active);
    $keys = array_map('strval', array_keys($tabs));
    $items = collect($tabs)->map(fn ($tab) => is_array($tab) ? $tab : ['label' => $tab]);
@endphp
<div x-data="panelTabs(@js($open), @js($keys))" {{ $attributes }}>
    {{ $head ?? '' }}
    {{-- On a phone the tabs scroll sideways: the open one is brought into view and a fading edge says there is more (MA-335). --}}
    <div role="tablist" aria-label="{{ $label }}" x-ref="tablist" x-on:keydown="move($event)" x-on:scroll.passive="edges()" data-tabs-scroll
         @class(['sticky top-[env(safe-area-inset-top,0px)] z-30 -mx-4 mb-[18px] flex gap-1 overflow-x-auto scroll-px-4 border-b border-line bg-sand-dark px-4 py-2.5 [scrollbar-width:none] md:mx-0 md:px-0', 'mt-3.5' => isset($head)])>
        @foreach ($items as $key => $tab)
            <button type="button" role="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}"
                    aria-selected="{{ $open === (string) $key ? 'true' : 'false' }}" x-bind:aria-selected="(tab === @js((string) $key)).toString()"
                    tabindex="{{ $open === (string) $key ? 0 : -1 }}" x-bind:tabindex="tab === @js((string) $key) ? 0 : -1" x-on:click="show(@js((string) $key))"
                    class="group inline-flex min-h-11 flex-none items-center gap-2 rounded-full border border-transparent px-4 text-[13.5px] text-lead transition-colors hover:border-line-strong hover:bg-sand hover:text-ink aria-selected:border-ink aria-selected:bg-ink aria-selected:text-linen">
                {{ $tab['label'] }}
                @if (isset($tab['count']) && $tab['count'] !== '')<span class="text-[11.5px] text-label tabular-nums group-aria-selected:text-sand">{{ $tab['count'] }}</span>@endif
                @if (filled($tab['note'] ?? null))<span class="text-[11px] text-label group-aria-selected:text-sand">{{ $tab['note'] }}</span>@endif
                @if (($tab['dot'] ?? null) !== null)
                    <span aria-hidden="true" @class(['size-[7px] rounded-full', 'bg-error' => $tab['dot'] === 'error', 'bg-brown' => $tab['dot'] !== 'error'])></span>
                    <span class="sr-only">{{ $tab['dot'] === 'error' ? ' — są błędy' : ' — do uzupełnienia' }}</span>
                @else
                    {{-- A mistake the browser finds in this tab after saving. --}}
                    <span x-show="bad.includes(@js((string) $key))" style="display: none" class="inline-flex items-center">
                        <span aria-hidden="true" class="size-[7px] rounded-full bg-error"></span>
                        <span class="sr-only"> — są błędy</span>
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
