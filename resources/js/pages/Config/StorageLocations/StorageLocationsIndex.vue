<template>
    <ConfigLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-serena-text-main">Emplacements</h1>
                    <p class="text-sm text-serena-text-muted">Gérez les emplacements de stockage.</p>
                </div>
                <PrimaryButton v-if="canManage" type="button" class="px-4 py-2" @click="openModal()">
                    Nouvel emplacement
                </PrimaryButton>
            </div>

            <div class="overflow-hidden rounded-xl border border-serena-border bg-white shadow-sm">
                <table class="min-w-full divide-y divide-serena-border text-sm">
                    <thead class="bg-serena-bg-soft/80 text-left text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                        <tr>
                            <th class="px-4 py-3">Nom</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Catégorie</th>
                            <th class="px-4 py-3">Actif</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-serena-border text-serena-text-main">
                        <tr v-for="location in locations" :key="location.id" class="hover:bg-serena-bg-soft">
                            <td class="px-4 py-3 font-semibold">{{ location.name }}</td>
                            <td class="px-4 py-3">{{ location.code || '—' }}</td>
                            <td class="px-4 py-3">{{ location.category }}</td>
                            <td class="px-4 py-3">
                                <span :class="location.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'" class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold">
                                    {{ location.is_active ? 'Oui' : 'Non' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-serena-text-muted">
                                <SecondaryButton v-if="canManage" type="button" class="px-3 py-1 text-xs" @click="openModal(location)">
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
                            {{ editing ? 'Modifier un emplacement' : 'Nouvel emplacement' }}
                        </h2>
                        <p class="text-sm text-serena-text-muted">Nom et catégorie de l’emplacement.</p>
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
                            Code
                            <input v-model="form.code" type="text" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Catégorie
                            <input v-model="form.category" type="text" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
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
    name: 'StorageLocationsIndex',
    components: { ConfigLayout, PrimaryButton, SecondaryButton },
    props: {
        locations: {
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
            return Boolean((this.$page?.props?.auth?.can ?? {})['stock_locations_manage']);
        },
    },
    methods: {
        openModal(location = null) {
            this.editing = location;
            this.form = location
                ? {
                    name: location.name,
                    code: location.code,
                    category: location.category,
                    is_active: location.is_active,
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
                code: '',
                category: 'general',
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
                    await router.post(`/settings/resources/storage-locations/${this.editing.id}`, this.form, {
                        _method: 'PUT',
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                } else {
                    await router.post('/settings/resources/storage-locations', this.form, {
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                }
            } catch (error) {
                console.error(error);
            } finally {
                this.submitting = false;
            }
        },
    },
};
</script>
