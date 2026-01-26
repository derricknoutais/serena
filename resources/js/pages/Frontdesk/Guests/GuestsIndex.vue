<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Clients</h1>
                <p class="text-sm text-gray-500">Base client commune au tenant.</p>
            </div>
            <PrimaryButton
                @click="openCreateModal"
            >
                Nouveau client
            </PrimaryButton>
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
                    <tr
                        v-for="guest in guests.data"
                        :key="guest.id"
                        class="cursor-pointer hover:bg-gray-50"
                        @click="openDetailsModal(guest)"
                    >
                        <td class="px-4 py-3 text-sm text-gray-800">{{ guest.full_name || `${guest.first_name} ${guest.last_name}` }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ guest.email || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ guest.phone || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <button class="text-indigo-600 hover:underline cursor-pointer" @click.stop="openEditModal(guest)">Éditer</button>
                            <button class="text-red-600 hover:underline cursor-pointer" @click.stop="destroy(guest.id)">Supprimer</button>
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
                    
                    <button type="button" class=" text-sm text-gray-500 hover:text-gray-700 cursor-pointer " @click="closeModal">Fermer</button>
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
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-800 cursor-pointer" @click="closeModal">Annuler</button>
                        <PrimaryButton
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="submitting"
                        >
                            <span v-if="submitting">Enregistrement…</span>
                            <span v-else>{{ isEditing ? 'Mettre à jour' : 'Enregistrer' }}</span>
                        </PrimaryButton>
                    </div>
                </Form>
            </div>
        </div>

        <div
            v-if="showDetailsModal"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeDetailsModal"
        >
            <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Fiche client</h2>
                        <p class="text-sm text-gray-500">Aperçu des informations et de la fidélité.</p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700 cursor-pointer" @click="closeDetailsModal">Fermer</button>
                </div>

                <div v-if="detailsLoading" class="py-10 text-center text-sm text-gray-500">
                    Chargement des informations…
                </div>
                <div v-else-if="detailsError" class="rounded-lg border border-red-100 bg-red-50 p-4 text-sm text-red-600">
                    {{ detailsError }}
                </div>
                <div v-else class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-xs font-semibold uppercase text-gray-400">Coordonnées</p>
                            <p class="mt-2 text-sm font-semibold text-gray-800">{{ details?.guest?.full_name || '—' }}</p>
                            <p class="text-sm text-gray-600">{{ details?.guest?.email || '—' }}</p>
                            <p class="text-sm text-gray-600">{{ details?.guest?.phone || '—' }}</p>
                            <p class="mt-2 text-xs text-gray-500">
                                {{ details?.guest?.document_type || 'Document' }} : {{ details?.guest?.document_number || '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase text-gray-400">Analyse rapide</p>
                            <div class="mt-3 space-y-2 text-sm text-gray-700">
                                <div class="flex items-center justify-between">
                                    <span>Réservations</span>
                                    <span class="font-semibold">{{ details?.analytics?.reservations_total ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Nuits cumulées</span>
                                    <span class="font-semibold">{{ details?.analytics?.total_nights ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Dépensé</span>
                                    <span class="font-semibold">{{ formatCurrency(details?.analytics?.total_spent) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Solde</span>
                                    <span class="font-semibold">{{ formatCurrency(details?.analytics?.balance_due) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Dernier séjour</span>
                                    <span class="font-semibold">{{ formatDate(details?.analytics?.last_stay_at) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase text-gray-400">Fidélité</p>
                            <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ details?.loyalty?.total_points ?? 0 }}</p>
                            <p class="text-xs text-gray-500">Points cumulés</p>
                            <div class="mt-4 space-y-2 text-xs text-gray-600">
                                <p class="font-semibold text-gray-500">Derniers points</p>
                                <div v-if="details?.loyalty?.recent?.length">
                                    <div v-for="point in details.loyalty.recent" :key="point.id" class="flex items-center justify-between">
                                        <span>{{ point.reservation_code || point.type || 'Séjour' }}</span>
                                        <span class="font-semibold">{{ point.points }}</span>
                                    </div>
                                </div>
                                <p v-else class="text-gray-400">Aucun point récent.</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-400">Adresse & notes</p>
                        <p class="mt-2 text-sm text-gray-700">
                            {{ formatAddress(details?.guest) }}
                        </p>
                        <p class="mt-2 text-sm text-gray-500">{{ details?.guest?.notes || 'Aucune note.' }}</p>
                    </div>

                    <div class="flex items-center justify-end">
                        <Link
                            v-if="details?.guest?.id"
                            :href="guestDetailsUrl(details.guest.id)"
                            class="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                        >
                            Voir plus
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import Swal from 'sweetalert2';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'GuestsIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, Link },
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
            showDetailsModal: false,
            detailsLoading: false,
            detailsError: null,
            details: null,
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
        resourceBasePath() {
            const currentUrl = this.$page?.url ?? '';

            if (currentUrl.startsWith('/settings/resources')) {
                return '/settings/resources';
            }

            if (currentUrl.startsWith('/guests')) {
                return '';
            }

            return '/resources';
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
            router.visit(`${this.resourceBasePath}/guests`, {
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
        async openDetailsModal(guest) {
            this.showDetailsModal = true;
            this.detailsLoading = true;
            this.detailsError = null;
            this.details = null;

            try {
                const response = await axios.get(`${this.resourceBasePath}/guests/${guest.id}/summary`, {
                    headers: { Accept: 'application/json' },
                });
                this.details = response.data;
            } catch (error) {
                this.detailsError = 'Impossible de charger les informations du client.';
            } finally {
                this.detailsLoading = false;
            }
        },
        closeDetailsModal() {
            this.showDetailsModal = false;
            this.detailsLoading = false;
            this.detailsError = null;
            this.details = null;
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

            const url = this.isEditing
                ? `${this.resourceBasePath}/guests/${this.editId}`
                : `${this.resourceBasePath}/guests`;
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
        guestDetailsUrl(guestId) {
            return `${this.resourceBasePath}/guests/${guestId}`;
        },
        formatCurrency(amount, currency = 'XAF') {
            const value = Number(amount || 0);
            return `${value.toFixed(0)} ${currency}`;
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
        },
        formatAddress(guest) {
            if (!guest) {
                return '—';
            }

            return [guest.address, guest.city, guest.country].filter(Boolean).join(', ') || '—';
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
                    router.delete(`${this.resourceBasePath}/guests/${id}`, { preserveScroll: true });
                }
            });
        },
    },
};
</script>
