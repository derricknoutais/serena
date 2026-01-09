<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Checklists HK</h1>
                <p class="text-sm text-gray-500">Configurer les checklists d'inspection.</p>
            </div>
            <PrimaryButton
                v-if="canManage"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouvelle checklist
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type de chambre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="checklist in checklists" :key="checklist.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ checklist.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="checklist.scope === 'global' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700'"
                            >
                                {{ scopeLabel(checklist.scope) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ checklist.scope === 'room_type' ? checklist.room_type?.name || '—' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    :checked="checklist.is_active"
                                    @change="toggleActive(checklist, $event)"
                                />
                                <span class="text-xs">
                                    {{ checklist.is_active ? 'Oui' : 'Non' }}
                                </span>
                            </label>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ checklist.items_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <div class="flex flex-wrap items-center gap-2">
                                <SecondaryButton
                                    type="button"
                                    class="px-2 py-1 text-xs"
                                    @click="openItemsModal(checklist)"
                                >
                                    Items
                                </SecondaryButton>
                                <SecondaryButton
                                    type="button"
                                    class="px-2 py-1 text-xs"
                                    @click="openEditModal(checklist)"
                                >
                                    Éditer
                                </SecondaryButton>
                                <SecondaryButton
                                    type="button"
                                    class="px-2 py-1 text-xs"
                                    @click="duplicateChecklist(checklist)"
                                >
                                    Dupliquer
                                </SecondaryButton>
                                <PrimaryButton
                                    type="button"
                                    variant="danger"
                                    class="px-2 py-1 text-xs bg-serena-danger"
                                    @click="destroyChecklist(checklist)"
                                >
                                    Supprimer
                                </PrimaryButton>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="checklists.length === 0">
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                            Aucune checklist créée pour le moment.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeModal"
        >
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ isEditing ? 'Modifier la checklist' : 'Nouvelle checklist' }}
                        </h2>
                        <p class="text-sm text-gray-500">Définissez la scope et les règles d'activation.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <form class="space-y-4" @submit.prevent="handleSubmit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-700">Nom</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            />
                            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Scope</label>
                            <select
                                v-model="form.scope"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                @change="handleScopeChange"
                            >
                                <option value="global">Globale</option>
                                <option value="room_type">Type de chambre</option>
                            </select>
                            <p v-if="errors.scope" class="mt-1 text-xs text-red-600">{{ errors.scope }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Type de chambre</label>
                            <select
                                v-model="form.room_type_id"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                :disabled="form.scope !== 'room_type'"
                            >
                                <option value="">Sélectionner</option>
                                <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
                                    {{ roomType.name }}
                                </option>
                            </select>
                            <p v-if="errors.room_type_id" class="mt-1 text-xs text-red-600">{{ errors.room_type_id }}</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Activer cette checklist
                    </label>

                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton type="button" class="text-sm" @click="closeModal">Annuler</SecondaryButton>
                        <PrimaryButton type="submit" class="px-4 py-2 text-sm" :disabled="submitting">
                            <span v-if="submitting">Enregistrement…</span>
                            <span v-else>{{ isEditing ? 'Mettre à jour' : 'Enregistrer' }}</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showItemsModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeItemsModal"
        >
            <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Items checklist</h2>
                        <p class="text-sm text-gray-500">{{ activeChecklist?.name || 'Checklist' }}</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeItemsModal">Fermer</SecondaryButton>
                </div>

                <form class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-100 bg-gray-50 p-4" @submit.prevent="submitItemForm">
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-xs font-medium text-gray-600">Libellé</label>
                        <input
                            v-model="itemForm.label"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                        <p v-if="itemErrors.label" class="mt-1 text-xs text-red-600">{{ itemErrors.label }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input
                            v-model="itemForm.is_required"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Obligatoire
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input
                            v-model="itemForm.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Actif
                    </label>
                    <div class="flex items-center gap-2">
                        <SecondaryButton
                            v-if="itemEditingId"
                            type="button"
                            class="px-3 py-2 text-xs"
                            @click="cancelItemEdit"
                        >
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton type="submit" class="px-4 py-2 text-xs" :disabled="itemSubmitting">
                            {{ itemEditingId ? 'Mettre à jour' : 'Ajouter' }}
                        </PrimaryButton>
                    </div>
                </form>

                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Libellé</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Obligatoire</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Actif</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Ordre</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(item, index) in items" :key="item.id">
                                <td class="px-3 py-2 text-sm text-gray-700">{{ item.label }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="item.is_required"
                                        @change="toggleItemFlag(item, 'is_required', $event)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="item.is_active"
                                        @change="toggleItemFlag(item, 'is_active', $event)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    <div class="flex items-center gap-2">
                                        <SecondaryButton
                                            type="button"
                                            class="px-2 py-1 text-xs"
                                            :disabled="index === 0"
                                            @click="moveItem(index, -1)"
                                        >
                                            ↑
                                        </SecondaryButton>
                                        <SecondaryButton
                                            type="button"
                                            class="px-2 py-1 text-xs"
                                            :disabled="index === items.length - 1"
                                            @click="moveItem(index, 1)"
                                        >
                                            ↓
                                        </SecondaryButton>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <SecondaryButton
                                            type="button"
                                            class="px-2 py-1 text-xs"
                                            @click="editItem(item)"
                                        >
                                            Éditer
                                        </SecondaryButton>
                                        <PrimaryButton
                                            type="button"
                                            variant="danger"
                                            class="px-2 py-1 text-xs bg-serena-danger"
                                            @click="destroyItem(item)"
                                        >
                                            Supprimer
                                        </PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="items.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Aucun item pour cette checklist.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'HousekeepingChecklistsIndex',
    components: { ConfigLayout, PrimaryButton, SecondaryButton },
    props: {
        checklists: {
            type: Array,
            required: true,
        },
        roomTypes: {
            type: Array,
            required: true,
        },
        canManage: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            showModal: false,
            showItemsModal: false,
            isEditing: false,
            submitting: false,
            editId: null,
            form: {
                name: '',
                scope: 'global',
                room_type_id: '',
                is_active: false,
            },
            activeChecklistId: null,
            items: [],
            itemForm: {
                label: '',
                is_required: false,
                is_active: true,
            },
            itemEditingId: null,
            itemSubmitting: false,
        };
    },
    computed: {
        errors() {
            return this.$page.props.errors || {};
        },
        itemErrors() {
            return this.$page.props.errors || {};
        },
        activeChecklist() {
            if (!this.activeChecklistId) {
                return null;
            }

            return this.checklists.find((checklist) => checklist.id === this.activeChecklistId) || null;
        },
    },
    watch: {
        checklists: {
            handler() {
                this.syncActiveChecklist();
            },
            deep: true,
        },
    },
    methods: {
        scopeLabel(scope) {
            return scope === 'room_type' ? 'Type de chambre' : 'Globale';
        },
        openCreateModal() {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            this.isEditing = false;
            this.editId = null;
            this.form = {
                name: '',
                scope: 'global',
                room_type_id: '',
                is_active: false,
            };
            this.showModal = true;
        },
        openEditModal(checklist) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            this.isEditing = true;
            this.editId = checklist.id;
            this.form = {
                name: checklist.name,
                scope: checklist.scope,
                room_type_id: checklist.room_type_id ?? '',
                is_active: !!checklist.is_active,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.submitting = false;
        },
        handleScopeChange() {
            if (this.form.scope !== 'room_type') {
                this.form.room_type_id = '';
            }
        },
        handleSubmit() {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            this.submitting = true;

            const payload = {
                name: this.form.name,
                scope: this.form.scope,
                room_type_id: this.form.scope === 'room_type' ? this.form.room_type_id || null : null,
                is_active: this.form.is_active,
            };

            const url = this.isEditing
                ? `/settings/resources/housekeeping-checklists/${this.editId}`
                : '/settings/resources/housekeeping-checklists';
            const method = this.isEditing ? 'put' : 'post';

            router[method](url, payload, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    this.showModal = false;
                    this.submitting = false;
                    this.showToast('success', this.isEditing ? 'Checklist mise à jour.' : 'Checklist créée.');
                },
                onError: () => {
                    this.submitting = false;
                    this.showToast('error', 'Impossible d’enregistrer la checklist.');
                },
            });
        },
        toggleActive(checklist, event) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            const nextValue = !checklist.is_active;
            const resetToggle = () => {
                if (event?.target) {
                    event.target.checked = checklist.is_active;
                }
            };
            const executeUpdate = () => {
                router.put(
                    `/settings/resources/housekeeping-checklists/${checklist.id}`,
                    {
                        name: checklist.name,
                        scope: checklist.scope,
                        room_type_id: checklist.scope === 'room_type' ? checklist.room_type_id : null,
                        is_active: nextValue,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            this.showToast('success', nextValue ? 'Checklist activée.' : 'Checklist désactivée.');
                        },
                        onError: () => {
                            resetToggle();
                            this.showToast('error', 'Impossible de mettre à jour la checklist.');
                        },
                    },
                );
            };

            if (nextValue) {
                Swal.fire({
                    title: 'Activer cette checklist ? ',
                    text: 'Cette action désactivera les autres checklists du même scope.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, activer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#4f46e5',
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeUpdate();
                    } else {
                        resetToggle();
                    }
                });
            } else {
                executeUpdate();
            }
        },
        duplicateChecklist(checklist) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            Swal.fire({
                title: 'Dupliquer cette checklist ? ',
                text: 'Les items seront copiés et la nouvelle checklist sera inactive.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Dupliquer',
                cancelButtonText: 'Annuler',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                router.post(`/settings/resources/housekeeping-checklists/${checklist.id}/duplicate`, {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        this.showToast('success', 'Checklist dupliquée.');
                    },
                    onError: () => {
                        this.showToast('error', 'Impossible de dupliquer la checklist.');
                    },
                });
            });
        },
        destroyChecklist(checklist) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            Swal.fire({
                title: 'Supprimer cette checklist ? ',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/settings/resources/housekeeping-checklists/${checklist.id}`, {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            this.showToast('success', 'Checklist supprimée.');
                        },
                        onError: () => {
                            this.showToast('error', 'Impossible de supprimer la checklist.');
                        },
                    });
                }
            });
        },
        openItemsModal(checklist) {
            if (!this.canManage) {
                this.showUnauthorizedAlert();
                return;
            }

            this.activeChecklistId = checklist.id;
            this.items = (checklist.items || []).map((item) => ({ ...item }));
            this.resetItemForm();
            this.showItemsModal = true;
        },
        closeItemsModal() {
            this.showItemsModal = false;
            this.activeChecklistId = null;
            this.items = [];
            this.resetItemForm();
        },
        syncActiveChecklist() {
            if (!this.activeChecklistId) {
                return;
            }

            const freshChecklist = this.checklists.find((checklist) => checklist.id === this.activeChecklistId);
            if (!freshChecklist) {
                return;
            }

            this.items = (freshChecklist.items || [])
                .map((item) => ({ ...item }))
                .sort((a, b) => a.sort_order - b.sort_order);
        },
        resetItemForm() {
            this.itemForm = {
                label: '',
                is_required: false,
                is_active: true,
            };
            this.itemEditingId = null;
        },
        submitItemForm() {
            if (!this.activeChecklistId) {
                return;
            }

            const isEditingItem = Boolean(this.itemEditingId);
            this.itemSubmitting = true;
            const payload = {
                label: this.itemForm.label,
                is_required: this.itemForm.is_required,
                is_active: this.itemForm.is_active,
            };

            const url = this.itemEditingId
                ? `/settings/resources/housekeeping-checklists/${this.activeChecklistId}/items/${this.itemEditingId}`
                : `/settings/resources/housekeeping-checklists/${this.activeChecklistId}/items`;
            const method = this.itemEditingId ? 'put' : 'post';

            router[method](url, payload, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    this.resetItemForm();
                    this.itemSubmitting = false;
                    this.showToast('success', isEditingItem ? 'Item mis à jour.' : 'Item ajouté.');
                },
                onError: () => {
                    this.itemSubmitting = false;
                    this.showToast('error', 'Impossible d’enregistrer cet item.');
                },
            });
        },
        editItem(item) {
            this.itemEditingId = item.id;
            this.itemForm = {
                label: item.label,
                is_required: !!item.is_required,
                is_active: !!item.is_active,
            };
        },
        cancelItemEdit() {
            this.resetItemForm();
        },
        toggleItemFlag(item, field, event) {
            if (!this.activeChecklistId) {
                return;
            }

            const nextValue = event.target.checked;
            item[field] = nextValue;

            router.put(
                `/settings/resources/housekeeping-checklists/${this.activeChecklistId}/items/${item.id}`,
                {
                    label: item.label,
                    is_required: item.is_required,
                    is_active: item.is_active,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        this.showToast('success', 'Item mis à jour.');
                    },
                    onError: () => {
                        this.showToast('error', 'Impossible de mettre à jour cet item.');
                    },
                },
            );
        },
        moveItem(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= this.items.length) {
                return;
            }

            const updated = [...this.items];
            const [moved] = updated.splice(index, 1);
            updated.splice(newIndex, 0, moved);

            this.items = updated.map((item, order) => ({
                ...item,
                sort_order: order,
            }));

            if (!this.activeChecklistId) {
                return;
            }

            const payload = {
                items: this.items.map((item, order) => ({
                    id: item.id,
                    sort_order: order,
                })),
            };

            router.post(`/settings/resources/housekeeping-checklists/${this.activeChecklistId}/items/reorder`, payload, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    this.showToast('success', 'Ordre mis à jour.');
                },
                onError: () => {
                    this.showToast('error', 'Impossible de réordonner les items.');
                },
            });
        },
        destroyItem(item) {
            if (!this.activeChecklistId) {
                return;
            }

            Swal.fire({
                title: 'Supprimer cet item ? ',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                router.delete(
                    `/settings/resources/housekeeping-checklists/${this.activeChecklistId}/items/${item.id}`,
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            this.showToast('success', 'Item supprimé.');
                        },
                        onError: () => {
                            this.showToast('error', 'Impossible de supprimer cet item.');
                        },
                    },
                );
            });
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
        showToast(icon, title) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                timer: 2500,
                showConfirmButton: false,
                icon,
                title,
            });
        },
    },
};
</script>
