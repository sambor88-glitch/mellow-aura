<x-admin::layout title="Niewysłane maile" lead="Każdy mail sklep próbuje wysłać sześć razy w ciągu dwóch godzin. Tutaj trafiają te, których mimo to nie udało się wysłać.">
    @if ($mails->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Wszystkie maile wyszły</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">Gdy jakiś mail nie wyjdzie mimo kilku prób, pojawi się tutaj z adresem i przyciskiem do ponownej wysyłki.</p>
        </div>
    @else
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-[4px] border border-line bg-cream px-[18px] py-4">
            <p class="min-w-0 flex-[1_1_320px] text-[13.5px] leading-[1.55] text-lead">Najpierw wyślij jeszcze raz. Jeśli mail znów tu wróci, napisz do klientki ze swojej skrzynki albo zadzwoń, a osobie, która opiekuje się sklepem, prześlij szczegóły techniczne.</p>
            @if ($mails->count() > 1)
                <form method="post" action="{{ route('admin.failed-mails.retry-all') }}">
                    @csrf
                    <button class="min-h-11 rounded-full bg-ink px-5 text-[13px] text-linen hover:bg-rose hover:text-ink">Wyślij wszystkie jeszcze raz</button>
                </form>
            @endif
        </div>

        <div class="grid gap-2.5">
            @foreach ($mails as $mail)
                <article class="flex flex-wrap items-start gap-x-5 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="flex-none text-[12.5px] text-hint tabular-nums">
                        <div>{{ $mail['failedAt']->format('j.m.Y') }}</div>
                        <div>{{ $mail['failedAt']->format('H:i') }}</div>
                    </div>
                    <div class="min-w-0 flex-[1_1_260px]">
                        <h2 class="text-[14.5px] text-ink">{{ $mail['description'] }}</h2>
                        <div class="mt-1 text-[13px] [overflow-wrap:anywhere] text-lead">Do: {{ implode(', ', $mail['to']) }}</div>
                        <details class="mt-2 text-[12.5px] text-label">
                            <summary class="cursor-pointer">Szczegóły techniczne</summary>
                            <p class="mt-1.5 rounded-[4px] bg-linen px-3 py-2 font-mono text-[12px] [overflow-wrap:anywhere] text-graphite">{{ $mail['reason'] }}</p>
                        </details>
                    </div>
                    <div class="flex flex-none flex-wrap items-center gap-2.5">
                        <form method="post" action="{{ route('admin.failed-mails.retry', $mail['id']) }}">
                            @csrf
                            <button class="min-h-11 rounded-full border border-line-strong px-4 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark">Wyślij jeszcze raz</button>
                        </form>
                        <form method="post" action="{{ route('admin.failed-mails.destroy', $mail['id']) }}"
                              x-data x-on:submit="confirm('Usunąć ten mail z listy? Potem nie da się go już wysłać.') || $event.preventDefault()">
                            @csrf
                            @method('DELETE')
                            <button class="min-h-11 rounded-full px-3 text-[13px] text-hint hover:text-error">Usuń z listy</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-admin::layout>
