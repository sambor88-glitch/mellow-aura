{{-- One part of a screen in <x-admin::tabs> (MA-315). The server shows the open one; the browser switches between them. --}}
@aware(['tabs', 'active' => null])
@props(['name'])
@use('App\Modules\Admin\Support\Tabs')
<section role="tabpanel" id="panel-{{ $name }}" aria-labelledby="tab-{{ $name }}" data-tab-panel="{{ $name }}"
         x-show="tab === @js((string) $name)" @if (Tabs::active($tabs, $active) !== (string) $name) style="display: none" @endif {{ $attributes }}>
    {{ $slot }}
</section>
