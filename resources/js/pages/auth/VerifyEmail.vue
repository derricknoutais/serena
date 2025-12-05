<script setup lang="ts">
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Vérifiez votre e-mail"
        description="Cliquez sur le lien que nous vous avons envoyé pour confirmer votre adresse."
    >
        <Head title="Vérification de l’e-mail" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            Un nouveau lien de vérification a été envoyé à l’adresse indiquée
            lors de votre inscription.
        </div>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <PrimaryButton type="submit" class="justify-center" :disabled="processing">
                <Spinner v-if="processing" />
                Renvoyer l’e-mail de vérification
            </PrimaryButton>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Se déconnecter
            </TextLink>
        </Form>
    </AuthLayout>
</template>
