<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Types de chambres</h1>
                <p class="text-sm text-gray-500">Gestion des catégories de chambres.</p>
            </div>
            <PrimaryButton type="button" class="px-4 py-2" @click="openCreateModal">
                Nouveau type
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Capacité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tarif de base</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="item in roomTypes.data"
                        :key="item.id"
                        class="cursor-pointer hover:bg-gray-50"
                        @click="goToShow(item.id)"
                    >
                        <td class="px-4 py-3 text-sm text-gray-800">{{ item.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ item.capacity_adults }} Ad., {{ item.capacity_children }} Enf.
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ item.base_price }}</td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <SecondaryButton
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click.stop="openEditModal(item)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click.stop="destroy(item.id)"
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
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier le type' : 'Nouveau type de chambre' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations principales.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Nom</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Champ requis.
                                </p>
                                <p v-if="backendErrors.name" class="mt-1 text-xs text-red-600">{{ backendErrors.name }}</p>
                            </div>
                        </Field>

                        <Field name="capacity_adults" rules="required|numeric|min:1" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Capacité adultes</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    min="1"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="capacity_adults" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Renseignez un nombre (min 1).
                                </p>
                                <p v-if="backendErrors.capacity_adults" class="mt-1 text-xs text-red-600">
                                    {{ backendErrors.capacity_adults }}
                                </p>
                            </div>
                        </Field>

                        <Field name="capacity_children" rules="required|numeric|min:0" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Capacité enfants</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="capacity_children" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Renseignez un nombre (min 0).
                                </p>
                                <p v-if="backendErrors.capacity_children" class="mt-1 text-xs text-red-600">
                                    {{ backendErrors.capacity_children }}
                                </p>
                            </div>
                        </Field>

                        <Field name="base_price" rules="required|numeric|min:0" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Tarif de base</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="base_price" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Renseignez un montant valide.
                                </p>
                                <p v-if="backendErrors.base_price" class="mt-1 text-xs text-red-600">
                                    {{ backendErrors.base_price }}
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
    name: 'RoomTypesIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        roomTypes: {
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
                capacity_adults: 1,
                capacity_children: 0,
                base_price: 0,
                description: '',
            },
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        backendErrors() {
            return this.errors;
        },
    },
    methods: {
        openCreateModal() {
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.formKey += 1;
            this.showModal = true;
        },
        goToShow(id) {
            router.visit(`/ressources/room-types/${id}`);
        },
        openEditModal(item) {
            this.isEditing = true;
            this.editId = item.id;
            this.form = {
                name: item.name || '',
                capacity_adults: item.capacity_adults || 1,
                capacity_children: item.capacity_children || 0,
                base_price: item.base_price || 0,
                description: item.description || '',
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
                capacity_adults: 1,
                capacity_children: 0,
                base_price: 0,
                description: '',
            };
        },
        handleSubmit(values) {
            this.submitting = true;
            const url = this.isEditing ? `/ressources/room-types/${this.editId}` : '/ressources/room-types';

            if (this.isEditing) {
                router.put(url, values, {
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
            } else {
                router.post(url, values, {
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
            }
        },
        destroy(id) {
            Swal.fire({
                title: 'Supprimer ce type de chambre ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/room-types/${id}`, { preserveScroll: true });
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

defineRule('alpha_num_dash', (value) => {
    if (!value) {
        return true;
    }
    return /^[A-Za-z0-9-_]+$/.test(value) || 'Utilisez uniquement lettres, chiffres, tirets.';
});

configure({
    validateOnBlur: true,
    validateOnChange: true,
    validateOnInput: true,
    validateOnModelUpdate: true,
});
</script>
