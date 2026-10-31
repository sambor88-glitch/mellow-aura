{{--
    „Zobacz u siebie” (MA-310): a button and a full-screen window with the 3D model, which a phone can put on the
    visitor's table (resources/js/ar.js). Shown only with JavaScript — without it there is nothing to open.
    data: the Alpine expression that starts it, e.g. arViewer({ source: '…', … }).
--}}
@props(['data', 'name'])
<div x-data="{{ $data }}" {{ $attributes }}>
    {{-- x-cloak: hidden until Alpine starts, so never a dead button without JavaScript. --}}
    <x-shared::button variant="secondary" size="md" type="button" x-cloak x-on:click="open()" aria-haspopup="dialog">
        {{ __('catalog::product.ar_open') }} <span aria-hidden="true">→</span>
    </x-shared::button>

    {{-- Esc or × closes it, and focus goes back to the button. A solid background: no blur under a 3D scene on a phone. --}}
    <dialog x-ref="dialog" aria-label="{{ __('catalog::product.ar_dialog', ['name' => $name]) }}"
            class="fixed inset-0 size-full max-h-none max-w-none animate-ma-in flex-col bg-linen pad-safe [--pad:0px] open:flex backdrop:bg-ink/55">
        <div x-ref="stage" class="relative min-h-0 flex-1"></div>

        <div class="flex-none px-6 pt-3 pb-6 text-center text-[13.5px] leading-[1.5] text-lead">
            <p x-show="loading" role="status">{{ __('catalog::product.ar_loading') }}</p>
            <p x-cloak x-show="failed" role="alert" class="text-error">{{ __('catalog::product.ar_failed') }}</p>
            <p x-cloak x-show="ready && ! canAr">{{ __('catalog::product.ar_drag') }} {{ __('catalog::product.ar_phone_hint') }}</p>
            <p x-cloak x-show="ready && canAr">{{ __('catalog::product.ar_drag') }}</p>
        </div>

        <button type="button" x-on:click="$refs.dialog.close()" aria-label="{{ __('catalog::product.ar_close') }}"
                class="absolute top-5 right-5 z-10 grid size-11 place-items-center rounded-full border border-line-strong bg-cream text-[24px] leading-none text-ink hover:bg-rose">×</button>
    </dialog>
</div>
