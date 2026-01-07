<script setup lang="ts">
import Card from '@/components/Card.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import type { BreadcrumbItem } from '@/types';

interface BadgeUser {
    id: number;
    name: string;
    email: string;
    role?: string | null;
    badge_code?: string | null;
    badge_qr_svg?: string | null;
    pin_set: boolean;
}

const props = defineProps<{
    users: BadgeUser[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Badges & QR',
        href: '/settings/badges',
    },
];

const pinDrafts = reactive<Record<number, string>>({});
const activePinUserId = ref<number | null>(null);
const activeCodeUserId = ref<number | null>(null);

const pinForm = useForm({
    pin: '',
});

const codeForm = useForm({});

const submitPin = (userId: number) => {
    activePinUserId.value = userId;
    pinForm.pin = pinDrafts[userId] ?? '';
    pinForm.post(`/settings/badges/${userId}/pin`, {
        preserveScroll: true,
        onSuccess: () => {
            pinDrafts[userId] = '';
        },
    });
};

const generateCode = (userId: number) => {
    activeCodeUserId.value = userId;
    codeForm.post(`/settings/badges/${userId}/code`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Badges & QR" />

        <SettingsLayout>
            <div class="space-y-6">
                <Card>
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Badges imprimables</h3>
                        <p class="text-sm text-serena-text-muted">
                            Générez un QR permanent par utilisateur et définissez un PIN court pour la connexion.
                        </p>
                    </div>

                    <div class="divide-y divide-serena-border/60 pt-4">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            class="flex flex-col gap-4 py-6 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="space-y-1">
                                <p class="text-base font-semibold text-serena-text-main">{{ user.name }}</p>
                                <p class="text-sm text-serena-text-muted">{{ user.email }}</p>
                                <p class="text-xs text-serena-text-muted">Rôle : {{ user.role || '—' }}</p>
                            </div>

                            <div class="flex flex-1 flex-col gap-4 lg:flex-row lg:items-center lg:justify-end">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-28 w-28 items-center justify-center rounded-2xl border border-serena-border/60 bg-white"
                                    >
                                        <div
                                            v-if="user.badge_qr_svg"
                                            class="h-full w-full p-2"
                                            v-html="user.badge_qr_svg"
                                        ></div>
                                        <span v-else class="text-xs text-serena-text-muted">QR non généré</span>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="text-xs text-serena-text-muted">Code badge</div>
                                        <div class="rounded-full border border-serena-border/60 px-3 py-1 text-sm">
                                            {{ user.badge_code || '—' }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <SecondaryButton
                                                type="button"
                                                class="px-4 py-2 text-xs"
                                                :disabled="codeForm.processing && activeCodeUserId === user.id"
                                                @click="generateCode(user.id)"
                                            >
                                                {{ user.badge_code ? 'Régénérer' : 'Générer' }}
                                            </SecondaryButton>
                                            <a
                                                v-if="user.badge_code"
                                                :href="`/settings/badges/${user.id}/download`"
                                                class="inline-flex items-center rounded-full border border-serena-border bg-white px-3 py-2 text-xs font-medium text-serena-text-main transition hover:bg-serena-primary-soft"
                                            >
                                                Télécharger
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <label class="text-xs font-medium text-serena-text-muted">
                                        PIN badge (4-8 chiffres)
                                    </label>
                                    <input
                                        v-model="pinDrafts[user.id]"
                                        type="password"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        class="h-9 w-40 rounded-full border border-serena-border px-3 text-sm"
                                        placeholder="••••"
                                    />
                                    <InputError
                                        v-if="activePinUserId === user.id"
                                        :message="pinForm.errors.pin"
                                    />
                                    <div class="flex items-center gap-2">
                                        <PrimaryButton
                                            type="button"
                                            class="px-4 py-2 text-xs"
                                            :disabled="pinForm.processing && activePinUserId === user.id"
                                            @click="submitPin(user.id)"
                                        >
                                            {{ user.pin_set ? 'Mettre à jour' : 'Définir' }}
                                        </PrimaryButton>
                                        <span
                                            class="text-xs"
                                            :class="user.pin_set ? 'text-emerald-600' : 'text-serena-text-muted'"
                                        >
                                            {{ user.pin_set ? 'PIN défini' : 'PIN manquant' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
