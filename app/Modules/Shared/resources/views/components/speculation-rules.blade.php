{{-- The next page renders on hover, so the click shows it at once (MA-237). The list of pages: Shared\Support\SpeculationRules. --}}
<script type="speculationrules" data-speculation-rules>{!! json_encode(app(\App\Modules\Shared\Support\SpeculationRules::class)->rules(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
