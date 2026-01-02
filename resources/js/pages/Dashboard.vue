<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as analyticsIndex } from '@/routes/analytics';
import { index as cashIndex } from '@/routes/cash';
import { dashboard as frontdeskDashboard } from '@/routes/frontdesk';
import { index as housekeepingIndex } from '@/routes/housekeeping';
import { index as maintenanceIndex } from '@/routes/maintenance';
import { index as reservationsIndex } from '@/routes/reservations';
import { board as roomsBoard } from '@/routes/rooms';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import InputError from '@/components/InputError.vue';
import Card from '@/components/Card.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps<{
    users: {
        id: number;
        name: string;
        email: string;
        role?: string | null;
    }[];
    roles: { name: string }[];
    hotel?: {
        id: number;
        name: string;
        code: string | null;
        currency: string | null;
        timezone: string | null;
        address: string | null;
        city: string | null;
        country: string | null;
        check_in_time: string | null;
        check_out_time: string | null;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const form = useForm({
    email: '',
});

const submitInvitation = () => {
    form.post('/invitations', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const users = computed(() => props.users);
const usersCount = computed(() => props.users.length);
const rolesCount = computed(() => props.roles.length);
const hotelLocation = computed(() => {
    const parts = [props.hotel?.address, props.hotel?.city, props.hotel?.country].filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : '—';
});

const quickActions = computed(() => [
    {
        label: 'Frontdesk',
        description: 'Planning & opérations',
        href: frontdeskDashboard().url,
    },
    {
        label: 'Room Board',
        description: 'Disponibilités & statuts',
        href: roomsBoard().url,
    },
    {
        label: 'Réservations',
        description: 'Liste et suivi',
        href: reservationsIndex().url,
    },
    {
        label: 'Caisse',
        description: 'Sessions & paiements',
        href: cashIndex().url,
    },
    {
        label: 'Housekeeping',
        description: 'Statuts des chambres',
        href: housekeepingIndex().url,
    },
    {
        label: 'Maintenance',
        description: 'Tickets & incidents',
        href: maintenanceIndex().url,
    },
    {
        label: 'Analytique',
        description: 'KPI & performance',
        href: analyticsIndex().url,
    },
]);

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <Card class="relative overflow-hidden">
                    <div class="absolute inset-0 opacity-25">
                        <PlaceholderPattern />
                    </div>
                    <div class="relative space-y-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-serena-text-muted">
                                    Tableau de bord
                                </p>
                                <h2 class="text-2xl font-semibold text-serena-text-main">
                                    Hôtel actif · {{ props.hotel?.name ?? '—' }}
                                </h2>
                                <p class="text-sm text-serena-text-muted">
                                    Pilotez les opérations du jour et accédez rapidement aux modules clés.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-serena-primary-soft px-3 py-1 text-xs font-semibold text-serena-primary">
                                    {{ props.hotel?.timezone ?? 'Fuseau non défini' }}
                                </span>
                                <span class="rounded-full border border-serena-border bg-white px-3 py-1 text-xs font-semibold text-serena-text-main">
                                    Devise · {{ props.hotel?.currency ?? '—' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-serena-border/60 bg-serena-primary-soft/50 p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Utilisateurs</p>
                                <p class="mt-2 text-2xl font-semibold text-serena-text-main">{{ usersCount }}</p>
                            </div>
                            <div class="rounded-xl border border-serena-border/60 bg-white p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Rôles</p>
                                <p class="mt-2 text-2xl font-semibold text-serena-text-main">{{ rolesCount }}</p>
                            </div>
                            <div class="rounded-xl border border-serena-border/60 bg-white p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Arrivée / Départ</p>
                                <p class="mt-2 text-lg font-semibold text-serena-text-main">
                                    {{ props.hotel?.check_in_time ?? '—' }} / {{ props.hotel?.check_out_time ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="action in quickActions"
                                :key="action.label"
                                :href="action.href"
                                class="group rounded-xl border border-serena-border bg-white p-3 transition hover:border-serena-primary/40 hover:bg-serena-primary-soft/60"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-serena-text-main">
                                        {{ action.label }}
                                    </span>
                                    <span class="text-xs text-serena-text-muted group-hover:text-serena-text-main">
                                        Ouvrir
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-serena-text-muted">{{ action.description }}</p>
                            </Link>
                        </div>
                    </div>
                </Card>

                <Card class="flex flex-col">
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Inviter un membre</h3>
                        <p class="text-sm text-serena-text-muted">
                            Envoyez un lien d'invitation pour rejoindre ce tenant.
                        </p>
                    </div>
                    <form class="flex h-full flex-col" @submit.prevent="submitInvitation">
                        <div class="flex-1 space-y-3 pt-4">
                            <div class="space-y-2">
                                <TextInput
                                    id="invite-email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    label="Email de l'invité"
                                    required
                                    autocomplete="email"
                                    placeholder="invite@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                            <p
                                v-if="form.recentlySuccessful"
                                class="text-sm text-serena-text-main"
                            >
                                Invitation envoyée.
                            </p>
                        </div>
                        <div class="border-t border-serena-border/60 pt-3">
                            <PrimaryButton
                                type="submit"
                                class="w-full justify-center"
                                :disabled="form.processing"
                            >
                                Envoyer l'invitation
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Hôtel actif</h3>
                        <p class="text-sm text-serena-text-muted">
                            Détails et paramètres principaux.
                        </p>
                    </div>
                    <div class="grid gap-3 pt-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm text-serena-text-muted">Nom</p>
                            <p class="font-semibold text-serena-text-main">{{ props.hotel?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Code</p>
                            <p class="font-semibold text-serena-text-main">{{ props.hotel?.code ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Devise</p>
                            <p class="font-semibold text-serena-text-main">{{ props.hotel?.currency ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Fuseau horaire</p>
                            <p class="font-semibold text-serena-text-main">{{ props.hotel?.timezone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Adresse</p>
                            <p class="font-semibold text-serena-text-main">{{ hotelLocation }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Arrivée / Départ</p>
                            <p class="font-semibold text-serena-text-main">
                                {{ props.hotel?.check_in_time ?? '—' }} / {{ props.hotel?.check_out_time ?? '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="border-t border-serena-border/60 pt-3">
                        <p class="text-xs text-serena-text-muted">
                            Pour changer d’hôtel, utilisez le sélecteur dans le menu utilisateur.
                        </p>
                    </div>
                </Card>

                <Card class="flex flex-col">
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Équipe</h3>
                        <p class="text-sm text-serena-text-muted">
                            Derniers utilisateurs ajoutés et leurs rôles.
                        </p>
                    </div>
                    <div class="flex-1 space-y-3 pt-4">
                        <div
                            v-for="user in users.slice(0, 6)"
                            :key="user.id"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="font-medium text-serena-text-main">{{ user.name }}</span>
                            <span class="text-serena-text-muted capitalize">{{ user.role ?? 'aucun' }}</span>
                        </div>
                    </div>
                    <div class="border-t border-serena-border/60 pt-3">
                        <Link
                            href="/settings/roles"
                            class="inline-flex w-full items-center justify-center rounded-full border border-serena-border bg-white px-4 py-2 text-sm font-medium text-serena-text-main transition hover:bg-serena-primary-soft"
                        >
                            Gérer les rôles
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
