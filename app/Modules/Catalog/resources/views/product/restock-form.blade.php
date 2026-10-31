@use('App\Modules\Catalog\Http\Controllers\StockNotificationController')
{{-- „Napisz do mnie, gdy wróci” under a sold-out size (MA-266). The browser that signed up sees that it waits and can step back. --}}
@php
    $token = session(StockNotificationController::SESSION.'.'.$variant->id);
    $status = session('restock_status');
@endphp
<section id="powiadom" aria-labelledby="restock-heading" class="glass mb-[26px] scroll-mt-28 rounded-[20px] px-5 py-[22px]">
    <p class="mb-2.5 flex items-center gap-2 text-[11.5px] tracking-[0.16em] text-error uppercase">
        <span class="size-[7px] flex-none rounded-full bg-error"></span>{{ __('catalog::restock.sold_out') }}
    </p>
    <h2 id="restock-heading" class="mb-2 font-serif text-[26px] leading-[1.1] font-light text-balance">{{ __('catalog::restock.heading') }}</h2>

    @if ($status === 'subscribed' || $token)
        <p role="status" class="mt-3 rounded-[14px] border border-success-line bg-success-soft px-3.5 py-3 text-[13.5px] leading-[1.5] text-success-text">
            {{ __($status === 'subscribed' ? 'catalog::restock.subscribed' : 'catalog::restock.waiting') }}
        </p>
        @if ($token)
            <form method="post" action="{{ route('stock-notifications.destroy', $token) }}" class="mt-3">
                @csrf
                @method('DELETE')
                <x-shared::button variant="secondary" size="md">{{ __('catalog::restock.unsubscribe') }}</x-shared::button>
            </form>
        @endif
    @else
        @if ($status === 'unsubscribed')
            <p role="status" class="mb-3 text-[13.5px] text-success-text">{{ __('catalog::restock.unsubscribed') }}</p>
        @endif
        <p class="mb-4 text-[14.5px] leading-[1.6] text-pretty text-lead">{{ __('catalog::restock.lead') }}</p>
        <form method="post" action="{{ route('stock-notifications.store') }}" novalidate data-submit-once class="grid gap-3">
            @csrf
            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
            <div class="min-w-0">
                <label for="restock-email" class="mb-1.5 block text-[13.5px] text-graphite">{{ __('catalog::restock.email_label') }}</label>
                <input id="restock-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" maxlength="255"
                       placeholder="{{ __('catalog::restock.email_placeholder') }}"
                       @error('email') aria-invalid="true" aria-describedby="restock-email-error" @enderror
                       class="field-control">
                @error('email')
                    <p id="restock-email-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="flex cursor-pointer items-start gap-2.5 py-1 text-[13px] leading-[1.5] text-graphite">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                           @error('consent') aria-invalid="true" aria-describedby="restock-consent-error" @enderror
                           class="mt-[3px] size-4 flex-none accent-ink">
                    <span>{{ __('catalog::restock.consent') }}</span>
                </label>
                @error('consent')
                    <p id="restock-consent-error" class="mt-1 text-[13px] text-error">{{ $message }}</p>
                @enderror
            </div>
            {{-- Hidden from people; a bot fills it in. --}}
            <div aria-hidden="true" class="absolute -left-[9999px]">
                <label for="restock-website">{{ __('catalog::restock.website') }}</label>
                <input id="restock-website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <x-shared::button>{{ __('catalog::restock.submit') }}</x-shared::button>
            @if (Route::has('content.privacy'))
                <p class="text-[12px] leading-[1.5] text-hint">{!! str_replace(':link', '<a href="'.e(route('content.privacy')).'">'.e(__('catalog::restock.privacy_link')).'</a>', e(__('catalog::restock.privacy'))) !!}</p>
            @endif
        </form>
    @endif
</section>
