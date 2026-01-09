<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Catégories de produits</h1>
                <p class="text-sm text-gray-500">Organisez vos articles par catégories.</p>
            </div>
            <PrimaryButton
                v-if="canCreate"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouvelle catégorie
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="category in categories.data" :key="category.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ category.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="category.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ category.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <SecondaryButton
                                v-if="canUpdate"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(category)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canDelete"
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(category.id)"
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
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la catégorie.</p>
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
                                    @input="(e) => { field.onChange(e); form.name = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                            </div>
                        </Field>

                        <!-- champ code supprimé -->

                        <Field name="description" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    v-bind="field"
                                    rows="3"
                                    @input="(e) => { field.onChange(e); form.description = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                ></textarea>
                                <ErrorMessage name="description" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="is_active" type="checkbox" v-slot="{ field }">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 md:col-span-2">
                                <input
                                    v-bind="field"
                                    type="checkbox"
                                    :checked="form.is_active"
                                    @change="(e) => { field.onChange(e); form.is_active = e.target.checked; }"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Activer la catégorie
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
    name: 'ProductCategoriesIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        categories: {
            type: Object,
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
                description: '',
                is_active: true,
            },
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        canCreate() {
            return this.$page.props.auth?.can?.product_categories_create ?? false;
        },
        canUpdate() {
            return this.$page.props.auth?.can?.product_categories_update ?? false;
        },
        canDelete() {
            return this.$page.props.auth?.can?.product_categories_delete ?? false;
        },
    },
    created() {
        defineRule('required', (value) => {
            if (value === undefined || value === null || value === '') {
                return 'Ce champ est requis.';
            }
            return true;
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
        openEditModal(category) {
            if (!this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = true;
            this.editId = category.id;
            this.form = {
                name: category.name || '',
                description: category.description || '',
                is_active: !!category.is_active,
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
                name: '',
                description: '',
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
                name: this.form.name,
                code: this.form.code,
                description: this.form.description,
                is_active: this.form.is_active,
            };

            const url = this.isEditing ? `/settings/resources/product-categories/${this.editId}` : '/settings/resources/product-categories';
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
                title: 'Supprimer cette catégorie ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/settings/resources/product-categories/${id}`, { preserveScroll: true });
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
