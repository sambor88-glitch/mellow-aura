@php
    $field = 'min-w-0 rounded-[4px] border border-line bg-white px-3 py-2.5 text-[14px] text-ink focus:border-ink';
@endphp
<x-admin::layout title="Klientki" lead="Każda osoba, która coś kupiła, zapisała się, wysłała pomysł albo wsad — zebrana po adresie e-mail. Tu przygotujesz jej kopię danych albo usuniesz dane, gdy o to poprosi.">
    <form method="get" action="{{ route('admin.customers.index') }}" role="search" class="mb-6 flex flex-wrap items-end gap-2.5">
        <label for="szukaj" class="grid min-w-0 flex-[1_1_280px] gap-1.5 text-[12.5px] text-label">
            Imię, e-mail albo telefon
            <input id="szukaj" name="szukaj" type="search" value="{{ $query }}" autocomplete="off" class="{{ $field }}">
        </label>
        <button class="min-h-11 rounded-full bg-ink px-6 text-[13.5px] text-linen hover:bg-navy">Szukaj</button>
        @if ($query !== '')
            <a href="{{ route('admin.customers.index') }}" class="min-h-11 px-2 py-3 text-[13px] text-lead">Pokaż wszystkie</a>
        @endif
    </form>

    @if ($customers->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">{{ $query === '' ? 'Jeszcze nikogo tu nie ma' : 'Nikogo nie znalazłam' }}</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">
                {{ $query === ''
                    ? 'Pierwsza osoba pojawi się tutaj razem z pierwszym zamówieniem, zapisem na warsztat albo formularzem ze strony.'
                    : 'Sprawdź pisownię albo wpisz tylko część: samo imię, początek adresu e-mail albo ostatnie cyfry telefonu.' }}
            </p>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($customers as $customer)
                <div class="flex flex-wrap items-center gap-3.5 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="min-w-0 flex-[1_1_240px]">
                        <div class="text-[14.5px] text-ink">{{ $customer->name }}</div>
                        <div class="mt-0.5 text-[12.5px] text-label [overflow-wrap:anywhere]">{{ $customer->email }}@if ($customer->phones !== []) · {{ implode(', ', $customer->phones) }}@endif</div>
                        <div class="mt-1 text-[12.5px] text-hint">{{ $customer->activity() }}</div>
                    </div>
                    <div class="flex-none text-[12.5px] text-hint tabular-nums">ostatnio {{ $customer->lastSeenAt->format('j.m.Y') }}</div>
                    <a href="{{ route('admin.customers.show', $customer->key) }}" class="flex-none rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark hover:text-ink">Karta</a>
                </div>
            @endforeach
        </div>

        @if ($customers->hasPages())
            <div class="mt-6 flex justify-between gap-4 text-[13.5px]">
                @if ($customers->previousPageUrl())
                    <a href="{{ $customers->previousPageUrl() }}">← Poprzednie</a>
                @else
                    <span></span>
                @endif
                @if ($customers->nextPageUrl())
                    <a href="{{ $customers->nextPageUrl() }}">Następne →</a>
                @endif
            </div>
        @endif
    @endif

    <section aria-labelledby="zadania-rodo" class="mt-12">
        <h2 id="zadania-rodo" class="mb-2 font-serif text-[24px]">Wykonane żądania RODO</h2>
        <p class="mb-4 max-w-[60ch] text-[13px] text-label">Dowód, że odpowiedziałaś na prośbę. Osoba jest tu zapisana bez adresu — po usunięciu danych nikt jej już z tej listy nie odczyta.</p>
        @if ($privacyRequests->isEmpty())
            <p class="rounded-[4px] border border-dashed border-line-strong bg-linen px-5 py-4 text-[13px] text-label">Na razie żadnych.</p>
        @else
            <div class="grid gap-2">
                @foreach ($privacyRequests as $privacyRequest)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-[4px] border border-divider bg-linen px-4 py-3 text-[13px]">
                        <span class="flex-none text-hint tabular-nums">{{ $privacyRequest->created_at->format('j.m.Y, H:i') }}</span>
                        <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $privacyRequest->type->chipClass() }}">{{ $privacyRequest->type->label() }}</span>
                        <span class="min-w-0 flex-[1_1_240px] text-graphite">{{ $privacyRequest->summaryLabel() }}</span>
                        <span class="flex-none text-hint">{{ $privacyRequest->performed_by }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-admin::layout>
