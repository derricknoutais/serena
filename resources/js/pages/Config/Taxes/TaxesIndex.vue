<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Taxes</h1>
                <p class="text-sm text-gray-500">Paramétrage des taxes.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                @click="openModal"
            >
                Nouvelle taxe
            </button>
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
                            <Link :href="`/ressources/taxes/${tax.id}/edit`" class="text-indigo-600 hover:underline">Éditer</Link>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(tax.id)">Supprimer</button>
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
                        <h2 class="text-lg font-semibold">Créer une taxe</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la taxe.</p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeModal">Fermer</button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nom</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Code</label>
                            <input
                                v-model="form.code"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <p v-if="errors.code" class="mt-1 text-xs text-red-600">{{ errors.code }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Taux (%)</label>
                            <input
                                v-model.number="form.rate"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <p v-if="errors.rate" class="mt-1 text-xs text-red-600">{{ errors.rate }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Type</label>
                            <Multiselect
                                :model-value="form.type"
                                @update:modelValue="(val) => (form.type = val)"
                                :options="typeOptions"
                                :close-on-select="true"
                                :allow-empty="false"
                                label="label"
                                track-by="value"
                                placeholder="Sélectionner un type"
                                class="mt-1"
                            />
                            <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
                        </div>

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
                </form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'TaxesIndex',
    components: { ConfigLayout, Link },
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
            form: {
                name: '',
                code: '',
                rate: '',
                type: '',
                is_city_tax: false,
                is_active: true,
            },
            typeOptions: [
                { label: 'Pourcentage', value: 'percentage' },
                { label: 'Montant fixe', value: 'fixed' },
            ],
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
    },
    methods: {
        openModal() {
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
                rate: '',
                type: '',
                is_city_tax: false,
                is_active: true,
            };
        },
        submit() {
            this.submitting = true;
            router.post('/ressources/taxes', this.form, {
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
            if (confirm('Supprimer cette taxe ?')) {
                router.delete(`/ressources/taxes/${id}`);
            }
        },
    },
};
</script>
