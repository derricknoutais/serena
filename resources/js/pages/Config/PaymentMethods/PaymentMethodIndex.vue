<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Méthodes de paiement</h1>
                <p class="text-sm text-gray-500">Paramétrage des méthodes de paiement.</p>
            </div>
            <PrimaryButton
                v-if="canCreate"
                type="button"
                class="px-4 py-2"
                @click="openModal"
            >
                Nouvelle méthode
            </PrimaryButton>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fournisseur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Compte</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actif</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Par défaut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Portée</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Config</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="method in paymentMethods.data" :key="method.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ method.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ method.code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ method.type ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ method.provider ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ method.account_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="method.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ method.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="method.is_default ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ method.is_default ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <div>Hotel: {{ method.hotel_id ?? '—' }}</div>
                            <div>Tenant: {{ method.tenant_id ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <span class="block max-w-xs truncate" :title="formatConfig(method.config)">
                                {{ formatConfig(method.config) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <SecondaryButton
                                v-if="canUpdate"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openModal(method)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canDelete"
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(method.id)"
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
            <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ editingId ? 'Modifier la méthode' : 'Créer une méthode' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la méthode.</p>
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

                        <Field name="code" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Code <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.code = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="code" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                                <p v-if="errors.code" class="mt-1 text-xs text-red-600">{{ errors.code }}</p>
                            </div>
                        </Field>

                        <Field name="type" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Type</label>
                                <Multiselect
                                    :model-value="field.value ?? form.type"
                                    @update:modelValue="(val) => { field.onChange(val); form.type = val; }"
                                    :options="typeOptions"
                                    :close-on-select="true"
                                    :allow-empty="true"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un type"
                                    class="mt-1"
                                />
                                <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
                            </div>
                        </Field>

                        <Field name="provider" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Fournisseur</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.provider = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="errors.provider" class="mt-1 text-xs text-red-600">{{ errors.provider }}</p>
                            </div>
                        </Field>

                        <Field name="account_number" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Compte</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    @input="(e) => { field.onChange(e); form.account_number = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <p v-if="errors.account_number" class="mt-1 text-xs text-red-600">{{ errors.account_number }}</p>
                            </div>
                        </Field>

                        <Field name="config" rules="json" v-slot="{ field, meta }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Configuration (JSON)</label>
                                <textarea
                                    v-bind="field"
                                    rows="4"
                                    @input="(e) => { field.onChange(e); form.config = e.target.value; }"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="config" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    JSON invalide.
                                </p>
                                <p v-if="errors.config" class="mt-1 text-xs text-red-600">{{ errors.config }}</p>
                            </div>
                        </Field>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="is_active" class="text-sm font-medium text-gray-700">Activer la méthode</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_default"
                                v-model="form.is_default"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="is_default" class="text-sm font-medium text-gray-700">Définir par défaut</label>
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
                            <span v-else>{{ editingId ? 'Mettre à jour' : 'Enregistrer' }}</span>
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
    name: 'PaymentMethodIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        paymentMethods: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
            submitting: false,
            formKey: 0,
            editingId: null,
            form: {
                name: '',
                code: '',
                type: null,
                provider: '',
                account_number: '',
                config: '',
                is_active: true,
                is_default: false,
            },
            typeOptions: [
                { label: 'Espèces', value: 'cash' },
                { label: 'Mobile Money', value: 'mobile_money' },
                { label: 'Carte bancaire', value: 'card' },
                { label: 'Virement', value: 'bank_transfer' },
                { label: 'Autre', value: 'other' },
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

        defineRule('json', (value) => {
            if (value === undefined || value === null || value === '') {
                return true;
            }
            try {
                JSON.parse(value);
                return true;
            } catch {
                return 'JSON invalide.';
            }
        });

        configure({
            validateOnInput: true,
        });
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        canCreate() {
            return this.$page.props.auth?.can?.payment_methods_create ?? false;
        },
        canUpdate() {
            return this.$page.props.auth?.can?.payment_methods_update ?? false;
        },
        canDelete() {
            return this.$page.props.auth?.can?.payment_methods_delete ?? false;
        },
    },
    methods: {
        formatConfig(config) {
            if (!config || Object.keys(config).length === 0) {
                return '—';
            }
            return JSON.stringify(config);
        },
        openModal(method = null) {
            if (!method && !this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (method && !this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (method) {
                this.editingId = method.id;
                this.form = {
                    name: method.name ?? '',
                    code: method.code ?? '',
                    type: this.typeOptions.find((opt) => opt.value === method.type) ?? null,
                    provider: method.provider ?? '',
                    account_number: method.account_number ?? '',
                    config: method.config ? JSON.stringify(method.config, null, 2) : '',
                    is_active: Boolean(method.is_active),
                    is_default: Boolean(method.is_default),
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
            this.editingId = null;
            this.form = {
                name: '',
                code: '',
                type: null,
                provider: '',
                account_number: '',
                config: '',
                is_active: true,
                is_default: false,
            };
            this.formKey += 1;
        },
        submit() {
            if (!this.editingId && !this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (this.editingId && !this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }

            this.submitting = true;
            const payload = {
                name: this.form.name,
                code: this.form.code,
                type: this.form.type ? String(this.form.type.value ?? this.form.type) : null,
                provider: this.form.provider || null,
                account_number: this.form.account_number || null,
                config: this.form.config || null,
                is_active: this.form.is_active,
                is_default: this.form.is_default,
            };

            const method = this.editingId ? 'put' : 'post';
            const url = this.editingId ? `/ressources/payment-methods/${this.editingId}` : '/ressources/payment-methods';

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
            if (!this.canDelete) {
                this.showUnauthorizedAlert();

                return;
            }

            Swal.fire({
                title: 'Supprimer cette méthode ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/payment-methods/${id}`, { preserveScroll: true });
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
