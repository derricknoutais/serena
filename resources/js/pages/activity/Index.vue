<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
                <CardHeader>
                    <CardTitle>Activités</CardTitle>
                    <CardDescription>Historique des actions avec filtres</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="event">Type d'action</Label>
                            <select
                                id="event"
                                v-model="filterState.event"
                                class="rounded-md border border-border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Toutes</option>
                                <option v-for="event in events" :key="event" :value="event">{{ event }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="user">Utilisateur</Label>
                            <select
                                id="user"
                                v-model="filterState.user_id"
                                class="rounded-md border border-border bg-background px-3 py-2 text-sm"
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
                            <Label for="search">Recherche</Label>
                            <Input
                                id="search"
                                v-model="filterState.search"
                                type="text"
                                placeholder="Description, détails…"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button size="sm" @click="submitFilters">Filtrer</Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            v-if="hasFilters"
                            @click="() => { filterState.event=''; filterState.user_id=''; filterState.search=''; submitFilters(); }"
                        >
                            Réinitialiser
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="divide-y divide-border p-0">
                    <div
                        v-for="activity in activities.data"
                        :key="activity.id"
                        class="grid gap-2 px-4 py-3 md:grid-cols-5 md:items-center"
                    >
                        <div class="md:col-span-2">
                            <p class="font-medium">{{ activity.description }}</p>
                            <p class="text-xs text-muted-foreground" v-if="activity.properties">
                                {{ activity.properties }}
                            </p>
                        </div>
                        <div class="text-sm text-muted-foreground capitalize">
                            {{ activity.event || 'non défini' }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            <div v-if="activity.causer">
                                {{ activity.causer.name }}
                                <span class="text-xs">({{ activity.causer.email }})</span>
                            </div>
                            <div v-else class="text-xs">Système</div>
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ activity.created_at }}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
