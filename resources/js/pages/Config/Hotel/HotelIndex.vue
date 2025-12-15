<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Hôtels</h1>
                <p class="text-sm text-gray-500">
                    {{ hasHotel ? "Gestion de l'hôtel actif." : "Aucun hôtel encore créé, commencez ici." }}
                </p>
            </div>
            <PrimaryButton
                v-if="isOwner"
                type="button"
                class="px-4 py-2"
                @click="openModal"
            >
                {{ hasHotel ? "Modifier l'hôtel" : "Créer un hôtel" }}
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm">
            <div v-if="!isOwner" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Accès réservé aux propriétaires (owner).
            </div>
            <div v-else>
                <p class="text-sm text-gray-700">
                    Utilisez le bouton ci-dessus pour mettre à jour les informations de l’hôtel actif.
                </p>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeModal"
        >
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Informations de l’hôtel</h2>
                        <p class="text-sm text-gray-500">Tous les champs requis sont marqués d’une astérisque.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">
                        Fermer
                    </SecondaryButton>
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
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                            </div>
                        </Field>

                        <Field name="currency" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Devise (3 lettres) <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    maxlength="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="currency" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                            </div>
                        </Field>

                        <Field name="check_in_time" rules="required" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Heure d’arrivée <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="check_in_time" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="check_out_time" rules="required" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Heure de départ <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="check_out_time" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <div class="md:col-span-2 rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm font-semibold text-gray-800">Arrivées anticipées / départs tardifs</p>
                            <p class="text-xs text-gray-500">Définissez la politique et les frais appliqués automatiquement au check-in / check-out.</p>
                            <div class="mt-3 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Politique arrivée anticipée</label>
                                    <Field name="early_policy" v-slot="{ field }">
                                        <select
                                            v-bind="field"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        >
                                            <option value="free">Autorisée (gratuite)</option>
                                            <option value="paid">Autorisée (payante)</option>
                                            <option value="forbidden">Interdite</option>
                                        </select>
                                    </Field>
                                    <div v-if="form.early_policy === 'paid'" class="mt-2 grid grid-cols-2 gap-2">
                                        <Field name="early_fee_type" v-slot="{ field }">
                                            <select
                                                v-bind="field"
                                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            >
                                                <option value="flat">Montant fixe</option>
                                                <option value="percent">Pourcentage</option>
                                            </select>
                                        </Field>
                                        <Field name="early_fee_value" v-slot="{ field }">
                                            <input
                                                v-bind="field"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                placeholder="Montant"
                                            />
                                        </Field>
                                    </div>
                                    <Field name="early_cutoff_time" v-slot="{ field }">
                                        <div class="mt-2">
                                            <label class="text-xs font-medium text-gray-600">Heure seuil (optionnelle)</label>
                                            <input
                                                v-bind="field"
                                                type="time"
                                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            />
                                        </div>
                                    </Field>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Politique départ tardif</label>
                                    <Field name="late_policy" v-slot="{ field }">
                                        <select
                                            v-bind="field"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        >
                                            <option value="free">Autorisé (gratuit)</option>
                                            <option value="paid">Autorisé (payant)</option>
                                            <option value="forbidden">Interdit</option>
                                        </select>
                                    </Field>
                                    <div v-if="form.late_policy === 'paid'" class="mt-2 grid grid-cols-2 gap-2">
                                        <Field name="late_fee_type" v-slot="{ field }">
                                            <select
                                                v-bind="field"
                                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            >
                                                <option value="flat">Montant fixe</option>
                                                <option value="percent">Pourcentage</option>
                                            </select>
                                        </Field>
                                        <Field name="late_fee_value" v-slot="{ field }">
                                            <input
                                                v-bind="field"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                placeholder="Montant"
                                            />
                                        </Field>
                                    </div>
                                    <Field name="late_max_time" v-slot="{ field }">
                                        <div class="mt-2">
                                            <label class="text-xs font-medium text-gray-600">Heure limite (optionnelle)</label>
                                            <input
                                                v-bind="field"
                                                type="time"
                                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            />
                                        </div>
                                    </Field>
                                </div>
                            </div>
                        </div>

                        <Field name="timezone" v-slot="{ field, meta }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">
                                    Fuseau horaire
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    placeholder="ex: Africa/Libreville"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="timezone" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">Champ requis.</p>
                            </div>
                        </Field>

                        <Field name="address" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Adresse</label>
                                <input
                                    v-bind="field"
                                    type="text"
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
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="country" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton type="button" class="text-sm" @click="closeModal">
                            Annuler
                        </SecondaryButton>
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
import { router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import Swal from 'sweetalert2'

export default {
    name: 'HotelIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        hotel: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            showModal: false,
            submitting: false,
            formKey: 0,
            form: {
                name: this.hotel?.name || '',
                currency: this.hotel?.currency || 'XAF',
                timezone: this.hotel?.timezone || '',
                check_in_time: this.hotel?.check_in_time || '14:00',
                check_out_time: this.hotel?.check_out_time || '12:00',
                address: this.hotel?.address || '',
                city: this.hotel?.city || '',
                country: this.hotel?.country || '',
                early_policy: this.hotel?.stay_settings?.early_checkin?.policy || 'free',
                early_fee_type: this.hotel?.stay_settings?.early_checkin?.fee_type || 'flat',
                early_fee_value: this.hotel?.stay_settings?.early_checkin?.fee_value ?? 0,
                early_cutoff_time: this.hotel?.stay_settings?.early_checkin?.cutoff_time || '',
                late_policy: this.hotel?.stay_settings?.late_checkout?.policy || 'free',
                late_fee_type: this.hotel?.stay_settings?.late_checkout?.fee_type || 'flat',
                late_fee_value: this.hotel?.stay_settings?.late_checkout?.fee_value ?? 0,
                late_max_time: this.hotel?.stay_settings?.late_checkout?.max_time || '',
            },
            hasHotel: Boolean(this.hotel && this.hotel.id),
        };
    },
    computed: {
        isOwner() {
            return (this.$page.props?.auth?.user?.roles || []).some((r) => r.name === 'owner');
        },
    },
    created() {
        defineRule('required', (value) => {
            if (value === undefined || value === null || value === '') {
                return 'Ce champ est requis.';
            }
            return true;
        });

        configure({
            validateOnInput: true,
        });
    },
    methods: {
        openModal() {
            if (!this.isOwner) {
                return;
            }
            if (!this.hasHotel) {
                this.form = {
                    name: '',
                    currency: 'XAF',
                timezone: '',
                check_in_time: '14:00',
                check_out_time: '12:00',
                address: '',
                city: '',
                country: '',
                early_policy: 'free',
                early_fee_type: 'flat',
                early_fee_value: 0,
                early_cutoff_time: '',
                late_policy: 'free',
                late_fee_type: 'flat',
                late_fee_value: 0,
                late_max_time: '',
            };
        }
            this.formKey += 1;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        handleSubmit(values) {
            this.submitting = true;
            router.put(
                '/ressources/hotel',
                values,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Votre hôtel a été enregistré',
                            timer : 1500
                        })
                        this.closeModal();
                    },
                    onError: (error) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                        });
                        console.log(error)
                    },
                    onFinish: () => {
                        this.submitting = false;
                    },
                },
            );
        },
    },
};
</script>
