@props(['name', 'params', 'once' => null])
@inject('consent', 'App\Modules\Consent\Support\Consent')
{{-- An e-commerce event for Google Analytics. resources/js/analytics.js sends it only after a yes to statistics, also when the yes comes later on this page. --}}
@if ($consent->isNeeded())
    <script type="application/json" data-analytics-event>@json(array_filter(['name' => $name, 'params' => $params, 'once' => $once]))</script>
@endif
