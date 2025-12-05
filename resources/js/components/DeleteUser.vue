<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall
            title="Supprimer le compte"
            description="Supprimez votre compte et toutes ses ressources"
        />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4">
            <div class="relative space-y-0.5 text-red-600">
                <p class="font-medium">Attention</p>
                <p class="text-sm">
                    Cette action est irréversible. Pensez à sauvegarder vos données avant de continuer.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <PrimaryButton variant="danger" data-test="delete-user-button">
                        Supprimer le compte
                    </PrimaryButton>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.$el?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                Souhaitez-vous vraiment supprimer ce compte ?
                            </DialogTitle>
                            <DialogDescription>
                                Une fois supprimé, toutes les données associées seront définitivement perdues. Confirmez avec votre mot de passe pour continuer.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="Mot de passe"
                            >
                                <template #label>
                                    <span class="sr-only">Mot de passe</span>
                                </template>
                            </TextInput>
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <SecondaryButton
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Annuler
                                </SecondaryButton>
                            </DialogClose>

                            <PrimaryButton
                                type="submit"
                                variant="danger"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                Supprimer le compte
                            </PrimaryButton>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
