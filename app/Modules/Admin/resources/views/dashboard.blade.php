@use('App\Modules\Admin\Menu')
<x-admin::layout title="Mój panel" lead="Dodawaj produkty, zmieniaj ceny i terminy. Zmiany widać na stronie od razu.">
    @includeIf('monitoring::admin.dashboard-notice')

    @if (Menu::sections())
        <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            @foreach (Menu::sections() as $section)
                <a href="{{ route($section['route']) }}" class="block rounded-[4px] border border-line bg-cream px-[26px] py-6 text-ink transition duration-300 hover:border-ink hover:text-ink">
                    <span class="mb-1.5 block font-serif text-[23px]">{{ $section['label'] }}</span>
                    <span class="block text-[13.5px] leading-[1.55] text-label">{{ $section['description'] }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Jeszcze nic tu nie ma</div>
            <p class="mx-auto max-w-[42ch] text-[13.5px] text-label">Sekcje panelu pojawią się tutaj, gdy będą gotowe.</p>
        </div>
    @endif
</x-admin::layout>
