// „Powiadomienia” in the panel (MA-345): turns the notifications on or off for this phone. The browser asks for the
// permission, makes the subscription with the shop's public key and gives it to the server. On an iPhone it works
// only from the panel added to the home screen (iOS 16.4+), so the screen says how to get there instead of a button.
const base64ToBytes = (text) => {
    const padded = (text + '='.repeat((4 - (text.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
};

export default (config) => ({
    // unsupported, home-screen (iPhone in Safari), denied, off, on, working
    state: 'working',
    message: '',
    endpoint: null,

    async init() {
        const iphone = /iPhone|iPad/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const standalone = navigator.standalone === true || matchMedia('(display-mode: standalone)').matches;

        if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
            this.state = iphone && ! standalone ? 'home-screen' : 'unsupported';

            return;
        }

        if (Notification.permission === 'denied') {
            this.state = 'denied';

            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription && Notification.permission === 'granted') {
            // Sent again on every visit: the browser may have renewed its keys, and the server keeps the last ones.
            await this.save(subscription).catch(() => {});
            this.state = 'on';
        } else {
            this.state = 'off';
        }
    },

    async turnOn() {
        this.state = 'working';
        this.message = '';

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                this.state = permission === 'denied' ? 'denied' : 'off';

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription()
                ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64ToBytes(config.key) });

            await this.save(subscription);
            this.state = 'on';
            this.message = 'Włączone. Wyślij próbne, żeby sprawdzić, czy telefon dzwoni.';
            this.refreshList();
        } catch {
            this.state = 'off';
            this.message = 'Nie udało się włączyć. Sprawdź połączenie i spróbuj jeszcze raz.';
        }
    },

    async turnOff() {
        this.state = 'working';
        this.message = '';

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await this.post(config.forget, { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            this.state = 'off';
            this.message = 'Wyłączone na tym telefonie.';
            this.refreshList();
        } catch {
            this.state = 'on';
            this.message = 'Nie udało się wyłączyć. Spróbuj jeszcze raz.';
        }
    },

    async sendTest() {
        this.message = '';

        try {
            await this.post(config.test, {});
            this.message = 'Wysłane — powinno przyjść w ciągu minuty.';
        } catch {
            this.message = 'Nie udało się wysłać. Spróbuj za chwilę.';
        }
    },

    save(subscription) {
        const data = subscription.toJSON();

        this.endpoint = data.endpoint;

        return this.post(config.store, { ...data, contentEncoding: 'aes128gcm' });
    },

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            body: JSON.stringify(body),
        });

        if (! response.ok) {
            throw new Error(String(response.status));
        }

        return response.json();
    },

    // The list of devices under the button comes from the server: reloaded after a change, without leaving the screen.
    async refreshList() {
        const response = await fetch(location.href, { headers: { Accept: 'text/html' } }).catch(() => null);
        const list = document.getElementById('push-devices');

        if (! response?.ok || ! list) {
            return;
        }

        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById(list.id);

        if (fresh) {
            list.replaceChildren(...fresh.childNodes);
        }
    },
});
