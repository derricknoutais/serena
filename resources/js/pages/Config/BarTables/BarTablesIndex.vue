<template>
    <ConfigLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-serena-text-main">Tables</h1>
                    <p class="text-sm text-serena-text-muted">Configurez les tables du bar/restaurant.</p>
                </div>
                <PrimaryButton v-if="canManage" type="button" class="px-4 py-2" @click="openModal()">
                    Nouvelle table
                </PrimaryButton>
            </div>

            <div class="overflow-hidden rounded-xl border border-serena-border bg-white shadow-sm">
                <table class="min-w-full divide-y divide-serena-border text-sm">
                    <thead class="bg-serena-bg-soft/80 text-left text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                        <tr>
                            <th class="px-4 py-3">Nom</th>
                            <th class="px-4 py-3">Zone</th>
                            <th class="px-4 py-3 text-right">Capacité</th>
                            <th class="px-4 py-3 text-right">Ordre</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-serena-border text-serena-text-main">
                        <tr v-for="table in tables" :key="table.id" class="hover:bg-serena-bg-soft">
                            <td class="px-4 py-3 font-semibold">{{ table.name }}</td>
                            <td class="px-4 py-3">{{ table.area || '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ formatNumber(table.capacity) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatNumber(table.sort_order) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                                    :class="table.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ table.is_active ? 'Oui' : 'Non' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-serena-text-muted">
                                <SecondaryButton v-if="canManage" type="button" class="px-3 py-1 text-xs" @click="openModal(table)">
                                    Éditer
                                </SecondaryButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 sm:items-center">
            <div class="w-full max-w-2xl rounded-2xl border border-serena-border bg-white p-6 shadow-xl" @click.stop>
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-serena-text-main">
                            {{ editing ? 'Modifier une table' : 'Nouvelle table' }}
                        </h2>
                        <p class="text-sm text-serena-text-muted">Nom, zone et capacité de la table.</p>
                    </div>
                    <button type="button" class="text-sm text-serena-text-muted" @click="closeModal">Fermer</button>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium text-serena-text-muted">
                            Nom
                            <input v-model="form.name" type="text" required class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Zone
                            <input v-model="form.area" type="text" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Capacité
                            <input v-model.number="form.capacity" type="number" min="1" max="255" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Ordre d’affichage
                            <input v-model.number="form.sort_order" type="number" min="0" max="65535" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-serena-text-muted">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary" />
                            Active
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton type="button" class="px-4 py-2 text-xs" @click="closeModal">Annuler</SecondaryButton>
                        <PrimaryButton type="submit" class="px-4 py-2 text-xs" :disabled="submitting">
                            {{ submitting ? 'Enregistrement…' : editing ? 'Mettre à jour' : 'Enregistrer' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'BarTablesIndex',
    components: { ConfigLayout, PrimaryButton, SecondaryButton },
    props: {
        tables: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            showModal: false,
            editing: null,
            submitting: false,
            form: this.resetForm(),
        };
    },
    computed: {
        canManage() {
            return Boolean((this.$page?.props?.auth?.can ?? {})['pos_tables_manage']);
        },
    },
    methods: {
        formatNumber(value) {
            if (value === null || value === undefined) {
                return '—';
            }

            return Number(value).toString();
        },
        openModal(table = null) {
            this.editing = table;
            this.form = table
                ? {
                    name: table.name,
                    area: table.area,
                    capacity: table.capacity,
                    sort_order: table.sort_order,
                    is_active: table.is_active,
                }
                : this.resetForm();
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editing = null;
            this.form = this.resetForm();
        },
        resetForm() {
            return {
                name: '',
                area: '',
                capacity: null,
                sort_order: 0,
                is_active: true,
            };
        },
        async submitForm() {
            if (this.submitting) {
                return;
            }

            this.submitting = true;

            try {
                if (this.editing) {
                    await router.put(`/settings/resources/bar-tables/${this.editing.id}`, this.form, {
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                } else {
                    await router.post('/settings/resources/bar-tables', this.form, {
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                }
            } finally {
                this.submitting = false;
            }
        },
    },
};
</script>
