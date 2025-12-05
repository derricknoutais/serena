<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

defineProps<{
    status?: string;
}>();

const emailValue = ref('');
const localErrors = reactive({
    email: 'Adresse e-mail requise.',
});

watch(emailValue, () => {
    localErrors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue.value)
        ? ''
        : 'Adresse e-mail invalide.';
}, { immediate: true });

const isInvalid = computed(() => localErrors.email !== '');
</script>

<template>
    <AuthLayout
        title="Mot de passe oublié"
        description="Saisissez votre e-mail pour recevoir un lien de réinitialisation"
    >
        <Head title="Mot de passe oublié" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <TextInput
                        id="email"
                        v-model="emailValue"
                        label="Adresse e-mail"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="email@exemple.com"
                    />
                    <InputError :message="errors.email || localErrors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <PrimaryButton
                        class="w-full justify-center"
                        :disabled="processing || isInvalid"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" />
                        Envoyer le lien de réinitialisation
                    </PrimaryButton>
                </div>
            </Form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>Ou retour à</span>
                <TextLink :href="login()">la connexion</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
