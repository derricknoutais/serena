<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-serena-primary">Journal</p>
                    <h1 class="text-2xl font-bold text-serena-text-main">Activités globales</h1>
                    <p class="text-sm text-serena-text-muted">
                        Suivez qui a fait quoi, quand, à travers tous les modules.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <label class="text-sm font-semibold text-serena-text-main">
                        Recherche
                        <input
                            v-model="localFilters.q"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                            placeholder="Réservation, chambre, action..."
                        />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Module
                        <select
                            v-model="localFilters.module"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        >
                            <option value="">Tous</option>
                            <option v-for="module in moduleOptions" :key="module.value" :value="module.value">
                                {{ module.label }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Action
                        <select
                            v-model="localFilters.action"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        >
                            <option value="">Toutes</option>
                            <option v-for="action in actionOptions" :key="action" :value="action">
                                {{ action }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Utilisateur
                        <Multiselect
                            v-model="selectedUser"
                            :options="users"
                            label="name"
                            track-by="id"
                            placeholder="Tous"
                            :allow-empty="true"
                            :close-on-select="true"
                            :clear-on-select="true"
                            class="mt-1"
                        />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Du
                        <input
                            v-model="localFilters.date_from"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Au
                        <input
                            v-model="localFilters.date_to"
                            type="date"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        />
                    </label>
                </div>

                <div v-if="canViewAllHotels" class="mt-4 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm font-semibold text-serena-text-main">
                        <input v-model="localFilters.all_hotels" type="checkbox" class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary" />
                        Tous les hôtels
                    </label>
                    <select
                        v-model="localFilters.hotel_id"
                        class="rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        :disabled="localFilters.all_hotels"
                    >
                        <option value="">Hôtel actif</option>
                        <option v-for="hotel in hotels" :key="hotel.id" :value="hotel.id">
                            {{ hotel.name }}
                        </option>
                    </select>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <PrimaryButton type="button" class="px-4 py-2 text-xs" @click="applyFilters">
                        Filtrer
                    </PrimaryButton>
                    <SecondaryButton type="button" class="px-4 py-2 text-xs" @click="resetFilters">
                        Réinitialiser
                    </SecondaryButton>
                </div>
            </div>

            <div class="space-y-3">
                <div v-if="!activities.data.length" class="rounded-2xl border border-dashed border-serena-border bg-white p-6 text-center text-sm text-serena-text-muted">
                    Aucun événement trouvé.
                </div>
                <div
                    v-for="entry in activities.data"
                    :key="entry.id"
                    class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-serena-primary-soft px-2 py-1 font-semibold text-serena-primary">
                                    {{ entry.module_label_fr }}
                                </span>
                                <span class="rounded-full bg-serena-bg-soft px-2 py-1 font-semibold text-serena-text-muted">
                                    {{ entry.action_label_fr }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-serena-text-main">
                                {{ entry.summary_fr }}
                            </p>
                            <button
                                v-if="entry.subject?.label && subjectLink(entry)"
                                type="button"
                                class="text-xs text-serena-primary hover:underline"
                                @click="goToSubject(entry)"
                            >
                                {{ entry.subject.label }}
                            </button>
                            <p v-else class="text-xs text-serena-text-muted">
                                {{ entry.subject?.label || '—' }}
                            </p>
                            <div v-if="entry.meta && entry.meta.length" class="mt-2 space-y-1 text-xs text-serena-text-muted">
                                <p v-for="line in entry.meta" :key="line">
                                    {{ line }}
                                </p>
                            </div>
                        </div>
                        <div class="text-xs text-serena-text-muted">
                            <div>{{ formatDate(entry.happened_at) }}</div>
                            <div v-if="entry.causer">{{ entry.causer.name }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activities.links?.length" class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="link in activities.links"
                    :key="link.label"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition"
                    :class="link.active ? 'border-serena-primary bg-serena-primary-soft text-serena-primary' : 'border-serena-border text-serena-text-muted'"
                    :disabled="!link.url"
                    v-html="link.label"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'JournalIndex',
    components: {
        AppLayout,
        Multiselect,
        PrimaryButton,
        SecondaryButton,
    },
    props: {
        activities: {
            type: Object,
            default: () => ({ data: [], links: [] }),
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        users: {
            type: Array,
            default: () => [],
        },
        moduleOptions: {
            type: Array,
            default: () => [],
        },
        actionOptions: {
            type: Array,
            default: () => [],
        },
        canViewAllHotels: {
            type: Boolean,
            default: false,
        },
        hotels: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            selectedUser: null,
            localFilters: { ...this.filters },
        };
    },
    computed: {
        breadcrumbs() {
            return [
                { title: 'Journal', href: '/journal' },
            ];
        },
    },
    created() {
        if (this.filters.user_id) {
            this.selectedUser = this.users.find((user) => user.id === Number(this.filters.user_id)) || null;
        }
    },
    methods: {
        applyFilters() {
            const params = {
                ...this.localFilters,
                user_id: this.selectedUser?.id ?? '',
            };

            router.get('/journal', params, { preserveState: true, replace: true });
        },
        resetFilters() {
            this.localFilters = {
                q: '',
                module: '',
                action: '',
                user_id: '',
                date_from: '',
                date_to: '',
                subject_type: '',
                subject_id: '',
                hotel_id: '',
                all_hotels: false,
            };
            this.selectedUser = null;
            this.applyFilters();
        },
        formatDate(value) {
            if (!value) {
                return '—';
            }
            return new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        },
        subjectLink(entry) {
            if (!entry.subject?.type || !entry.subject?.id) {
                return false;
            }
            const type = entry.subject.type;
            const permissions = this.$page?.props?.auth?.can ?? {};

            if (type.includes('Reservation')) {
                return Boolean(permissions.reservations_view_details);
            }
            if (type.includes('Room')) {
                return Boolean(permissions.housekeeping_view ?? false);
            }
            return false;
        },
        goToSubject(entry) {
            if (!entry.subject?.type || !entry.subject?.id) {
                return;
            }
            const type = entry.subject.type;
            if (type.includes('Reservation')) {
                router.visit(`/reservations/${entry.subject.id}/details`);
            } else if (type.includes('Room')) {
                router.visit(`/hk/rooms/${entry.subject.id}`);
            }
        },
    },
};
</script>
