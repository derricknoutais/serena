<script setup lang="ts">
import Card from '@/components/Card.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    activities: {
        data: {
            id: number;
            description: string;
            event: string | null;
            created_at: string | null;
            causer: { id: number; name: string; email: string } | null;
            properties: Record<string, unknown>;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        event?: string | null;
        user_id?: number | null;
        search?: string | null;
    };
    users: { id: number; name: string; email: string }[];
    events: string[];
}>();

const filterState = reactive({
    event: props.filters.event ?? '',
    user_id: props.filters.user_id ?? '',
    search: props.filters.search ?? '',
});

const submitFilters = () => {
    router.get('/activity', {
        event: filterState.event || undefined,
        user_id: filterState.user_id || undefined,
        search: filterState.search || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const hasFilters = computed(() => Boolean(filterState.event || filterState.user_id || filterState.search));
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Activités', href: '/activity' }]">
        <Head title="Activités" />

        <div class="space-y-6 px-4 pb-8">
            <Card>
                <div class="space-y-1 border-b border-serena-border/60 pb-4">
                    <h3 class="text-lg font-semibold text-serena-text-main">Activités</h3>
                    <p class="text-sm text-serena-text-muted">Historique des actions avec filtres</p>
                </div>
                <div class="space-y-4 pt-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <label for="event" class="text-xs font-medium text-serena-text-muted">Type d'action</label>
                            <select
                                id="event"
                                v-model="filterState.event"
                                class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option value="">Toutes</option>
                                <option v-for="event in events" :key="event" :value="event">{{ event }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="user" class="text-xs font-medium text-serena-text-muted">Utilisateur</label>
                            <select
                                id="user"
                                v-model="filterState.user_id"
                                class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option value="">Tous</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <TextInput
                                id="search"
                                v-model="filterState.search"
                                label="Recherche"
                                type="text"
                                placeholder="Description, détails…"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <PrimaryButton type="button" class="px-4 py-2 text-sm" @click="submitFilters">Filtrer</PrimaryButton>
                        <SecondaryButton
                            v-if="hasFilters"
                            type="button"
                            class="px-4 py-2 text-sm"
                            @click="() => { filterState.event=''; filterState.user_id=''; filterState.search=''; submitFilters(); }"
                        >
                            Réinitialiser
                        </SecondaryButton>
                    </div>
                </div>
            </Card>

            <Card>
                <div class="divide-y divide-serena-border/60">
                    <div
                        v-for="activity in activities.data"
                        :key="activity.id"
                        class="grid gap-2 px-4 py-3 md:grid-cols-5 md:items-center"
                    >
                        <div class="md:col-span-2">
                            <p class="font-medium text-serena-text-main">{{ activity.description }}</p>
                            <p class="text-xs text-serena-text-muted" v-if="activity.properties">
                                {{ activity.properties }}
                            </p>
                        </div>
                        <div class="text-sm capitalize text-serena-text-muted">
                            {{ activity.event || 'non défini' }}
                        </div>
                        <div class="text-sm text-serena-text-muted">
                            <div v-if="activity.causer">
                                {{ activity.causer.name }}
                                <span class="text-xs text-serena-text-muted/80">({{ activity.causer.email }})</span>
                            </div>
                            <div v-else class="text-xs">Système</div>
                        </div>
                        <div class="text-sm text-serena-text-muted">
                            {{ activity.created_at }}
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
