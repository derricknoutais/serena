<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import InputError from '@/components/InputError.vue';
import Card from '@/components/Card.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { computed, ref } from 'vue';

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

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card class="flex flex-col md:col-span-2">
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Hôtel actif</h3>
                        <p class="text-sm text-serena-text-muted">
                            Toutes les actions sont liées à l’hôtel sélectionné.
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
                            <p class="text-sm text-serena-text-muted">Arrivée / Départ</p>
                            <p class="font-semibold text-serena-text-main">
                                {{ props.hotel?.check_in_time ?? '—' }} / {{ props.hotel?.check_out_time ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-serena-text-muted">Adresse</p>
                            <p class="font-semibold text-serena-text-main">
                                {{ props.hotel?.address ?? '—' }}
                                <span v-if="props.hotel?.city">, {{ props.hotel?.city }}</span>
                                <span v-if="props.hotel?.country">, {{ props.hotel?.country }}</span>
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

                <Card class="relative overflow-hidden">
                    <div class="absolute inset-0 opacity-40">
                        <PlaceholderPattern />
                    </div>
                    <div class="relative flex h-full flex-col justify-between rounded-xl bg-gradient-to-br from-serena-primary-soft to-white p-4">
                        <div>
                            <h3 class="text-lg font-semibold text-serena-text-main">Utilisateurs</h3>
                            <p class="text-sm text-serena-text-muted">
                                Consultez la liste et gérez les rôles dans les paramètres.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div class="space-y-1">
                                <div
                                    class="flex items-center justify-between text-sm"
                                    v-for="user in users.slice(0, 4)"
                                    :key="user.id"
                                >
                                    <span class="font-medium text-serena-text-main">{{ user.name }}</span>
                                    <span class="text-serena-text-muted capitalize">{{ user.role ?? 'aucun' }}</span>
                                </div>
                            </div>
                            <Link
                                href="/settings/roles"
                                class="inline-flex items-center justify-center rounded-full border border-serena-border bg-white px-4 py-2 text-sm font-medium text-serena-text-main transition hover:bg-serena-primary-soft"
                            >
                                Gérer les rôles
                            </Link>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
