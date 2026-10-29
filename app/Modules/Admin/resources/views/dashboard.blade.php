{{-- „Na dziś” (MA-250): four tiles, each leading to its filtered list, and what waits under them, the oldest first. --}}
<x-admin::layout title="Na dziś" :lead="ucfirst($today).'. '.(auth()->user()->isOwner() ? 'Co wysłać, na co odpisać i co nie wyszło. Reszta panelu jest w menu.' : 'Zamówienia do spakowania i wysłania, z napisami do wbicia i certyfikatami do druku.')">
    @can('manage-shop')
        @includeIf('monitoring::admin.dashboard-notice')
    @endcan

    <div class="mb-9 flex flex-wrap gap-2.5 md:gap-4">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['route'] }}" class="block min-w-0 flex-[1_1_140px] rounded-[4px] border border-line bg-cream px-4 py-4 text-ink md:flex-[1_1_180px] md:px-[22px] md:py-5 transition-colors duration-300 hover:border-ink hover:text-ink">
                <span @class(['block font-serif text-[28px] leading-none font-light tabular-nums md:text-[34px]', 'text-label' => $tile['value'] === 0])>{{ $tile['value'] }}</span>
                <span class="mt-1.5 block text-[11.5px] tracking-[0.14em] text-label uppercase">{{ $tile['label'] }}</span>
                <span @class(['mt-2 block text-[12.5px]', 'text-error' => $tile['late'], 'text-hint' => ! $tile['late']])>{{ $tile['hint'] }}</span>
                <span class="mt-3.5 block text-[12.5px]">{{ $tile['go'] }} →</span>
            </a>
        @endforeach
    </div>

    <div class="mb-3.5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 class="font-serif text-[26px] leading-tight font-light">Wymaga Twojej reakcji</h2>
        @if ($waiting->isNotEmpty())
            <span class="text-[12.5px] text-hint">najstarsze na górze</span>
        @endif
    </div>

    @if ($waiting->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Na dziś wszystko zrobione</div>
            <p class="mx-auto max-w-[42ch] text-[13.5px] text-label">Nic nie czeka na wysłanie ani na odpowiedź. Nowe zamówienie pojawi się tutaj od razu po zapłacie.</p>
        </div>
    @else
        <ul class="grid gap-2.5">
            @foreach ($waiting as $row)
                <li @class(['flex flex-wrap items-center gap-3.5 rounded-[4px] border border-line bg-cream py-4 pr-[18px]', 'border-l-[3px] border-l-error pl-4' => $row['late'], 'pl-[18px]' => ! $row['late']])>
                    <div class="flex flex-none items-baseline gap-2 text-[12.5px] tabular-nums max-sm:basis-full sm:w-[92px] sm:flex-col sm:gap-0">
                        <span @class(['font-serif text-[19px] font-light', 'text-error' => $row['late'], 'text-ink' => ! $row['late']])>{{ $row['age'] }}</span>
                        <span @class(['text-error' => $row['late'], 'text-hint' => ! $row['late']])>{{ $row['since']->format('j.m') }}</span>
                    </div>
                    <div class="min-w-0 flex-[1_1_220px]">
                        <div class="text-[11px] tracking-[0.14em] text-label uppercase">{{ $row['kind'] }}</div>
                        <div class="mt-0.5 text-[14.5px] text-ink">{{ $row['title'] }}</div>
                        @if ($row['detail'] !== '')
                            <div class="mt-0.5 text-[12.5px] text-label">{{ $row['detail'] }}</div>
                        @endif
                        @if ($row['late'])
                            <div class="mt-1 text-[12px] text-error">{{ $row['late'] }}</div>
                        @endif
                    </div>
                    @if ($row['status'])
                        <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $row['status'][1] }}">{{ $row['status'][0] }}</span>
                    @endif
                    <a href="{{ $row['url'] }}" @class([
                        'inline-flex min-h-11 flex-none items-center rounded-full border px-4 text-[12.5px]',
                        'border-ink bg-ink text-linen hover:border-rose hover:bg-rose hover:text-ink' => $row['main'],
                        'border-line-strong text-ink hover:border-ink hover:bg-sand-dark hover:text-ink' => ! $row['main'],
                    ])>{{ $row['action'] }}@if ($row['main']) →@endif</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-admin::layout>
