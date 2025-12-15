<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Produits (Bar / Restau)</h1>
                <p class="text-sm text-gray-500">Gestion des articles vendus.</p>
            </div>
            <PrimaryButton
                v-if="canCreate"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouveau produit
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Catégorie</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Prix</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Compte</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actif</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ product.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.category || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.unit_price }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ product.account_code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="product.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ product.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <SecondaryButton
                                v-if="canUpdate"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(product)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canDelete"
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(product.id)"
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
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations du produit.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="product_category_id" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Catégorie <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.product_category_id"
                                    @update:modelValue="(val) => { field.onChange(val); form.product_category_id = val; }"
                                    :options="categoryOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner une catégorie"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="product_category_id" />
                                </p>
                                <p v-if="errors.product_category_id" class="mt-1 text-xs text-red-600">{{ errors.product_category_id }}</p>
                            </div>
                        </Field>

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

                        <Field name="sku" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">SKU</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.sku = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="sku" class="mt-1 text-xs text-red-600" />
                                <p v-if="errors.sku" class="mt-1 text-xs text-red-600">{{ errors.sku }}</p>
                            </div>
                        </Field>

                        <Field name="unit_price" rules="required|numeric|min:0" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Prix unitaire <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    @input="(e) => { field.onChange(e); form.unit_price = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="unit_price" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Renseignez un prix valide.</p>
                                <p v-if="errors.unit_price" class="mt-1 text-xs text-red-600">{{ errors.unit_price }}</p>
                            </div>
                        </Field>

                        <Field name="tax_id" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Taxe</label>
                                <Multiselect
                                    :model-value="field.value ?? form.tax_id"
                                    @update:modelValue="(val) => { field.onChange(val); form.tax_id = val; }"
                                    :options="taxOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner une taxe (optionnel)"
                                    :allow-empty="true"
                                    class="mt-1"
                                />
                                <ErrorMessage name="tax_id" class="mt-1 text-xs text-red-600" />
                                <p v-if="errors.tax_id" class="mt-1 text-xs text-red-600">{{ errors.tax_id }}</p>
                            </div>
                        </Field>

                        <Field name="account_code" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Code comptable <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.account_code = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="account_code" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.account_code" class="mt-1 text-xs text-red-600">{{ errors.account_code }}</p>
                            </div>
                        </Field>

                        <Field name="is_active" type="checkbox" v-slot="{ field }">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input
                                    v-bind="field"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    :checked="form.is_active"
                                    @change="(e) => { field.onChange(e); form.is_active = e.target.checked; }"
                                />
                                Activer le produit
                            </label>
                        </Field>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton type="button" class="text-sm" @click="closeModal">Annuler</SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            class="px-4 py-2 text-sm"
                            :disabled="submitting"
                        >
                            <span v-if="submitting">Enregistrement…</span>
                            <span v-else>{{ isEditing ? 'Mettre à jour' : 'Enregistrer' }}</span>
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
    name: 'ProductsIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        products: {
            type: Object,
            required: true,
        },
        categories: {
            type: Array,
            required: true,
        },
        taxes: {
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
                product_category_id: null,
                name: '',
                sku: '',
                unit_price: '',
                tax_id: null,
                account_code: '',
                is_active: true,
            },
        };
    },
    computed: {
        categoryOptions() {
            return this.categories.map((c) => ({ label: c.name, value: c.id }));
        },
        taxOptions() {
            return this.taxes.map((t) => ({ label: t.name, value: t.id }));
        },
        errors() {
            return this.$page.props.errors || {};
        },
        canCreate() {
            return this.$page.props.auth?.can?.products_create ?? false;
        },
        canUpdate() {
            return this.$page.props.auth?.can?.products_update ?? false;
        },
        canDelete() {
            return this.$page.props.auth?.can?.products_delete ?? false;
        },
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
            return !Number.isNaN(Number(value)) || 'Veuillez saisir un nombre.';
        });

        defineRule('min', (value, [limit]) => {
            if (value === undefined || value === null || value === '') {
                return true;
            }
            return Number(value) >= Number(limit) || `Valeur minimale ${limit}.`;
        });

        configure({
            validateOnInput: true,
        });
    },
    methods: {
        openCreateModal() {
            if (!this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.formKey += 1;
            this.showModal = true;
        },
        openEditModal(product) {
            if (!this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = true;
            this.editId = product.id;
            this.form = {
                product_category_id: this.categoryOptions.find((c) => c.value === product.product_category_id) ?? null,
                name: product.name || '',
                sku: product.sku || '',
                unit_price: product.unit_price || '',
                tax_id: product.tax_id ? this.taxOptions.find((t) => t.value === product.tax_id) ?? null : null,
                account_code: product.account_code || '',
                is_active: !!product.is_active,
            };
            this.formKey += 1;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.form = {
                product_category_id: null,
                name: '',
                sku: '',
                unit_price: '',
                tax_id: null,
                account_code: '',
                is_active: true,
            };
        },
        handleSubmit() {
            if (!this.isEditing && !this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (this.isEditing && !this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.submitting = true;
            const payload = {
                product_category_id: this.form.product_category_id?.value ?? this.form.product_category_id,
                name: this.form.name,
                sku: this.form.sku,
                unit_price: this.form.unit_price,
                tax_id: this.form.tax_id ? this.form.tax_id?.value ?? this.form.tax_id : null,
                account_code: this.form.account_code,
                is_active: this.form.is_active,
            };
            const url = this.isEditing ? `/ressources/products/${this.editId}` : '/ressources/products';
            const method = this.isEditing ? 'put' : 'post';

            router[method](url, payload, {
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
            });
        },
        destroy(id) {
            if (!this.canDelete) {
                this.showUnauthorizedAlert();

                return;
            }
            Swal.fire({
                title: 'Supprimer ce produit ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/products/${id}`, { preserveScroll: true });
                }
            });
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
    },
};
</script>
