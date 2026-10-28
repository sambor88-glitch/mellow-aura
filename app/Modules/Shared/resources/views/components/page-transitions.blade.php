{{--
    Pages of the shop fade into each other (MA-237; the animation in app.css, the product photo in resources/js/transitions.js).
    Here and not in app.css, so the panel, which uses the same stylesheet, changes pages at once. With reduced motion
    pages change the usual way.

    Leaving a page before its transition ends (a click during the fade) rejects the transition's promises, and Chromium
    reports that as an error on the page. So a click first ends the running fade, and the promises count as handled.
    This runs before the page is shown, so even the first transition is covered.
--}}
<style>@media (prefers-reduced-motion: no-preference) { @view-transition { navigation: auto; } }</style>
<script>
    (function () {
        var running = null;

        ['pageswap', 'pagereveal'].forEach(function (type) {
            addEventListener(type, function (event) {
                var transition = event.viewTransition;
                if (! transition) {
                    return;
                }

                [transition.ready, transition.updateCallbackDone, transition.finished].forEach(function (done) {
                    done && done.catch(function () {});
                });
                if (type === 'pagereveal') {
                    running = transition;
                    transition.finished.then(function () { running = null; }, function () { running = null; });
                }
            });
        });

        addEventListener('click', function () {
            running && running.skipTransition();
        }, true);
    })();
</script>
