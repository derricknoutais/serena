<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
const password = ref('');
const passwordConfirmation = ref('');
const localErrors = reactive({
    password: 'Le mot de passe doit contenir au moins 8 caracteres.',
    password_confirmation: 'Les mots de passe doivent correspondre.',
});

watch([password, passwordConfirmation], () => {
    localErrors.password = password.value.length >= 8 ? '' : 'Le mot de passe doit contenir au moins 8 caracteres.';
    localErrors.password_confirmation =
        passwordConfirmation.value === password.value ? '' : 'Les mots de passe doivent correspondre.';
}, { immediate: true });

const isInvalid = computed(() => Object.values(localErrors).some((message) => message !== ''));
</script>

<template>
    <AuthLayout
        title="Réinitialiser le mot de passe"
        description="Renseignez votre nouveau mot de passe"
    >
        <Head title="Réinitialiser le mot de passe" />

        <Form
            v-bind="update.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-model="inputEmail"
                        class="mt-1 block w-full"
                        readonly
                    />
                    <InputError :message="errors.email" class="mt-2" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nouveau mot de passe</Label>
                    <Input
                        id="password"
                        v-model="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        autofocus
                        placeholder="Au moins 8 caractères"
                    />
                    <InputError :message="errors.password || localErrors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        Confirmer le mot de passe
                    </Label>
                    <Input
                        id="password_confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        placeholder="Répétez le mot de passe"
                    />
                    <InputError :message="errors.password_confirmation || localErrors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="processing || isInvalid"
                    data-test="reset-password-button"
                >
                    <Spinner v-if="processing" />
                    Réinitialiser le mot de passe
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
