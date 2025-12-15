<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-serena-text-main">Notifications</h1>
                <p class="text-sm text-serena-text-muted">Consultez et marquez vos notifications comme lues.</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-serena-border bg-white px-3 py-1.5 text-sm font-semibold text-serena-text-main hover:bg-serena-primary-soft hover:text-serena-primary"
                    @click="markAllRead"
                >
                    Tout marquer comme lu
                </button>
            </div>
        </div>

        <div class="mb-3 flex items-center gap-3 text-sm">
            <Link
                href="/notifications"
                class="rounded-full px-3 py-1"
                :class="filters.unread ? 'text-serena-text-muted hover:text-serena-primary' : 'bg-serena-primary-soft text-serena-primary'"
            >
                Toutes
            </Link>
            <Link
                href="/notifications?unread=1"
                class="rounded-full px-3 py-1"
                :class="filters.unread ? 'bg-serena-primary-soft text-serena-primary' : 'text-serena-text-muted hover:text-serena-primary'"
            >
                Non lues
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-serena-border bg-white shadow-sm">
            <div v-if="!notifications.data.length" class="p-6 text-sm text-serena-text-muted">
                Aucune notification.
            </div>
            <ul v-else class="divide-y divide-serena-border">
                <li
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex items-start justify-between gap-3 px-4 py-3"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-serena-text-main">
                                {{ notification.title }}
                            </span>
                            <span
                                v-if="!notification.read_at"
                                class="inline-block h-2 w-2 rounded-full bg-serena-primary"
                            />
                        </div>
                        <p class="text-sm text-serena-text-muted">
                            {{ notification.message }}
                        </p>
                        <p class="text-xs text-serena-text-muted">
                            {{ formatDate(notification.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="notification.cta_url"
                            :href="notification.cta_url"
                            class="text-sm font-semibold text-serena-primary hover:underline"
                        >
                            Voir
                        </Link>
                        <button
                            type="button"
                            class="text-sm text-serena-text-muted hover:text-serena-primary"
                            @click="markRead(notification.id)"
                        >
                            Marquer lu
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import Swal from 'sweetalert2';

export default {
    name: 'NotificationsIndex',
    components: { ConfigLayout, Link },
    props: {
        notifications: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
    },
    methods: {
        formatDate(value) {
            if (!value) return '';
            const date = new Date(value);

            return date.toLocaleString('fr-FR', {
                dateStyle: 'short',
                timeStyle: 'short',
            });
        },
        async markRead(id) {
            try {
                await axios.patch(`/notifications/${id}`);
                this.$inertia.reload({ only: ['notifications'] });
            } catch (error) {
                const message = error?.response?.data?.message ?? 'Impossible de marquer la notification.';
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            }
        },
        async markAllRead() {
            try {
                await axios.patch('/notifications/read-all');
                this.$inertia.reload({ only: ['notifications'] });
            } catch (error) {
                const message = error?.response?.data?.message ?? 'Impossible de marquer les notifications.';
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            }
        },
    },
};
</script>
