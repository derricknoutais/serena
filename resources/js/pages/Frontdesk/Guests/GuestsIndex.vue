<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Clients</h1>
                <p class="text-sm text-gray-500">Base client commune au tenant.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                @click="openCreateModal"
            >
                Nouveau client
            </button>
        </div>

        <div class="mb-4 flex gap-3">
            <input
                v-model="localFilters.search"
                type="text"
                placeholder="Rechercher (nom, email, téléphone)"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                @keyup.enter="applyFilters"
            />
            <button
                type="button"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                @click="applyFilters"
            >
                Rechercher
            </button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Téléphone</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="guest in guests.data" :key="guest.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ guest.full_name || `${guest.first_name} ${guest.last_name}` }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ guest.email || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ guest.phone || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <button class="text-indigo-600 hover:underline" @click="openEditModal(guest)">Éditer</button>
                            <button class="text-red-600 hover:underline" @click="destroy(guest.id)">Supprimer</button>
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
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier le client' : 'Nouveau client' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations principales.</p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeModal">Fermer</button>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="first_name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Prénom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.first_name = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="first_name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.first_name" class="mt-1 text-xs text-red-600">{{ errors.first_name }}</p>
                            </div>
                        </Field>

                        <Field name="last_name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Nom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.last_name = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="last_name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.last_name" class="mt-1 text-xs text-red-600">{{ errors.last_name }}</p>
                            </div>
                        </Field>

                        <Field name="email" rules="email" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Email</label>
                                <input
                                    v-bind="field"
                                    type="email"
                                    @input="(e) => { field.onChange(e); form.email = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="email" class="mt-1 text-xs text-red-600" />
                                <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                            </div>
                        </Field>

                        <Field name="phone" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Téléphone</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.phone = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="phone" class="mt-1 text-xs text-red-600" />
                                <p v-if="errors.phone" class="mt-1 text-xs text-red-600">{{ errors.phone }}</p>
                            </div>
                        </Field>

                        <Field name="document_type" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Type de document</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.document_type = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="document_type" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="document_number" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Numéro de document</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.document_number = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="document_number" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="address" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Adresse</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.address = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="address" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="city" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Ville</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.city = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="city" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="country" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Pays</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.country = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="country" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="notes" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Notes</label>
                                <textarea
                                    v-bind="field"
                                    rows="3"
                                    @input="(e) => { field.onChange(e); form.notes = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                ></textarea>
                                <ErrorMessage name="notes" class="mt-1 text-xs text-red-600" />
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
                            <span v-else>{{ isEditing ? 'Mettre à jour' : 'Enregistrer' }}</span>
                        </button>
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

export default {
    name: 'GuestsIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage },
    props: {
        guests: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            localFilters: {
                search: this.filters.search || '',
            },
            searchDebounce: null,
            showModal: false,
            isEditing: false,
            submitting: false,
            editId: null,
            formKey: 0,
            form: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                document_type: '',
                document_number: '',
                address: '',
                city: '',
                country: '',
                notes: '',
            },
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
    },
    watch: {
        'localFilters.search'(value) {
            if (this.searchDebounce) {
                clearTimeout(this.searchDebounce);
            }
            this.searchDebounce = setTimeout(() => {
                this.applyFilters(value);
            }, 300);
        },
    },
    created() {
        defineRule('required', (value) => {
            if (value === undefined || value === null || value === '') {
                return 'Ce champ est requis.';
            }
            return true;
        });

        defineRule('email', (value) => {
            if (!value) {
                return true;
            }
            const regex = /^[\w.-]+@([\w-]+\.)+[\w-]{2,4}$/;
            return regex.test(value) || 'Adresse email invalide.';
        });

        configure({
            validateOnInput: true,
        });
    },
    methods: {
        applyFilters(value = this.localFilters.search) {
            router.visit('/guests', {
                method: 'get',
                data: { search: value },
                preserveState: true,
                preserveScroll: true,
            });
        },
        openCreateModal() {
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.formKey += 1;
            this.showModal = true;
        },
        openEditModal(guest) {
            this.isEditing = true;
            this.editId = guest.id;
            this.form = {
                first_name: guest.first_name || '',
                last_name: guest.last_name || '',
                email: guest.email || '',
                phone: guest.phone || '',
                document_type: guest.document_type || '',
                document_number: guest.document_number || '',
                address: guest.address || '',
                city: guest.city || '',
                country: guest.country || '',
                notes: guest.notes || '',
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
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                document_type: '',
                document_number: '',
                address: '',
                city: '',
                country: '',
                notes: '',
            };
        },
        handleSubmit() {
            this.submitting = true;
            const payload = {
                first_name: this.form.first_name,
                last_name: this.form.last_name,
                email: this.form.email,
                phone: this.form.phone,
                document_type: this.form.document_type,
                document_number: this.form.document_number,
                address: this.form.address,
                city: this.form.city,
                country: this.form.country,
                notes: this.form.notes,
            };

            const url = this.isEditing ? `/guests/${this.editId}` : '/guests';
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
            Swal.fire({
                title: 'Supprimer ce client ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/guests/${id}`, { preserveScroll: true });
                }
            });
        },
    },
};
</script>
