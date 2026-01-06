<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Chambres</h1>
                <p class="text-sm text-gray-500">Gestion des chambres.</p>
            </div>
            <PrimaryButton
                v-if="canCreate"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouvelle chambre
            </PrimaryButton>
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
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ statusLabels[room.status] ?? room.status }}
                        </td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <SecondaryButton
                                v-if="canUpdate"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(room)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canDelete"
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(room.id)"
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
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier la chambre' : 'Nouvelle chambre' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de la chambre.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form :key="formKey" :initial-values="form" @submit="handleSubmit" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field name="number" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Numéro</label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="number" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Champ requis.
                                </p>
                                <p v-if="errors.number" class="mt-1 text-xs text-red-600">{{ errors.number }}</p>
                            </div>
                        </Field>

                        <Field name="room_type_id" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Type de chambre</label>
                                <Multiselect
                                    :model-value="field.value ?? form.room_type_id"
                                    @update:modelValue="(val) => { field.onChange(val); form.room_type_id = val; }"
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
                                <p v-if="errors.room_type_id" class="mt-1 text-xs text-red-600">{{ errors.room_type_id }}</p>
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

                        <Field name="status" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Statut</label>
                                <Multiselect
                                    :model-value="field.value ?? form.status"
                                    @update:modelValue="(val) => { field.onChange(val); form.status = val; }"
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
                                <p v-if="errors.status" class="mt-1 text-xs text-red-600">{{ errors.status }}</p>
                            </div>
                        </Field>

                        <Field name="hk_status" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Statut ménager</label>
                                <Multiselect
                                    :model-value="field.value ?? form.hk_status"
                                    @update:modelValue="(val) => { field.onChange(val); form.hk_status = val; }"
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
                                <p v-if="errors.hk_status" class="mt-1 text-xs text-red-600">{{ errors.hk_status }}</p>
                            </div>
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
                            <span v-else>Enregistrer</span>
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
    name: 'RoomsIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
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
            formKey: 0,
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
            return this.roomTypes.map((rt) => ({ label: rt.name, value: Number(rt.id) }));
        },
        statusOptions() {
            return this.statuses.map((s) => ({ label: this.statusLabels[s] ?? s, value: s }));
        },
        hkStatusOptions() {
            return this.housekeepingStatuses.map((s) => ({ label: this.hkStatusLabels[s] ?? s, value: s }));
        },
        errors() {
            return this.$page.props.errors || {};
        },
        canCreate() {
            return this.$page.props.auth?.can?.rooms_create ?? false;
        },
        canUpdate() {
            return this.$page.props.auth?.can?.rooms_update ?? false;
        },
        canDelete() {
            return this.$page.props.auth?.can?.rooms_delete ?? false;
        },
        statusLabels() {
            return {
                active: 'Active',
                inactive: 'Inactive',
                out_of_order: 'Hors service',
            };
        },
        hkStatusLabels() {
            return {
                dirty: 'Sale',
                cleaning: 'En cours',
                awaiting_inspection: 'En attente d’inspection',
                inspected: 'Inspectée',
                redo: 'À refaire',
            };
        },
    },
    methods: {
        openCreateModal() {
            if (!this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.formKey += 1;
            this.showModal = true;
        },
        openEditModal(room) {
            if (!this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = true;
            this.editId = room.id;
            this.form = {
                number: room.number || '',
                room_type_id: this.roomTypeOptions.find((opt) => opt.value === Number(room.room_type_id)) ?? null,
                floor: room.floor || '',
                status: this.statusOptions.find((opt) => opt.value === room.status) ?? null,
                hk_status: this.hkStatusOptions.find((opt) => opt.value === room.hk_status) ?? null,
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
                number: '',
                room_type_id: null,
                floor: '',
                status: '',
                hk_status: '',
            };
        },
        handleSubmit(values) {
            if (!this.isEditing && !this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (this.isEditing && !this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }

            this.submitting = true;
            const url = this.isEditing ? `/ressources/rooms/${this.editId}` : '/ressources/rooms';
            if (this.isEditing) {
                router.put(
                    url,
                    {
                        ...values,
                        room_type_id: values.room_type_id?.value ?? values.room_type_id,
                        status: values.status?.value ?? values.status,
                        hk_status: values.hk_status?.value ?? values.hk_status,
                    },
                    {
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
                    },
                );
            } else {
                router.post(
                    url,
                    {
                        ...values,
                        room_type_id: values.room_type_id?.value ?? values.room_type_id,
                        status: values.status?.value ?? values.status,
                        hk_status: values.hk_status?.value ?? values.hk_status,
                    },
                    {
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
                    },
                );
            }
        },
        destroy(id) {
            if (!this.canDelete) {
                this.showUnauthorizedAlert();

                return;
            }
            Swal.fire({
                title: 'Supprimer cette chambre ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/ressources/rooms/${id}`, { preserveScroll: true });
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
