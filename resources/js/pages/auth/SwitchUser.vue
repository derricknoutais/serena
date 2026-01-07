<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import QrScanner from '@/components/Housekeeping/QrScanner.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { User } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useInitials } from '@/composables/useInitials';

interface SwitchUserAccount extends Pick<User, 'id' | 'name' | 'email' | 'avatar'> {
    role?: string | null;
}

const props = defineProps<{
    users: SwitchUserAccount[];
    currentUserId: number;
}>();

const users = computed(() => props.users);
const selectedUserId = ref<number | null>(props.currentUserId ?? null);
const showPasswordStep = ref(false);
const passwordValue = ref('');
const badgeCode = ref('');
const badgePin = ref('');
const badgeScannerOpen = ref(false);
const showBadgeLogin = ref(false);

const { getInitials } = useInitials();
const colorClasses = [
    'bg-amber-100 text-amber-900',
    'bg-emerald-100 text-emerald-900',
    'bg-sky-100 text-sky-900',
    'bg-rose-100 text-rose-900',
    'bg-indigo-100 text-indigo-900',
    'bg-lime-100 text-lime-900',
    'bg-orange-100 text-orange-900',
    'bg-teal-100 text-teal-900',
];

const colorForUser = (userId: number) => colorClasses[Math.abs(userId) % colorClasses.length];
const currentUser = computed(() => users.value.find((user) => user.id === props.currentUserId) ?? null);
const otherUsers = computed(() => users.value.filter((user) => user.id !== props.currentUserId));

const userError = computed(() => {
    return selectedUserId.value ? '' : 'Sélectionnez un compte.';
});

const passwordError = computed(() => {
    return passwordValue.value ? '' : 'Mot de passe requis.';
});

const isInvalid = computed(() => {
    return !selectedUserId.value || !passwordValue.value;
});
const isBadgeInvalid = computed(() => !badgeCode.value || !badgePin.value);

const parseBadgePayload = (value: string) => {
    const prefix = 'serena-badge:';
    const raw = value.startsWith(prefix) ? value.slice(prefix.length) : value;
    return raw.trim().toUpperCase();
};

const handleBadgeDetected = (value: string) => {
    badgeCode.value = parseBadgePayload(value);
    badgeScannerOpen.value = false;
};

const selectUser = (userId: number) => {
    selectedUserId.value = userId;
    showPasswordStep.value = true;
};

const resetSelection = () => {
    showPasswordStep.value = false;
    passwordValue.value = '';
};

const toggleLoginMode = () => {
    showBadgeLogin.value = !showBadgeLogin.value;
    badgeScannerOpen.value = false;
};
</script>

