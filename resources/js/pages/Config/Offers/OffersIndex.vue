<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Offres</h1>
                <p class="text-sm text-gray-500">Offres et packages.</p>
            </div>
            <PrimaryButton type="button" class="px-4 py-2" @click="openCreateModal">
                Nouvelle offre
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Facturation</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="offer in offers.data" :key="offer.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ offer.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ offer.kind }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ offer.billing_mode }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="offer.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ offer.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <SecondaryButton
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(offer)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(offer.id)"
                            >
                                Supprimer
                            </PrimaryButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                Pagination à ajouter selon vos besoins.
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeModal"
        >
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier l’offre' : 'Nouvelle offre' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de l’offre.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Nom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Champ requis.
                                </p>
                                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                            </div>
                        </Field>

                        <!-- champ code supprimé -->

                        <Field name="kind" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Type <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.kind"
                                    @update:modelValue="(val) => { field.onChange(val); form.kind = val; }"
                                    :options="kindOptionsNormalized"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un type"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="kind" />
                                </p>
                                <p v-if="errors.kind" class="mt-1 text-xs text-red-600">{{ errors.kind }}</p>
                            </div>
                        </Field>

                        <Field name="billing_mode" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Mode de facturation <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.billing_mode"
                                    @update:modelValue="(val) => { field.onChange(val); form.billing_mode = val; }"
                                    :options="billingModeOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un mode"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="billing_mode" />
                                </p>
                                <p v-if="errors.billing_mode" class="mt-1 text-xs text-red-600">{{ errors.billing_mode }}</p>
                            </div>
                        </Field>

                        <Field name="fixed_duration_hours" rules="numeric|min:1" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Durée fixe (heures)</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="fixed_duration_hours" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Renseignez une durée en heures (optionnel).
                                </p>
                                <p v-if="errors.fixed_duration_hours" class="mt-1 text-xs text-red-600">
                                    {{ errors.fixed_duration_hours }}
                                </p>
                            </div>
                        </Field>

                        <Field name="check_in_from" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Arrivée possible à partir de</label>
                                <input
                                    v-bind="field"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="check_in_from" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="check_out_until" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Départ possible jusqu’à</label>
                                <input
                                    v-bind="field"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="check_out_until" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="valid_from" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Valide à partir du</label>
                                <input
                                    v-bind="field"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="valid_from" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="valid_to" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Valide jusqu’au</label>
                                <input
                                    v-bind="field"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="valid_to" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="valid_days_of_week" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Jours valides</label>
                                <Multiselect
                                    :model-value="field.value ?? form.valid_days_of_week"
                                    @update:modelValue="(val) => { field.onChange(val); form.valid_days_of_week = val; }"
                                    :options="dayOptionsNormalized"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner les jours"
                                    :multiple="true"
                                    :close-on-select="false"
                                    :allow-empty="true"
                                    class="mt-1"
                                />
                                <ErrorMessage name="valid_days_of_week" class="mt-1 text-xs text-red-600" />
                                <p v-if="errors.valid_days_of_week" class="mt-1 text-xs text-red-600">
                                    {{ errors.valid_days_of_week }}
                                </p>
                            </div>
                        </Field>

                        <Field name="description" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    v-bind="field"
                                    rows="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                ></textarea>
                                <ErrorMessage name="description" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="is_active" type="checkbox" v-slot="{ field }">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    v-bind="field"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Activer l’offre
                            </label>
                        </Field>
                    </div>

                    <div class="mt-6 rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <h3 class="mb-2 text-sm font-semibold text-gray-800">
                            Tarifs par type de chambre
                        </h3>
                        <p class="mb-3 text-xs text-gray-500">
                            Vous pouvez saisir un prix par type de chambre. Ces tarifs seront utilisés pour pré-remplir les réservations.
                        </p>
                        <div class="max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Type de chambre</th>
                                        <th class="px-3 py-2 text-right">Prix (XAF)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="rtPrice in roomTypePrices"
                                        :key="rtPrice.room_type_id"
                                        class="border-t text-gray-700"
                                    >
                                        <td class="px-3 py-1.5">
                                            {{ rtPrice.room_type_name }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right">
                                            <input
                                                v-model.number="rtPrice.price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-28 rounded-md border border-gray-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-100"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton type="button" class="text-sm" @click="closeModal">Annuler</SecondaryButton>
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
        </div>
    </ConfigLayout>
</template>

<script>
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'OffersIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        offers: {
            type: Object,
            required: true,
        },
        kindOptions: {
            type: Array,
            required: true,
        },
        billingModes: {
            type: Array,
            required: true,
        },
        dayOptions: {
            type: Array,
            required: true,
        },
        roomTypes: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
            isEditing: false,
            submitting: false,
            editId: null,
            formKey: 0,
            form: {
                name: '',
                kind: null,
                billing_mode: null,
                fixed_duration_hours: null,
                check_in_from: '',
                check_out_until: '',
                valid_from: '',
                valid_to: '',
                valid_days_of_week: [],
                is_active: true,
            },
            roomTypePrices: [],
        };
    },
    computed: {
        kindOptionsNormalized() {
            return this.kindOptions.map((k) => ({ label: k, value: k }));
        },
        billingModeOptions() {
            return this.billingModes.map((k) => ({ label: k, value: k }));
        },
        dayOptionsNormalized() {
            return this.dayOptions.map((k) => ({ label: k, value: k }));
        },
        errors() {
            return this.$page.props.errors || {};
        },
    },
    methods: {
        openCreateModal() {
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.initializeRoomTypePrices();
            this.formKey += 1;
            this.showModal = true;
        },
        openEditModal(offer) {
            this.isEditing = true;
            this.editId = offer.id;
            this.form = {
                name: offer.name || '',
                kind: this.kindOptionsNormalized.find((opt) => opt.value === offer.kind) ?? null,
                billing_mode: this.billingModeOptions.find((opt) => opt.value === offer.billing_mode) ?? null,
                fixed_duration_hours: offer.fixed_duration_hours || null,
                check_in_from: offer.check_in_from || '',
                check_out_until: offer.check_out_until || '',
                valid_from: offer.valid_from || '',
                valid_to: offer.valid_to || '',
                valid_days_of_week: Array.isArray(offer.valid_days_of_week)
                    ? offer.valid_days_of_week
                        .map((d) => this.dayOptionsNormalized.find((opt) => opt.value === d) ?? null)
                        .filter(Boolean)
                    : [],
                is_active: !!offer.is_active,
            };
            this.initializeRoomTypePrices(offer);
            this.formKey += 1;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.form = {
                name: '',
                kind: null,
                billing_mode: null,
                fixed_duration_hours: null,
                check_in_from: '',
                check_out_until: '',
                valid_from: '',
                valid_to: '',
                valid_days_of_week: [],
                is_active: true,
            };
            this.roomTypePrices = [];
        },
        initializeRoomTypePrices(offer = null) {
            const pricesByRoomType = {};

            if (offer && Array.isArray(offer.prices)) {
                offer.prices.forEach((p) => {
                    pricesByRoomType[p.room_type_id] = Number(p.price || 0);
                });
            }

            this.roomTypePrices = (this.roomTypes || []).map((rt) => ({
                room_type_id: rt.id,
                room_type_name: rt.name,
                price: Object.prototype.hasOwnProperty.call(pricesByRoomType, rt.id)
                    ? pricesByRoomType[rt.id]
                    : '',
            }));
        },
        handleSubmit(values) {
            this.submitting = true;
            const payload = {
                ...values,
                kind: values.kind?.value ?? values.kind,
                billing_mode: values.billing_mode?.value ?? values.billing_mode,
                fixed_duration_hours: values.fixed_duration_hours ? Number(values.fixed_duration_hours) : null,
                check_in_from: values.check_in_from || null,
                check_out_until: values.check_out_until || null,
                valid_from: values.valid_from || null,
                valid_to: values.valid_to || null,
                valid_days_of_week: Array.isArray(values.valid_days_of_week)
                    ? values.valid_days_of_week.map((d) => d?.value ?? d)
                    : [],
                is_active: !!values.is_active,
                prices: this.isEditing
                    ? undefined
                    : this.roomTypePrices
                        .filter((p) => p.price !== '' && !Number.isNaN(Number(p.price)))
                        .map((p) => ({
                            room_type_id: p.room_type_id,
                            price: Number(p.price),
                        })),
            };
            const url = this.isEditing ? `/ressources/offers/${this.editId}` : '/ressources/offers';

            if (this.isEditing) {
                router.put(
                    url,
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.closeModal();
                        },
                        onError: () => {
                            this.submitting = false;
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            } else {
                router.post(
                    url,
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.closeModal();
                        },
                        onError: () => {
                            this.submitting = false;
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            }
        },
        destroy(id) {
            Swal.fire({
                title: 'Supprimer cette offre ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/offers/${id}`, { preserveScroll: true });
                }
            });
        },
    },
};

defineRule('required', (value) => {
    if (value === undefined || value === null || value === '') {
        return 'Ce champ est requis.';
    }
    return true;
});

defineRule('alpha_num_dash', (value) => {
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
