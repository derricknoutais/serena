<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import Card from '@/components/Card.vue';
import PushNotificationsCard from '@/components/PushNotificationsCard.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    hotel: { id: number };
    roles: { name: string; label: string }[];
    channels: { key: string; label: string }[];
    events: {
        key: string;
        label: string;
        description: string;
        roles: string[];
        channels: string[];
        default_roles: string[];
        default_channels: string[];
    }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notifications',
        href: '/settings/notifications',
    },
];

const form = useForm({
    events: props.events.reduce<Record<string, { roles: string[]; channels: string[] }>>((acc, event) => {
        acc[event.key] = {
            roles: [...event.roles],
            channels: [...event.channels],
        };
        return acc;
    }, {}),
});

const submit = () => {
    form.put('/settings/notifications', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notifications" />

        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <HeadingSmall
                    title="Notifications"
                    description="Choisissez quels rôles reçoivent les notifications et via quels canaux."
                />

                <PushNotificationsCard />

                <Card class="space-y-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div
                            v-for="event in events"
                            :key="event.key"
                            class="rounded-2xl border border-serena-border/60 bg-white px-5 py-4 shadow-sm"
                        >
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-semibold text-serena-text-main">{{ event.label }}</span>
                                <span class="text-xs text-serena-text-muted">{{ event.description }}</span>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                        Rôles
                                    </span>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="role in roles"
                                            :key="role.name"
                                            class="flex items-center gap-2 rounded-full border border-serena-border/60 px-3 py-1.5 text-xs text-serena-text-main"
                                        >
                                            <input
                                                v-model="form.events[event.key].roles"
                                                type="checkbox"
                                                :value="role.name"
                                                class="h-3.5 w-3.5 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                                            />
                                            {{ role.label }}
                                        </label>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                                        Canaux
                                    </span>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="channel in channels"
                                            :key="channel.key"
                                            class="flex items-center gap-2 rounded-full border border-serena-border/60 px-3 py-1.5 text-xs text-serena-text-main"
                                        >
                                            <input
                                                v-model="form.events[event.key].channels"
                                                type="checkbox"
                                                :value="channel.key"
                                                class="h-3.5 w-3.5 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                                            />
                                            {{ channel.label }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing" type="submit">Enregistrer</PrimaryButton>
                            <p v-if="form.recentlySuccessful" class="text-sm text-serena-text-muted">Enregistré.</p>
                        </div>
                    </form>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
