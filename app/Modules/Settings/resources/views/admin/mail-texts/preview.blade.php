<x-admin::layout :title="'Podgląd: '.$label" lead="Mail dla przykładowego zamówienia z tekstami, które są teraz w polach. Nic nie jest jeszcze zapisane ani wysłane.">
    <div class="max-w-[760px] rounded-[4px] border border-line bg-cream p-[22px]">
        <p class="mb-1 text-[11.5px] tracking-[0.16em] text-label uppercase">Temat{{ $locale === 'pl' ? '' : ' · po angielsku' }}</p>
        <p class="mb-4 text-[16px] text-ink">{{ $subject }}</p>
        {{-- The mail is a whole HTML document with its own styles, so it renders in a frame of its own. --}}
        <iframe title="Podgląd maila" sandbox srcdoc="{{ $html }}" class="h-[1100px] w-full rounded-[4px] border border-line bg-white"></iframe>
    </div>
</x-admin::layout>
