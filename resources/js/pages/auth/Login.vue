<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
            v-bind="store.form(tenant ? { query: { tenant: tenant.slug } } : undefined)"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        v-model="emailValue"
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
                    <div class="flex items-center justify-between">
                        <Label for="password">Mot de passe</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Mot de passe oublié ?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        v-model="passwordValue"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Mot de passe"
                        @focus="touched.password = true"
                    />
                    <InputError :message="errors.password || (touched.password ? localErrors.password : '')" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Se souvenir de moi</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing || isLoginInvalid"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Se connecter
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Pas encore de compte ?
                <TextLink :href="register()" :tabindex="5">Créer un compte</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
