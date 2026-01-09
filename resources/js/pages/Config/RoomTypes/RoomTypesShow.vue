<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Type de chambre</h1>
                <p class="text-sm text-gray-500">Configurer les tarifs par offre pour ce type de chambre.</p>
            </div>
            <Link href="/settings/resources/room-types" class="text-sm text-indigo-600 hover:underline">Retour</Link>
        </div>

        <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-gray-500">Nom</p>
                    <p class="font-semibold text-gray-800">{{ roomType.name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Code</p>
                    <p class="font-semibold text-gray-800">{{ roomType.code || '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Capacité</p>
                    <p class="font-semibold text-gray-800">{{ roomType.capacity_adults }} Ad., {{ roomType.capacity_children }} Enf.</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Tarif de base</p>
                    <p class="font-semibold text-gray-800">{{ roomType.base_price }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">Description</p>
                    <p class="font-semibold text-gray-800">{{ roomType.description || '—' }}</p>
                </div>
            </div>
        </div>

        <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Ajouter / mettre à jour un tarif d’offre</h2>
                    <p class="text-sm text-gray-500">Associer une offre et définir son tarif pour ce type de chambre.</p>
                </div>
            </div>

            <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <Field name="offer_id" rules="required" v-slot="{ field, meta }">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Offre <span class="text-red-600">*</span></label>
                            <Multiselect
                                :model-value="field.value ?? form.offer_id"
                                @update:modelValue="(val) => { field.onChange(val); form.offer_id = val; }"
                                :options="offerOptions"
                                label="label"
                                track-by="value"
                                placeholder="Sélectionner une offre"
                                :allow-empty="false"
                                class="mt-1"
                            />
                            <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                <ErrorMessage name="offer_id" />
                            </p>
                            <p v-if="errors.offer_id" class="mt-1 text-xs text-red-600">{{ errors.offer_id }}</p>
                        </div>
                    </Field>

                    <Field name="price" rules="required|numeric|min:0" v-slot="{ field, meta }">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Tarif <span class="text-red-600">*</span></label>
                            <input
                                v-bind="field"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <ErrorMessage name="price" class="mt-1 text-xs text-red-600" />
                            <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Renseignez un montant.</p>
                            <p v-if="errors.price" class="mt-1 text-xs text-red-600">{{ errors.price }}</p>
                        </div>
                    </Field>

                    <Field name="extra_adult_price" rules="numeric|min:0" v-slot="{ field }">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Supplément adulte</label>
                            <input
                                v-bind="field"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <ErrorMessage name="extra_adult_price" class="mt-1 text-xs text-red-600" />
                        </div>
                    </Field>

                    <Field name="extra_child_price" rules="numeric|min:0" v-slot="{ field }">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Supplément enfant</label>
                            <input
                                v-bind="field"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <ErrorMessage name="extra_child_price" class="mt-1 text-xs text-red-600" />
                        </div>
                    </Field>

                    <Field name="is_active" type="checkbox" v-slot="{ field }">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input
                                v-bind="field"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Activer le tarif
                        </label>
                    </Field>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <SecondaryButton type="button" class="text-sm" @click="resetForm">
                        Réinitialiser
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        class="px-4 py-2 text-sm"
                        :disabled="submitting"
                    >
                        <span v-if="submitting">Enregistrement…</span>
                        <span v-else>Enregistrer</span>
                    </PrimaryButton>
                </div>
            </Form>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">Tarifs existants</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Offre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tarif</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Suppl. Adulte</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Suppl. Enfant</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="price in prices" :key="price.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ price.offer_name || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ price.price }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ price.extra_adult_price ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ price.extra_child_price ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="price.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ price.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'RoomTypesShow',
    components: { ConfigLayout, Link, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        roomType: {
            type: Object,
            required: true,
        },
        offers: {
            type: Array,
            required: true,
        },
        prices: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            formKey: 0,
            submitting: false,
            form: {
                offer_id: null,
                price: '',
                extra_adult_price: '',
                extra_child_price: '',
                is_active: true,
            },
        };
    },
    computed: {
        offerOptions() {
            return this.offers.map((offer) => ({
                label: `${offer.name} (${offer.code})`,
                value: offer.id,
            }));
        },
        errors() {
            return this.$page.props.errors || {};
        },
    },
    methods: {
        resetForm() {
            this.form = {
                offer_id: null,
                price: '',
                extra_adult_price: '',
                extra_child_price: '',
                is_active: true,
            };
            this.formKey += 1;
        },
        handleSubmit(values) {
            this.submitting = true;
            const payload = {
                ...values,
                offer_id: values.offer_id?.value ?? values.offer_id,
                price: values.price,
                extra_adult_price: values.extra_adult_price || null,
                extra_child_price: values.extra_child_price || null,
                is_active: !!values.is_active,
            };

            router.post(
                `/settings/resources/room-types/${this.roomType.id}/prices`,
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.resetForm();
                    },
                    onError: () => {
                        this.submitting = false;
                    },
                    onFinish: () => {
                        this.submitting = false;
                    },
                },
            );
        },
    },
};

defineRule('required', (value) => {
    if (value === undefined || value === null || value === '') {
        return 'Ce champ est requis.';
    }
    return true;
});

defineRule('numeric', (value) => {
    if (value === undefined || value === null || value === '') {
        return true;
    }
    return !Number.isNaN(Number(value)) || 'Veuillez saisir un nombre.';
});

defineRule('min', (value, [limit]) => {
    if (value === undefined || value === null || value === '') {
        return true;
    }
    return Number(value) >= Number(limit) || `Valeur minimale ${limit}.`;
});

configure({
    validateOnBlur: true,
    validateOnChange: true,
    validateOnInput: true,
    validateOnModelUpdate: true,
});
</script>