<template>
    <AuthLayout
        title="Changer d'utilisateur"
        description="Choisissez un compte du même établissement et confirmez le mot de passe."
    >
        <Head title="Changer d'utilisateur" />

        <Form v-if="!showBadgeLogin" action="/switch-user" method="post" v-slot="{ errors, processing }">
            <div class="space-y-6">
                <div v-if="!showPasswordStep" class="space-y-5 flex flex-col items-center">
                    <div v-if="currentUser" class="space-y-3">
                        <button
                            type="button"
                            class="flex flex-col gap-3 rounded-2xl border border-indigo-500/50 bg-indigo-500/5 p-4 text-left text-sm transition hover:border-indigo-500/70"
                            @click="selectUser(currentUser.id)"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-16 w-1/3 items-center justify-center rounded-xl text-base font-semibold"
                                    :class="colorForUser(currentUser.id)"
                                >
                                    {{ getInitials(currentUser.name) }}
                                </div>
                                <div class="font-medium text-foreground">{{ currentUser.name }}</div>
                            </div>
                            <div class="w-full rounded-full bg-indigo-600/10 px-2 py-1 text-center text-[11px] font-medium text-indigo-700">
                                Session actuelle
                            </div>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium text-foreground">Comptes disponibles</p>

                        <div v-if="otherUsers.length" class="grid gap-3 sm:grid-cols-2">
                            <button
                                v-for="user in otherUsers"
                                :key="user.id"
                                type="button"
                                class="flex w-full gap-3 rounded-2xl border border-border/60 bg-muted/20 p-4 text-left text-sm transition hover:border-muted-foreground/40 hover:cursor-pointer"
                                @click="selectUser(user.id)"
                            >
                                <div
                                    class="flex items-center w-1/3 justify-center rounded-xl text-base font-semibold"
                                    :class="colorForUser(user.id)"
                                >
                                    {{ getInitials(user.name) }}
                                </div>
                                <div class="font-medium text-foreground">{{ user.name }}</div>
                            </button>
                        </div>

                        <p v-else class="text-sm text-muted-foreground">
                            Aucun compte n'est disponible pour ce tenant.
                        </p>
                    </div>

                    <InputError :message="errors.user_id || userError" />
                </div>

                <div v-else class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-foreground">Compte sélectionné</p>
                        <button
                            type="button"
                            class="text-xs font-medium text-serena-primary transition hover:text-serena-primary-dark"
                            @click="resetSelection"
                        >
                            Choisir un autre compte
                        </button>
                    </div>

                    <div class="rounded-xl border border-border/60 bg-muted/30 p-3 text-sm">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-semibold"
                                :class="colorForUser(selectedUserId ?? 0)"
                            >
                                {{ getInitials(users.find((user) => user.id === selectedUserId)?.name) }}
                            </div>
                            <div>
                                <div class="font-medium text-foreground">
                                    {{ users.find((user) => user.id === selectedUserId)?.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ users.find((user) => user.id === selectedUserId)?.email }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ users.find((user) => user.id === selectedUserId)?.role || 'Compte' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm font-medium text-foreground">Mot de passe</p>
                    <div class="grid gap-2">
                        <TextInput
                            id="password"
                            v-model="passwordValue"
                            label="Mot de passe"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Mot de passe"
                        />
                        <InputError :message="errors.password || passwordError" />
                    </div>
                    <input type="hidden" name="user_id" :value="selectedUserId ?? ''" />
                </div>

                <PrimaryButton
                    type="submit"
                    class="w-full justify-center"
                    :disabled="processing || isInvalid"
                    data-test="switch-user-submit"
                >
                    <Spinner v-if="processing" />
                    Basculer vers ce compte
                </PrimaryButton>
            </div>
        </Form>

        <div v-else class="rounded-xl border border-border/60 bg-muted/40 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">Basculer par badge</h3>
                    <p class="text-xs text-muted-foreground">
                        Scannez le QR du badge et saisissez le PIN court.
                    </p>
                </div>
                <SecondaryButton type="button" class="px-4 py-2 text-xs" @click="badgeScannerOpen = true">
                    Scanner le badge
                </SecondaryButton>
            </div>

            <Form action="/switch-user/badge" method="post" v-slot="{ errors, processing }" class="mt-4 space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <TextInput
                            id="switch_badge_code"
                            v-model="badgeCode"
                            label="Code badge"
                            name="badge_code"
                            required
                            placeholder="SERENA-XXXX"
                        />
                        <InputError :message="errors.badge_code" />
                    </div>

                    <div class="grid gap-2">
                        <TextInput
                            id="switch_badge_pin"
                            v-model="badgePin"
                            label="PIN"
                            type="password"
                            name="pin"
                            required
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            placeholder="••••"
                        />
                        <InputError :message="errors.pin" />
                    </div>
                </div>

                <PrimaryButton
                    type="submit"
                    class="w-full justify-center"
                    :disabled="processing || isBadgeInvalid"
                >
                    <Spinner v-if="processing" />
                    Basculer avec le badge
                </PrimaryButton>
            </Form>
        </div>

        <button
            type="button"
            class="mt-6 text-center text-sm font-medium text-serena-primary transition hover:text-serena-primary-dark"
            @click="toggleLoginMode"
        >
            {{ showBadgeLogin ? 'Basculer par liste' : 'Basculer par badge' }}
        </button>

        <div
            v-if="badgeScannerOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        >
            <QrScanner @close="badgeScannerOpen = false" @detected="handleBadgeDetected" />
        </div>
    </AuthLayout>
</template>
