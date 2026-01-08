self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};

    try {
        payload = event.data.json();
    } catch {
        payload = {
            title: 'Notification',
            body: event.data.text(),
        };
    }

    const data = payload.data || {};
    const url = data.url || payload.url || '/';

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Notification', {
            body: payload.body || '',
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/badge-192.png',
            tag: payload.tag,
            data: {
                ...data,
                url,
            },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification?.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    return client.focus().then(() => client.navigate(url));
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url);
            }

            return undefined;
        }),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request));
});
