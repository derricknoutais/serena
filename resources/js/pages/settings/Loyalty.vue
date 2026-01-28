<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import Card from '@/components/Card.vue';
import { update as updateLoyaltySettings } from '@/actions/App/Http/Controllers/Settings/LoyaltyController';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    hotel: { id: number; name: string; currency: string };
    settings: {
        enabled: boolean;
        earning_mode: 'amount' | 'nights' | 'fixed';
        points_per_amount: number | null;
        amount_base: string | number | null;
        points_per_night: number | null;
        fixed_points: number | null;
        max_points_per_stay: number | null;
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Programme de fidélité',
        href: '/settings/loyalty',
    },
];

const form = useForm({
    enabled: props.settings.enabled ?? false,
    earning_mode: props.settings.earning_mode ?? 'amount',
    points_per_amount: props.settings.points_per_amount ?? null,
    amount_base: props.settings.amount_base ?? null,
    points_per_night: props.settings.points_per_night ?? null,
    fixed_points: props.settings.fixed_points ?? null,
    max_points_per_stay: props.settings.max_points_per_stay ?? null,
});

const showAmountFields = computed(() => form.earning_mode === 'amount');
const showNightFields = computed(() => form.earning_mode === 'nights');
const showFixedFields = computed(() => form.earning_mode === 'fixed');

const submit = () => {
    form.put(updateLoyaltySettings().url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Programme de fidélité" />

        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <HeadingSmall
                    title="Programme de fidélité"
                    description="Configurez les règles de gains de points pour l'hôtel actif."
                />

                <Card class="space-y-6">
                    <div class="flex flex-col gap-2 text-sm text-serena-text-muted">
                        <span class="font-medium text-serena-text-main">{{ hotel.name }}</span>
                        <span>Les points sont attribués à l’enregistrement des paiements.</span>
                    </div>

                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="flex items-center gap-3">
                            <input
                                id="loyalty_enabled"
                                v-model="form.enabled"
                                type="checkbox"
                                class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                            />
                            <label for="loyalty_enabled" class="text-sm font-medium text-serena-text-main">
                                Activer le programme de fidélité
                            </label>
                        </div>
                        <InputError :message="form.errors.enabled" />

                        <div class="grid gap-3">
                            <label for="earning_mode" class="text-sm font-medium text-serena-text-main">
                                Mode de gain
                            </label>
                            <select
                                id="earning_mode"
                                v-model="form.earning_mode"
                                class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option value="amount">Montant dépensé</option>
                                <option value="nights">Nuits séjournées</option>
                                <option value="fixed">Points fixes par séjour</option>
                            </select>
                            <InputError :message="form.errors.earning_mode" />
                        </div>

                        <div v-if="showAmountFields" class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <TextInput
                                    id="points_per_amount"
                                    v-model="form.points_per_amount"
                                    type="number"
                                    label="Points accordés"
                                    min="1"
                                    name="points_per_amount"
                                    placeholder="Ex: 1"
                                />
                                <InputError :message="form.errors.points_per_amount" />
                            </div>
                            <div class="grid gap-2">
                                <TextInput
                                    id="amount_base"
                                    v-model="form.amount_base"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :label="`Montant de base (${hotel.currency})`"
                                    name="amount_base"
                                    placeholder="Ex: 1000"
                                />
                                <InputError :message="form.errors.amount_base" />
                            </div>
                        </div>

                        <div v-if="showNightFields" class="grid gap-2">
                            <TextInput
                                id="points_per_night"
                                v-model="form.points_per_night"
                                type="number"
                                label="Points par nuit"
                                min="1"
                                name="points_per_night"
                                placeholder="Ex: 10"
                            />
                            <InputError :message="form.errors.points_per_night" />
                        </div>

                        <div v-if="showFixedFields" class="grid gap-2">
                            <TextInput
                                id="fixed_points"
                                v-model="form.fixed_points"
                                type="number"
                                label="Points fixes par séjour"
                                min="1"
                                name="fixed_points"
                                placeholder="Ex: 50"
                            />
                            <InputError :message="form.errors.fixed_points" />
                        </div>

                        <div class="grid gap-2">
                            <TextInput
                                id="max_points_per_stay"
                                v-model="form.max_points_per_stay"
                                type="number"
                                label="Plafond de points par séjour (optionnel)"
                                min="1"
                                name="max_points_per_stay"
                                placeholder="Ex: 300"
                            />
                            <InputError :message="form.errors.max_points_per_stay" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing" type="submit">Enregistrer</PrimaryButton>
                            <p v-if="form.recentlySuccessful" class="text-sm text-serena-text-muted">Enregistré.</p>
                        </div>
                    </form>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
