{{-- Shows once something is saved with a heart and opens the shop filtered to it. --}}
<a href="{{ route('shop.index') }}#ulubione" x-data x-cloak x-show="$store.favorites.count > 0"
   x-bind:aria-label="'Ulubione: ' + $store.favorites.count"
   class="flex min-h-11 items-center gap-2 rounded-full border border-line-strong px-3.5 py-2.5 text-[13px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark hover:text-ink active:scale-[.97]">
    <span aria-hidden="true" class="text-[16px] leading-none">♥</span>
    <span aria-hidden="true" x-text="$store.favorites.count"></span>
</a>
