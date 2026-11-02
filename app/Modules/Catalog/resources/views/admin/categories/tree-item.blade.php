{{--
    One shelf in the panel's tree (MA-351), with the shelves under it in their own list. Every shelf has that list, even an
    empty one, so another can be dropped into it; the script lets in only what still fits in three levels.
--}}
@php
    $google = filled($category->seo_title) && filled($category->seo_description);
    $empty = $filled($category) === 0;
@endphp
<li data-id="{{ $category->id }}" class="category-item">
    <div class="grid grid-cols-[28px_minmax(0,1fr)_auto] items-center gap-2.5 border-t border-divider bg-cream py-2 pr-2 pl-1 transition-colors hover:bg-linen md:pr-3.5">
        <button type="button" data-drag x-on:keydown.arrow-up.prevent="step($el, -1)" x-on:keydown.arrow-down.prevent="step($el, 1)"
                aria-label="Przesuń: {{ $category->name }} — złap i przeciągnij albo użyj strzałek w górę i w dół"
                class="relative z-[1] grid h-11 w-7 cursor-grab touch-none place-items-center rounded-full text-label transition-colors hover:bg-sand hover:text-ink active:cursor-grabbing">
            <svg viewBox="0 0 10 16" aria-hidden="true" class="h-4 w-2.5 fill-current"><circle cx="2" cy="2" r="1.5"/><circle cx="8" cy="2" r="1.5"/><circle cx="2" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="2" cy="14" r="1.5"/><circle cx="8" cy="14" r="1.5"/></svg>
        </button>
        <button type="button" x-on:click="open = @js('kategoria-'.$category->id)" aria-controls="kategoria-{{ $category->id }}" class="min-h-11 min-w-0 py-1 text-left">
            <span data-name class="block text-[15px] leading-[1.3] text-ink">{{ $category->name }}</span>
            <span class="flex flex-wrap gap-x-2.5 text-[12.5px] text-label">
                <span>produktów: {{ $category->products_count }}</span>
                @if ($category->also_shown_products_count > 0)
                    <span>pokazanych też tu: {{ $category->also_shown_products_count }}</span>
                @endif
                @if ($empty)
                    <span class="text-brown">pusta — nie widać jej w sklepie</span>
                @endif
                <span @class(['text-success' => $google])>{{ $google ? 'Google ✓' : 'Google: złoży sama' }}</span>
            </span>
        </button>
        @if ($depth < \App\Modules\Catalog\Models\Category::MAX_DEPTH)
            <button type="button" x-on:click="addUnder({{ $category->id }})" aria-label="Dodaj półkę w: {{ $category->name }}"
                    class="min-h-11 rounded-full px-3 text-[13px] whitespace-nowrap text-lead hover:bg-sand hover:text-ink">+ Półka</button>
        @else
            <span></span>
        @endif
    </div>
    <ol data-children-of="{{ $category->id }}" class="category-list pl-7">
        @foreach ($tree->children($category) as $child)
            @include('catalog::admin.categories.tree-item', ['category' => $child, 'depth' => $depth + 1])
        @endforeach
    </ol>
</li>
