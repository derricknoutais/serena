import axios from 'axios';

const DB_NAME = 'serena-outbox';
const STORE_NAME = 'queue';
let dbPromise = null;

function openDb() {
    if (!('indexedDB' in window)) {
        return Promise.resolve(null);
    }

    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore(STORE_NAME, { keyPath: 'id' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

async function withStore(mode, callback) {
    const db = await openDb();
    if (!db) {
        return callback(null);
    }
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, mode);
        const store = tx.objectStore(STORE_NAME);
        const result = callback(store);
        tx.oncomplete = () => resolve(result);
        tx.onerror = () => reject(tx.error);
    });
}

function randomId() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return `outbox-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export async function enqueue({ type, endpoint, method = 'post', payload = {}, tenant_id, hotel_id }) {
    const item = {
        id: randomId(),
        type,
        endpoint,
        method: method.toLowerCase(),
        payload,
        tenant_id,
        hotel_id,
        created_at: new Date().toISOString(),
        idempotency_key: randomId(),
        status: 'pending',
        last_error: null,
        retry_count: 0,
    };

    await withStore('readwrite', (store) => store.put(item));
    return item;
}

export async function listOutbox() {
    const items = [];
    await withStore('readonly', (store) => {
        const request = store.openCursor();
        request.onsuccess = (event) => {
            const cursor = event.target.result;
            if (cursor) {
                items.push(cursor.value);
                cursor.continue();
            }
        };
    });
    return items;
}

export async function updateOutbox(id, data) {
    await withStore('readwrite', (store) => {
        const getReq = store.get(id);
        getReq.onsuccess = () => {
            const existing = getReq.result;
            if (!existing) return;
            store.put({ ...existing, ...data });
        };
    });
}

export async function deleteOutbox(id) {
    await withStore('readwrite', (store) => store.delete(id));
}

export async function processOutbox({ onSuccess, onError } = {}) {
    if (!navigator.onLine) return;

    const items = await listOutbox();
    for (const item of items.filter((i) => i.status === 'pending' || i.status === 'failed')) {
        try {
            await updateOutbox(item.id, { status: 'sending', last_error: null });
            await axios.request({
                url: item.endpoint,
                method: item.method,
                data: item.payload,
                headers: {
                    'X-Idempotency-Key': item.idempotency_key,
                    Accept: 'application/json',
                },
            });
            await deleteOutbox(item.id);
            onSuccess?.(item);
        } catch (error) {
            const status = error?.response?.status;
            const message = error?.response?.data?.message || error?.message || 'Erreur de synchronisation';
            await updateOutbox(item.id, {
                status: 'failed',
                last_error: `${status || ''} ${message}`,
                retry_count: (item.retry_count || 0) + 1,
            });
            onError?.(item, message);
        }
    }
}

export function listenNetwork(onOnline) {
    window.addEventListener('online', () => onOnline?.());
}
