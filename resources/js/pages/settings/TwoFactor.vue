<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable, show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Authentification à deux facteurs',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Authentification à deux facteurs" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Authentification à deux facteurs"
                    description="Gérez vos paramètres d'authentification à deux facteurs"
                />

                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="destructive">Désactivée</Badge>

                    <p class="text-serena-text-muted">
                        Une fois l’authentification à deux facteurs activée, vous serez invité à saisir un code sécurisé pendant la connexion, récupérable depuis une application compatible TOTP sur votre téléphone.
                    </p>

                    <div>
                        <PrimaryButton
                            v-if="hasSetupData"
                            class="gap-2"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck class="h-4 w-4" />Continuer la configuration
                        </PrimaryButton>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <PrimaryButton
                                type="submit"
                                class="gap-2"
                                :disabled="processing"
                            >
                                <ShieldCheck class="h-4 w-4" />Activer la 2FA
                            </PrimaryButton>
                        </Form>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="default">Activée</Badge>

                    <p class="text-serena-text-muted">
                        Lorsque l’authentification à deux facteurs est activée, un code unique vous sera demandé à chaque connexion, disponible dans votre application d’authentification.
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <Form v-bind="disable.form()" #default="{ processing }">
                            <PrimaryButton
                                variant="danger"
                                class="gap-2"
                                type="submit"
                                :disabled="processing"
                            >
                                <ShieldBan class="h-4 w-4" />
                                Désactiver la 2FA
                            </PrimaryButton>
                        </Form>
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
