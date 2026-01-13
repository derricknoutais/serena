<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Techniciens</h1>
                <p class="text-sm text-gray-500">Gérez les techniciens internes et externes.</p>
            </div>
            <PrimaryButton
                v-if="canManage"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouveau technicien
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Société</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Interne</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="technician in technicians" :key="technician.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ technician.name }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            <div>{{ technician.phone || '—' }}</div>
                            <div>{{ technician.email || '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ technician.company_name || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="technician.is_internal ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ technician.is_internal ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <SecondaryButton
                                v-if="canManage"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(technician)"
                            >
                                Éditer
                            </SecondaryButton>
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
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier le technicien' : 'Nouveau technicien' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations du technicien.</p>
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

                        <Field name="company_name" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Société</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.company_name = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="company_name" class="mt-1 text-xs text-red-600" />
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
                            </div>
                        </Field>

                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-700">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            ></textarea>
                            <p v-if="errors.notes" class="mt-1 text-xs text-red-600">{{ errors.notes }}</p>
                        </div>

                        <Field name="is_internal" type="checkbox" v-slot="{ field }">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 md:col-span-2">
                                <input
                                    v-bind="field"
                                    type="checkbox"
                                    :checked="form.is_internal"
                                    @change="(e) => { field.onChange(e); form.is_internal = e.target.checked; }"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Technicien interne
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
    name: 'TechniciansIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        technicians: {
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
                phone: '',
                email: '',
                company_name: '',
                is_internal: false,
                notes: '',
            },
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        canManage() {
            return this.$page.props.auth?.can?.maintenance_technicians_manage ?? false;
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
            return /.+@.+\..+/.test(value) || 'Email invalide.';
        });

        configure({
            validateOnInput: true,
        });
    },
    methods: {
        openCreateModal() {
            if (!this.canManage) {
                this.showUnauthorizedAlert();

                return;
            }

            this.isEditing = false;
            this.editId = null;
            this.form = {
                name: '',
                phone: '',
                email: '',
                company_name: '',
                is_internal: false,
                notes: '',
            };
            this.formKey += 1;
            this.showModal = true;
        },
        openEditModal(technician) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();

                return;
            }

            this.isEditing = true;
            this.editId = technician.id;
            this.form = {
                name: technician.name || '',
                phone: technician.phone || '',
                email: technician.email || '',
                company_name: technician.company_name || '',
                is_internal: !!technician.is_internal,
                notes: technician.notes || '',
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
                phone: '',
                email: '',
                company_name: '',
                is_internal: false,
                notes: '',
            };
        },
        handleSubmit() {
            if (!this.canManage) {
                this.showUnauthorizedAlert();

                return;
            }

            this.submitting = true;
            const payload = {
                name: this.form.name,
                phone: this.form.phone || null,
                email: this.form.email || null,
                company_name: this.form.company_name || null,
                is_internal: this.form.is_internal,
                notes: this.form.notes || null,
            };

            const url = this.isEditing
                ? `/settings/resources/technicians/${this.editId}`
                : '/settings/resources/technicians';
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
