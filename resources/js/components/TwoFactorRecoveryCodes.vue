<script setup lang="ts">
import AlertError from '@/components/AlertError.vue';
import Card from '@/components/Card.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
import { Form } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-vue-next';
import { nextTick, onMounted, ref, useTemplateRef } from 'vue';

const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
const isRecoveryCodesVisible = ref<boolean>(false);
const recoveryCodeSectionRef = useTemplateRef('recoveryCodeSectionRef');

const toggleRecoveryCodesVisibility = async () => {
    if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }

    isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;

    if (isRecoveryCodesVisible.value) {
        await nextTick();
        recoveryCodeSectionRef.value?.scrollIntoView({ behavior: 'smooth' });
    }
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }
});
</script>

<template>
    <Card class="w-full">
        <div class="space-y-1 border-b border-serena-border/60 pb-4">
            <h3 class="flex items-center gap-2 text-lg font-semibold text-serena-text-main">
                <LockKeyhole class="h-4 w-4" />Codes de récupération 2FA
            </h3>
            <p class="text-sm text-serena-text-muted">
                Ces codes vous permettent de récupérer l'accès si vous perdez votre appareil 2FA. Conservez-les dans un gestionnaire de mots de passe sécurisé.
            </p>
        </div>
        <div class="pt-4">
                <div
                    class="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between"
                >
                    <PrimaryButton @click="toggleRecoveryCodesVisibility" type="button" class="w-fit gap-2">
                        <component
                            :is="isRecoveryCodesVisible ? EyeOff : Eye"
                            class="h-4 w-4"
                        />
                        {{ isRecoveryCodesVisible ? 'Masquer' : 'Afficher' }} les codes de récupération
                    </PrimaryButton>

                    <Form
                        v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                        v-bind="regenerateRecoveryCodes.form()"
                        method="post"
                        :options="{ preserveScroll: true }"
                        @success="fetchRecoveryCodes"
                        #default="{ processing }"
                    >
                        <SecondaryButton
                            type="submit"
                            class="gap-2"
                            :disabled="processing"
                        >
                            <RefreshCw class="h-4 w-4" /> Régénérer les codes
                        </SecondaryButton>
                    </Form>
            </div>
            <div
                :class="[
                    'relative overflow-hidden transition-all duration-300',
                    isRecoveryCodesVisible
                        ? 'h-auto opacity-100'
                        : 'h-0 opacity-0',
                ]"
            >
                <div v-if="errors?.length" class="mt-6">
                    <AlertError :errors="errors" />
                </div>
                <div v-else class="mt-3 space-y-3">
                    <div
                        ref="recoveryCodeSectionRef"
                        class="grid gap-1 rounded-lg bg-serena-primary-soft p-4 font-mono text-sm"
                    >
                        <div v-if="!recoveryCodesList.length" class="space-y-2">
                            <div
                                v-for="n in 8"
                                :key="n"
                                class="h-4 animate-pulse rounded bg-serena-primary/10"
                            ></div>
                        </div>
                        <div
                            v-else
                            v-for="(code, index) in recoveryCodesList"
                            :key="index"
                        >
                            {{ code }}
                        </div>
                    </div>
                    <p class="text-xs text-serena-text-muted select-none">
                        Chaque code est utilisable une seule fois pour accéder à votre compte. S'ils sont épuisés, cliquez sur <span class="font-bold">Régénérer les codes</span> ci-dessus.
                    </p>
                </div>
            </div>
        </div>
    </Card>
</template>
