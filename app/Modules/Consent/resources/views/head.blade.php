@inject('consent', 'App\Modules\Consent\Support\Consent')
{{-- Google Analytics loads only after a yes (Consent Mode, basic mode). resources/js/consent.js starts it the same way right after the click. --}}
@if ($consent->allowsAnalytics(request()))
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
        gtag('consent', 'update', { analytics_storage: 'granted' });
        {{-- A page rendered ahead of a click (MA-237) counts its view only once it is shown, never for a link only hovered. --}}
        (function (start) {
            document.prerendering ? document.addEventListener('prerenderingchange', start, { once: true }) : start();
        })(function () {
            gtag('js', new Date());
            gtag('config', @js($consent->measurementId()));
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $consent->measurementId() }}"></script>
@endif
