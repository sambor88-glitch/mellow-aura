{{-- One sentence on why the language switch did not land on the same page. --}}
@if (request()->boolean('unavailable'))
    <p role="status" class="mx-auto mt-3.5 max-w-[1120px] px-[22px] text-[13.5px] text-graphite print:hidden">{{ __('localization::switcher.unavailable') }}</p>
@endif
