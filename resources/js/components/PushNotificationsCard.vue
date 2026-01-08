<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { ensureServiceWorkerRegistration } from '@/offline/registerServiceWorker';
import type { AppPageProps } from '@/types';

type WebpushProps = AppPageProps & {
    webpush?: {
        publicKey?: string | null;
    };
};

const page = usePage<WebpushProps>();
const publicKey = computed(() => page.props.webpush?.publicKey ?? null);

const isSupported = ref(false);
const permission = ref<NotificationPermission>('default');
const isSubscribed = ref(false);
const isWorking = ref(false);
const errorMessage = ref<string | null>(null);
const isIos = ref(false);
const isStandalone = ref(false);

const iosNeedsInstall = computed(() => isIos.value && !isStandalone.value);
const isConfigured = computed(() => !!publicKey.value);

const statusLabel = computed(() => {
    if (!isSupported.value) {
        return 'Non supporté';
    }

    if (!isConfigured.value) {
        return 'Configuration manquante';
    }

    if (permission.value === 'denied') {
        return 'Bloquées';
    }

    return isSubscribed.value ? 'Activées' : 'Désactivées';
});

const statusClass = computed(() => {
    if (!isSupported.value || !isConfigured.value) {
        return 'bg-serena-border/40 text-serena-text-muted';
    }

    if (permission.value === 'denied') {
        return 'bg-serena-danger/10 text-serena-danger';
    }

    return isSubscribed.value
        ? 'bg-serena-accent-soft text-serena-accent'
        : 'bg-serena-primary-soft text-serena-primary';
});

const statusDescription = computed(() => {
    if (!isSupported.value) {
        return 'Ce navigateur ne supporte pas les notifications push.';
    }

    if (!isConfigured.value) {
        return 'La clé VAPID publique est manquante sur ce tenant.';
    }

    if (permission.value === 'denied') {
        return 'Les notifications sont bloquées. Autorisez-les dans les réglages du navigateur.';
    }

    return isSubscribed.value
        ? 'Vous recevrez les alertes importantes liées à votre hôtel.'
        : 'Activez les notifications pour recevoir les alertes importantes.';
});

const canShowControls = computed(() => isSupported.value && !iosNeedsInstall.value);
const canEnable = computed(
    () => canShowControls.value && !isSubscribed.value && permission.value !== 'denied' && isConfigured.value,
);
const canDisable = computed(() => canShowControls.value && isSubscribed.value);

function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isStandaloneMode(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches || (window.navigator as any).standalone === true;
}

function toUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i += 1) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

async function refreshSubscription(): Promise<void> {
    try {
        const registration = await ensureServiceWorkerRegistration();
        if (!registration) {
            isSubscribed.value = false;
            return;
        }

        const subscription = await registration.pushManager.getSubscription();
        isSubscribed.value = !!subscription;
    } catch {
        isSubscribed.value = false;
    }
}

async function persistSubscription(subscription: PushSubscription): Promise<void> {
    const payload = subscription.toJSON();

    await axios.post('/push/subscribe', {
        endpoint: payload.endpoint,
        keys: payload.keys ?? {},
        contentEncoding: 'aesgcm',
        userAgent: navigator.userAgent,
    });
}

async function enableNotifications(): Promise<void> {
    if (!publicKey.value) {
        errorMessage.value = 'Clé VAPID publique manquante.';
        return;
    }

    isWorking.value = true;
    errorMessage.value = null;

    try {
        if (permission.value === 'default') {
            permission.value = await Notification.requestPermission();
        }

        if (permission.value !== 'granted') {
            return;
        }

        const registration = await ensureServiceWorkerRegistration();
        if (!registration) {
            errorMessage.value = 'Le service worker est indisponible.';
            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: toUint8Array(publicKey.value),
        });

        await persistSubscription(subscription);
        isSubscribed.value = true;
    } catch {
        errorMessage.value = 'Impossible d’activer les notifications.';
    } finally {
        isWorking.value = false;
    }
}

async function disableNotifications(): Promise<void> {
    isWorking.value = true;
    errorMessage.value = null;

    try {
        const registration = await ensureServiceWorkerRegistration();
        if (!registration) {
            isSubscribed.value = false;
            return;
        }

        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            isSubscribed.value = false;
            return;
        }

        await subscription.unsubscribe();

        await axios.post('/push/unsubscribe', {
            endpoint: subscription.endpoint,
        });

        isSubscribed.value = false;
    } catch {
        errorMessage.value = 'Impossible de désactiver les notifications.';
    } finally {
        isWorking.value = false;
    }
}

onMounted(() => {
    isSupported.value =
        typeof window !== 'undefined' &&
        'Notification' in window &&
        'serviceWorker' in navigator &&
        'PushManager' in window;

    if (!isSupported.value) {
        return;
    }

    permission.value = Notification.permission;
    isIos.value = isIosDevice();
    isStandalone.value = isStandaloneMode();

    void refreshSubscription();
});
</script>

<template>
    <section class="rounded-2xl border border-serena-border/60 bg-serena-card p-6 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold">Notifications push</h3>
                <p class="text-sm text-serena-text-muted">
                    Recevez les alertes importantes sur vos appareils.
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass">
                {{ statusLabel }}
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-serena-text-muted">
                {{ statusDescription }}
            </p>
            <div class="flex flex-wrap gap-2">
                <PrimaryButton v-if="canEnable" :disabled="isWorking" @click="enableNotifications">
                    Activer
                </PrimaryButton>
                <SecondaryButton v-if="canDisable" :disabled="isWorking" @click="disableNotifications">
                    Désactiver
                </SecondaryButton>
            </div>
        </div>

        <div
            v-if="iosNeedsInstall"
            class="mt-4 rounded-xl border border-serena-border/60 bg-serena-primary-soft/60 p-4 text-sm text-serena-text-main"
        >
            Pour activer les notifications sur iPhone : ouvrez dans Safari → Partager → “Ajouter à
            l’écran d’accueil” → ouvrez l’app installée, puis activez les notifications.
        </div>

        <p v-if="errorMessage" class="mt-3 text-sm text-serena-danger">
            {{ errorMessage }}
        </p>
    </section>
</template>
