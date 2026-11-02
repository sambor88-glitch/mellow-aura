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

{{-- Notifications (MA-345). The message comes encrypted from the server and is shown at once, never stored.
     It holds a title, a line, the panel address to open and a tag: never a customer's name or address. --}}
self.addEventListener('push', (event) => {
    let message = {};

    try {
        message = event.data ? event.data.json() : {};
    } catch {
        message = {};
    }

    event.waitUntil(self.registration.showNotification(message.title || 'MellowAura', {
        body: message.body || '',
        tag: message.tag || undefined,
        icon: @js($icon),
        lang: 'pl',
        data: { url: message.url || @js($scope) },
    }));
});

{{-- A tap opens the screen in the panel already open on the phone, or opens the panel. Only panel addresses. --}}
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || @js($scope), self.location.origin);
    const url = target.origin === self.location.origin && target.pathname.startsWith(@js($scope)) ? target.href : new URL(@js($scope), self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const panel = windows.find((client) => new URL(client.url).pathname.startsWith(@js($scope)));

            if (panel) {
                return panel.focus().then((client) => (client || panel).navigate(url)).catch(() => self.clients.openWindow(url));
            }

            return self.clients.openWindow(url);
        }),
    );
});
