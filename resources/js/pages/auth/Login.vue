<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import TextLink from '@/components/TextLink.vue';
import QrScanner from '@/components/Housekeeping/QrScanner.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    tenant?: {
        name: string;
        domain: string;
        slug: string;
    } | null;
    centralLoginUrl?: string;
}>();

const emailValue = ref('');
const passwordValue = ref('');
const badgeCode = ref('');
const badgePin = ref('');
const badgeScannerOpen = ref(false);
const showBadgeLogin = ref(false);
const localErrors = reactive({
    email: 'Adresse e-mail requise.',
    password: 'Mot de passe requis.',
});
const touched = reactive({
    email: false,
    password: false,
});

const validate = () => {
    localErrors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue.value)
        ? ''
        : 'Adresse e-mail invalide.';
    localErrors.password = passwordValue.value ? '' : 'Mot de passe requis.';
};

watch([emailValue, passwordValue], validate, { immediate: true });

const isLoginInvalid = computed(() => Object.values(localErrors).some((message) => message !== ''));
const isBadgeInvalid = computed(() => !badgeCode.value || !badgePin.value);
const toggleLoginMode = () => {
    showBadgeLogin.value = !showBadgeLogin.value;
    badgeScannerOpen.value = false;
};

const parseBadgePayload = (value: string) => {
    const prefix = 'serena-badge:';
    const raw = value.startsWith(prefix) ? value.slice(prefix.length) : value;
    return raw.trim().toUpperCase();
};

const handleBadgeDetected = (value: string) => {
    badgeCode.value = parseBadgePayload(value);
    badgeScannerOpen.value = false;
};
</script>

<template>
    <AuthBase
        title="Connexion"
        description="Saisissez vos identifiants pour accéder à votre compte"
    >
        <Head title="Connexion" />

        <div
            v-if="tenant"
            class="rounded-md border border-border/60 bg-muted/40 p-3 text-sm text-muted-foreground"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="font-medium text-foreground">{{ tenant.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ tenant.domain }}
                    </p>
                </div>
                <TextLink
                    v-if="centralLoginUrl"
                    :href="centralLoginUrl"
                    class="text-xs"
                >
                    Ce n'est pas votre société ?
                </TextLink>
            </div>
        </div>

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-if="!showBadgeLogin"
            v-bind="store.form(tenant ? { query: { tenant: tenant.slug } } : undefined)"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <TextInput
                        id="email"
                        v-model="emailValue"
                        label="Adresse e-mail"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@exemple.com"
                        @focus="touched.email = true"
                    />
                    <InputError :message="errors.email || (touched.email ? localErrors.email : '')" />
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="password"
                        v-model="passwordValue"
                        label="Mot de passe"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Mot de passe"
                        @focus="touched.password = true"
                    >
                        <template #label-action v-if="canResetPassword">
                            <TextLink
                                :href="request()"
                                class="text-xs"
                                :tabindex="5"
                            >
                                Mot de passe oublié ?
                            </TextLink>
                        </template>
                    </TextInput>
                    <InputError :message="errors.password || (touched.password ? localErrors.password : '')" />
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember" class="flex items-center space-x-3 text-sm text-serena-text-muted">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Se souvenir de moi</span>
                    </label>
                </div>

                <PrimaryButton
                    type="submit"
                    class="mt-4 w-full justify-center"
                    :tabindex="4"
                    :disabled="processing || isLoginInvalid"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Se connecter
                </PrimaryButton>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Pas encore de compte ?
                <TextLink :href="register()" :tabindex="5">Créer un compte</TextLink>
            </div>
        </Form>

        <div v-else class="rounded-xl border border-border/60 bg-muted/40 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">Connexion par badge</h3>
                    <p class="text-xs text-muted-foreground">
                        Scannez le QR du badge et entrez le PIN court.
                    </p>
                </div>
                <SecondaryButton type="button" class="px-4 py-2 text-xs" @click="badgeScannerOpen = true">
                    Scanner le badge
                </SecondaryButton>
            </div>

            <Form action="/login/badge" method="post" v-slot="{ errors, processing }" class="mt-4 space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <TextInput
                            id="badge_code"
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
                            id="badge_pin"
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
                    Se connecter avec le badge
                </PrimaryButton>
            </Form>
        </div>

        <button
            type="button"
            class="mt-6 text-center text-sm font-medium text-serena-primary transition hover:text-serena-primary-dark hover:cursor-pointer"
            @click="toggleLoginMode"
        >
            {{ showBadgeLogin ? 'Connexion classique' : 'Connexion par badge' }}
        </button>

        <div
            v-if="badgeScannerOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        >
            <QrScanner @close="badgeScannerOpen = false" @detected="handleBadgeDetected" />
        </div>
    </AuthBase>
</template>
