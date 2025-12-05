<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-password';
import { Form, Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Paramètres du mot de passe',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Paramètres du mot de passe" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Modifier le mot de passe"
                    description="Assurez-vous que votre compte utilise un mot de passe long et aléatoire pour rester sécurisé"
                />

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <TextInput
                            id="current_password"
                            name="current_password"
                            label="Mot de passe actuel"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Mot de passe actuel"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <TextInput
                            id="password"
                            name="password"
                            label="Nouveau mot de passe"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Nouveau mot de passe"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <TextInput
                            id="password_confirmation"
                            name="password_confirmation"
                            label="Confirmer le mot de passe"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Confirmer le mot de passe"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton
                            :disabled="processing"
                            data-test="update-password-button"
                            >Enregistrer le mot de passe</PrimaryButton
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-serena-text-muted"
                            >
                                Enregistré.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
