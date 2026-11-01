{{-- The panel's service worker (MA-328). It stores one page, "Brak połączenia", and nothing from the panel itself:
     the screens hold customers' data, so every request goes to the server as if the worker were not there.
     Only a page that cannot load because the phone is offline gets the stored page instead. --}}
const CACHE = 'panel-offline-v1';
const OFFLINE = @js($offline);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE, { cache: 'reload' }))));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE).then((page) => page || Response.error())),
    );
});
