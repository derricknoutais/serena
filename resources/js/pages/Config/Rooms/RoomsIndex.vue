<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Chambres</h1>
                <p class="text-sm text-gray-500">Gestion des chambres.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                @click="openCreateModal"
            >
                Nouvelle chambre
            </button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Étage</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="room in rooms.data" :key="room.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ room.number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ room.room_type || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ room.floor || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ room.status }}</td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <button class="text-indigo-600 hover:underline" @click="openEditModal(room)">Éditer</button>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(room.id)">Supprimer</button>
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
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier la chambre' : 'Nouvelle chambre' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la chambre.</p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeModal">Fermer</button>
                </div>

                <Form @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="number" rules="required" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Numéro</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="number" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="room_type_id" rules="required" v-slot="{ meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Type de chambre</label>
                                <Multiselect
                                    :model-value="form.room_type_id"
                                    @update:modelValue="(val) => (form.room_type_id = val)"
                                    :options="roomTypeOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un type"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="room_type_id" />
                                </p>
                            </div>
                        </Field>

                        <Field name="floor" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Étage</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="floor" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="status" rules="required" v-slot="{ meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Statut</label>
                                <Multiselect
                                    :model-value="form.status"
                                    @update:modelValue="(val) => (form.status = val)"
                                    :options="statusOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un statut"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="status" />
                                </p>
                            </div>
                        </Field>

                        <Field name="hk_status" rules="required" v-slot="{ meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Statut ménager</label>
                                <Multiselect
                                    :model-value="form.hk_status"
                                    @update:modelValue="(val) => (form.hk_status = val)"
                                    :options="hkStatusOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un statut"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="hk_status" />
                                </p>
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
    name: 'RoomsIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage },
    props: {
        rooms: {
            type: Object,
            required: true,
        },
        roomTypes: {
            type: Array,
            required: true,
        },
        statuses: {
            type: Array,
            required: true,
        },
        housekeepingStatuses: {
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
            form: {
                number: '',
                room_type_id: null,
                floor: '',
                status: '',
                hk_status: '',
            },
        };
    },
    computed: {
        roomTypeOptions() {
            return this.roomTypes.map((rt) => ({ label: rt.name, value: rt.id }));
        },
        statusOptions() {
            return this.statuses.map((s) => ({ label: s, value: s }));
        },
        hkStatusOptions() {
            return this.housekeepingStatuses.map((s) => ({ label: s, value: s }));
        },
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
        openEditModal(room) {
            this.isEditing = true;
            this.editId = room.id;
            this.form = {
                number: room.number || '',
                room_type_id: this.roomTypes.find((rt) => rt.name === room.room_type)?.id ?? null,
                floor: room.floor || '',
                status: room.status || '',
                hk_status: room.hk_status || '',
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
        },
        resetForm() {
            this.form = {
                number: '',
                room_type_id: null,
                floor: '',
                status: '',
                hk_status: '',
            };
        },
        handleSubmit() {
            this.submitting = true;
            const url = this.isEditing ? `/ressources/rooms/${this.editId}` : '/ressources/rooms';
            const method = this.isEditing ? router.put : router.post;

            method(url, this.form, {
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
            if (confirm('Supprimer cette chambre ?')) {
                router.delete(`/ressources/rooms/${id}`);
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

configure({
    validateOnBlur: true,
    validateOnChange: true,
    validateOnInput: true,
    validateOnModelUpdate: true,
});
</script>
