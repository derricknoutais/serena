<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Types de chambres</h1>
                <p class="text-sm text-gray-500">Gestion des catégories de chambres.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                @click="openCreateModal"
            >
                Nouveau type
            </button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Capacité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tarif de base</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="item in roomTypes.data" :key="item.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ item.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ item.code || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ item.capacity_adults }} Ad., {{ item.capacity_children }} Enf.
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ item.base_price }}</td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <button class="text-indigo-600 hover:underline" @click="openEditModal(item)">Éditer</button>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(item.id)">Supprimer</button>
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
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeModal">Fermer</button>
                </div>

                <Form @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="name" rules="required" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Nom</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="code" rules="alpha_num_dash" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Code</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="code" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="capacity_adults" rules="required|numeric|min:1" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Capacité adultes</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    min="1"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="capacity_adults" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="capacity_children" rules="required|numeric|min:0" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Capacité enfants</label>
                                <input
                                    v-bind="field"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="capacity_children" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="base_price" rules="required|numeric|min:0" v-slot="{ field }">
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
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="closeModal">Annuler</button>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="submitting"
                        >
                            <span v-if="submitting">Enregistrement…</span>
                            <span v-else>Enregistrer</span>
                        </button>
                    </div>
                </Form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'RoomTypesIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage },
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
            form: {
                name: '',
                code: '',
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
    },
    methods: {
        openCreateModal() {
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.showModal = true;
        },
        openEditModal(item) {
            this.isEditing = true;
            this.editId = item.id;
            this.form = {
                name: item.name || '',
                code: item.code || '',
                capacity_adults: item.capacity_adults || 1,
                capacity_children: item.capacity_children || 0,
                base_price: item.base_price || 0,
                description: item.description || '',
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.form = {
                name: '',
                code: '',
                capacity_adults: 1,
                capacity_children: 0,
                base_price: 0,
                description: '',
            };
        },
        handleSubmit() {
            this.submitting = true;
            const url = this.isEditing ? `/ressources/room-types/${this.editId}` : '/ressources/room-types';

            if (this.isEditing) {
                router.put(url, this.form, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.closeModal();
                    },
                    onFinish: () => {
                        this.submitting = false;
                    },
                });
            } else {
                router.post(url, this.form, {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.closeModal();
                    },
                    onFinish: () => {
                        this.submitting = false;
                    },
                });
            }
        },
        destroy(id) {
            if (confirm('Supprimer ce type de chambre ?')) {
                router.delete(`/ressources/room-types/${id}`);
            }
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
