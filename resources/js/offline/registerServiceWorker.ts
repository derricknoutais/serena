import axios from 'axios';

const serviceWorkerPath = '/service-worker.js';

export async function ensureServiceWorkerRegistration(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const existing = await navigator.serviceWorker.getRegistration();
        if (existing) {
            return existing;
        }

        return await navigator.serviceWorker.register(serviceWorkerPath);
    } catch {
        return null;
    }
}

async function syncExistingPushSubscription(): Promise<void> {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    try {
        const registration = await ensureServiceWorkerRegistration();
        if (!registration) {
            return;
        }

        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            return;
        }

        const payload = subscription.toJSON();

        await axios.post('/push/subscribe', {
            endpoint: payload.endpoint,
            keys: payload.keys ?? {},
            contentEncoding: 'aesgcm',
            userAgent: navigator.userAgent,
        });
    } catch {
        // fail silently
    }
}

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        void ensureServiceWorkerRegistration().then(() => syncExistingPushSubscription());
    });
}
