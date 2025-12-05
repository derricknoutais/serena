<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
    tenant?: {
        name?: string | null;
    } | null;
}>();

const form = useForm({
    name: '',
    email: props.email,
    password: '',
    password_confirmation: '',
    token: props.token,
});

const submit = () => {
    form.post('/invitations/accept', {
        onSuccess: () => form.reset('name', 'password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        title="Rejoindre l'equipe"
        :description="tenant?.name ? `Invitation pour ${tenant.name}` : 'Completez votre compte pour rejoindre cette equipe.'"
    >
        <Head title="Accepter l'invitation" />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="space-y-2">
                <TextInput
                    id="name"
                    v-model="form.name"
                    label="Nom complet"
                    type="text"
                    name="name"
                    required
                    autocomplete="name"
                    placeholder="Votre nom"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-2">
                <TextInput
                    id="email"
                    v-model="form.email"
                    label="Email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    readonly
                    class="bg-serena-primary-soft/40"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-2">
                <TextInput
                    id="password"
                    v-model="form.password"
                    label="Mot de passe"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Choisissez un mot de passe"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-2">
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    label="Confirmez le mot de passe"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirmez le mot de passe"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <input type="hidden" name="token" :value="form.token" />

            <div class="space-y-3">
                <PrimaryButton class="w-full" type="submit" :disabled="form.processing">
                    Accepter l'invitation
                </PrimaryButton>
                <p class="text-center text-sm text-muted-foreground">
                    Vous avez deja un compte ?
                    <TextLink href="/login">Connectez-vous</TextLink>
                </p>
            </div>
        </form>
    </AuthBase>
</template>
