<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const passwordValue = ref('');
const localErrors = reactive({
    password: 'Mot de passe requis.',
});

watch(passwordValue, () => {
    localErrors.password = passwordValue.value ? '' : 'Mot de passe requis.';
}, { immediate: true });

const isInvalid = computed(() => localErrors.password !== '');
</script>

<template>
    <AuthLayout
        title="Confirmez votre mot de passe"
        description="Espace sécurisé : merci de confirmer votre mot de passe avant de continuer."
    >
        <Head title="Confirmation du mot de passe" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label htmlFor="password">Mot de passe</Label>
                    <Input
                        id="password"
                        v-model="passwordValue"
                        type="password"
                        name="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="errors.password || localErrors.password" />
                </div>

                <div class="flex items-center">
                    <Button
                        class="w-full"
                        :disabled="processing || isInvalid"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" />
                        Confirmer
                    </Button>
                </div>
            </div>
        </Form>
    </AuthLayout>
</template>
