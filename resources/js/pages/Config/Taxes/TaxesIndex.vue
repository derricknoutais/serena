<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Taxes</h1>
                <p class="text-sm text-gray-500">Paramétrage des taxes.</p>
            </div>
            <PrimaryButton type="button" class="px-4 py-2" @click="openModal">
                Nouvelle taxe
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Taux</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="tax in taxes.data" :key="tax.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ tax.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ tax.rate }}%</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ tax.type }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="tax.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ tax.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <SecondaryButton
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openModal(tax)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(tax.id)"
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
                        <h2 class="text-lg font-semibold">{{ editingTaxId ? 'Modifier la taxe' : 'Créer une taxe' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la taxe.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="submit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Nom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.name = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                            </div>
                        </Field>

                        <!-- champ code supprimé -->

                        <Field name="rate" rules="required|numeric|min:0" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Taux (%) <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    @input="(e) => { field.onChange(e); form.rate = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="rate" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Renseignez un taux.</p>
                                <p v-if="errors.rate" class="mt-1 text-xs text-red-600">{{ errors.rate }}</p>
                            </div>
                        </Field>

                        <Field name="type" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Type <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.type"
                                    @update:modelValue="(val) => { field.onChange(val); form.type = val; }"
                                    :options="typeOptions"
                                    :close-on-select="true"
                                    :allow-empty="false"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un type"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="type" />
                                </p>
                                <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
                            </div>
                        </Field>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_city_tax"
                                v-model="form.is_city_tax"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="is_city_tax" class="text-sm font-medium text-gray-700">Taxe de séjour</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="is_active" class="text-sm font-medium text-gray-700">Activer la taxe</label>
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
                            <span v-else>{{ editingTaxId ? 'Mettre à jour' : 'Enregistrer' }}</span>
                        </PrimaryButton>
                    </div>
                </Form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import Swal from 'sweetalert2';
import { Link, router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'TaxesIndex',
    components: { ConfigLayout, Link, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        taxes: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
            submitting: false,
            formKey: 0,
            editingTaxId: null,
            form: {
                name: '',
                rate: '',
                type: null,
                is_city_tax: false,
                is_active: true,
            },
            typeOptions: [
                { label: 'Pourcentage', value: 'percentage' },
                { label: 'Montant fixe', value: 'fixed' },
            ],
        };
    },
    created() {
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
            return !Number.isNaN(Number(value)) || 'Valeur numérique requise.';
        });

        defineRule('min', (value, [limit]) => {
            if (value === undefined || value === null || value === '') {
                return true;
            }
            return Number(value) >= Number(limit) || `La valeur doit être supérieure ou égale à ${limit}.`;
        });
        configure({
            validateOnInput: true,
        });
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
    },
    methods: {
        openModal(tax = null) {
            if (tax) {
                this.editingTaxId = tax.id;
                this.form = {
                    name: tax.name ?? '',
                    rate: tax.rate ?? '',
                    type: this.typeOptions.find((opt) => opt.value === tax.type) ?? null,
                    is_city_tax: Boolean(tax.is_city_tax),
                    is_active: Boolean(tax.is_active),
                };
            } else {
                this.resetForm();
            }

            this.formKey += 1;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.editingTaxId = null;
            this.form = {
                name: '',
                rate: '',
                type: null,
                is_city_tax: false,
                is_active: true,
            };
            this.formKey += 1;
        },
        submit() {
            this.submitting = true;
            const payload = {
                name: this.form.name,
                rate: this.form.rate,
                type: this.form.type ? String(this.form.type.value ?? this.form.type) : '',
                is_city_tax: this.form.is_city_tax,
                is_active: this.form.is_active,
            };

            const method = this.editingTaxId ? 'put' : 'post';
            const url = this.editingTaxId ? `/ressources/taxes/${this.editingTaxId}` : '/ressources/taxes';

            router[method](url, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    this.closeModal();
                },
                onFinish: () => {
                    this.submitting = false;
                },
            });
        },
        destroy(id) {
            Swal.fire({
                title: 'Supprimer cette taxe ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/taxes/${id}`, { preserveScroll: true });
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
