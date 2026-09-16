@inject('settings', 'App\Modules\Settings\Settings')
@php
    $nameMax = (int) $settings->get('voucher_recipient_name_max_chars', 40);
    $noteMax = (int) $settings->get('voucher_dedication_max_chars', 180);
    $validUntil = now()->addMonthsNoOverflow(max(1, (int) $settings->get('voucher_validity_months', 12)))->translatedFormat('j F Y');
@endphp
{{-- Inside the product's add-to-cart form: the name and dedication go with the voucher, and the preview shows the printed card. --}}
<input type="hidden" name="type" value="voucher">
<div x-data="{ recipient: '', dedication: '' }" class="mb-[26px] rounded-[4px] border border-line bg-cream px-5 py-[22px]">
    <div class="mb-3.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Dla kogo ten voucher?</div>

    <label for="voucher-for" class="mb-1.5 block text-[13.5px] text-graphite">Imię na voucherze</label>
    <input id="voucher-for" name="recipient_name" x-model="recipient" maxlength="{{ $nameMax }}" placeholder="np. Ania" autocomplete="off"
           aria-describedby="voucher-for-hint"
           class="w-full min-w-0 rounded-[4px] border border-line bg-white px-[15px] py-[13px] text-[16px] text-ink placeholder:text-hint focus:border-ink">
    <div id="voucher-for-hint" class="mt-2 mb-4 flex justify-between gap-3 text-[12.5px] text-hint">
        <span>Nieobowiązkowe — puste pole nie pokaże się na voucherze</span>
        <span class="flex-none whitespace-nowrap" x-text="recipient.length + ' / {{ $nameMax }}'">0 / {{ $nameMax }}</span>
    </div>

    <label for="voucher-note" class="mb-1.5 block text-[13.5px] text-graphite">Dedykacja</label>
    <textarea id="voucher-note" name="dedication" x-model="dedication" maxlength="{{ $noteMax }}" rows="3" placeholder="Kilka słów od Ciebie"
              aria-describedby="voucher-note-hint"
              class="block w-full min-w-0 resize-y rounded-[4px] border border-line bg-white px-[15px] py-[13px] font-sans text-[16px] leading-[1.55] text-ink placeholder:text-hint focus:border-ink"></textarea>
    <div id="voucher-note-hint" class="mt-2 flex justify-between gap-3 text-[12.5px] text-hint">
        <span>Trafi na voucher w PDF, który dostaniesz mailem</span>
        <span class="flex-none whitespace-nowrap" x-text="dedication.length + ' / {{ $noteMax }}'">0 / {{ $noteMax }}</span>
    </div>

    <div class="mt-[22px] mb-2.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Tak będzie wyglądał</div>
    <div class="rounded-[4px] border border-divider bg-sand px-[22px] py-7 text-center">
        <div class="mb-3.5 text-[10.5px] tracking-[0.3em] text-brown uppercase">mellowaura &middot; voucher</div>
        <div class="mb-2.5 font-serif text-[24px] leading-[1.2] font-light text-balance">{{ $product->name }}</div>
        <div class="mb-3 font-serif text-[21px] text-brown italic [overflow-wrap:anywhere]" x-text="recipient.trim() || 'Imię osoby, która go dostanie'">Imię osoby, która go dostanie</div>
        <p class="mx-auto mb-4 max-w-[36ch] text-[14.5px] leading-[1.6] whitespace-pre-line text-lead [overflow-wrap:anywhere]" x-text="dedication.trim() || 'Tu pojawi się Twoja dedykacja.'">Tu pojawi się Twoja dedykacja.</p>
        <div class="text-[12px] text-label">Ważny do {{ $validUntil }}</div>
    </div>
</div>
