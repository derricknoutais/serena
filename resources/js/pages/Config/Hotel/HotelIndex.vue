<template>
    <ConfigLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-xl font-semibold">Hotel</h1>
                <p class="text-sm text-gray-500">
                    {{ hasHotel ? "Gestion de l'hotel actif." : "Aucun hotel encore cree, commencez ici." }}
                </p>
            </div>

            <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm">
                <div v-if="!canManageHotel" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Acces reserve aux proprietaires et managers.
                </div>
                <div v-else>
                    <p class="text-sm text-gray-700">
                        Mettez a jour les informations de l'hotel actif. Chaque section se sauvegarde automatiquement pendant la saisie.
                    </p>
                </div>
            </div>

            <div v-if="canManageHotel" class="space-y-4">
                <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-2 shadow-sm">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === tab.value ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="setActiveTab(tab.value)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <form class="space-y-6" @submit.prevent>
                    <section v-show="activeTab === 'general'" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Informations generales</h2>
                                <p class="text-xs text-gray-500">Nom, devise, horaires et localisation.</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span :class="statusClass(segmentStatus.general)">{{ statusLabel(segmentStatus.general) }}</span>
                                <PrimaryButton
                                    type="button"
                                    class="px-3 py-2 text-xs"
                                    :disabled="segmentSaving.general"
                                    @click="saveSegment('general')"
                                >
                                    Enregistrer
                                </PrimaryButton>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Nom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Devise (3 lettres) <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="form.currency"
                                    type="text"
                                    maxlength="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.currency" class="mt-1 text-xs text-red-600">{{ errors.currency }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Heure d'arrivee <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="form.check_in_time"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @change="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.check_in_time" class="mt-1 text-xs text-red-600">{{ errors.check_in_time }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Heure de depart <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="form.check_out_time"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @change="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.check_out_time" class="mt-1 text-xs text-red-600">{{ errors.check_out_time }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Fuseau horaire</label>
                                <input
                                    v-model="form.timezone"
                                    type="text"
                                    placeholder="ex: Africa/Libreville"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.timezone" class="mt-1 text-xs text-red-600">{{ errors.timezone }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Adresse</label>
                                <input
                                    v-model="form.address"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.address" class="mt-1 text-xs text-red-600">{{ errors.address }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Ville</label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.city" class="mt-1 text-xs text-red-600">{{ errors.city }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Pays</label>
                                <input
                                    v-model="form.country"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('general')"
                                />
                                <p v-if="errors.country" class="mt-1 text-xs text-red-600">{{ errors.country }}</p>
                            </div>
                        </div>
                    </section>

                    <section v-show="activeTab === 'policies'" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Politiques de sejour</h2>
                                <p class="text-xs text-gray-500">Arrivees anticipees et departs tardifs.</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span :class="statusClass(segmentStatus.policies)">{{ statusLabel(segmentStatus.policies) }}</span>
                                <PrimaryButton
                                    type="button"
                                    class="px-3 py-2 text-xs"
                                    :disabled="segmentSaving.policies"
                                    @click="saveSegment('policies')"
                                >
                                    Enregistrer
                                </PrimaryButton>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Politique arrivee anticipee</label>
                                <select
                                    v-model="form.early_policy"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @change="scheduleAutoSave('policies')"
                                >
                                    <option value="free">Autorisee (gratuite)</option>
                                    <option value="paid">Autorisee (payante)</option>
                                    <option value="forbidden">Interdite</option>
                                </select>
                                <p v-if="errors.early_policy" class="mt-1 text-xs text-red-600">{{ errors.early_policy }}</p>
                                <div v-if="form.early_policy === 'paid'" class="mt-2 grid grid-cols-2 gap-2">
                                    <select
                                        v-model="form.early_fee_type"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        @change="scheduleAutoSave('policies')"
                                    >
                                        <option value="flat">Montant fixe</option>
                                        <option value="percent">Pourcentage</option>
                                    </select>
                                    <input
                                        v-model.number="form.early_fee_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        placeholder="Montant"
                                        @input="scheduleAutoSave('policies')"
                                    />
                                </div>
                                <div class="mt-2">
                                    <label class="text-xs font-medium text-gray-600">Heure seuil (optionnelle)</label>
                                    <input
                                        v-model="form.early_cutoff_time"
                                        type="time"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        @change="scheduleAutoSave('policies')"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Politique depart tardif</label>
                                <select
                                    v-model="form.late_policy"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @change="scheduleAutoSave('policies')"
                                >
                                    <option value="free">Autorise (gratuit)</option>
                                    <option value="paid">Autorise (payant)</option>
                                    <option value="forbidden">Interdit</option>
                                </select>
                                <p v-if="errors.late_policy" class="mt-1 text-xs text-red-600">{{ errors.late_policy }}</p>
                                <div v-if="form.late_policy === 'paid'" class="mt-2 grid grid-cols-2 gap-2">
                                    <select
                                        v-model="form.late_fee_type"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        @change="scheduleAutoSave('policies')"
                                    >
                                        <option value="flat">Montant fixe</option>
                                        <option value="percent">Pourcentage</option>
                                    </select>
                                    <input
                                        v-model.number="form.late_fee_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        placeholder="Montant"
                                        @input="scheduleAutoSave('policies')"
                                    />
                                </div>
                                <div class="mt-2">
                                    <label class="text-xs font-medium text-gray-600">Heure limite (optionnelle)</label>
                                    <input
                                        v-model="form.late_max_time"
                                        type="time"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        @change="scheduleAutoSave('policies')"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-show="activeTab === 'documents'" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Documents</h2>
                                <p class="text-xs text-gray-500">En-tete et pied de page pour factures, recus et bons.</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span :class="statusClass(segmentStatus.documents)">{{ statusLabel(segmentStatus.documents) }}</span>
                                <a
                                    v-if="canUpdateDocuments"
                                    :href="invoicePreviewUrl"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                >
                                    Apercu facture
                                </a>
                                <PrimaryButton
                                    type="button"
                                    class="px-3 py-2 text-xs"
                                    :disabled="segmentSaving.documents"
                                    @click="saveSegment('documents')"
                                >
                                    Enregistrer
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="!canUpdateDocuments" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            Vous n'avez pas la permission de modifier les documents.
                        </div>

                        <div v-else class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Nom affiche</label>
                                <input
                                    v-model="form.document_display_name"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_display_name" class="mt-1 text-xs text-red-600">{{ errors.document_display_name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Logo</label>
                                <div class="mt-1">
                                    <FilePond
                                        name="logo"
                                        :disabled="!canUpdateDocuments"
                                        :allow-multiple="false"
                                        :instant-upload="true"
                                        :max-file-size="'12MB'"
                                        :server="documentLogoServer"
                                        :label-idle="'Deposez un logo ou cliquez ici'"
                                        :label-max-file-size-exceeded="'Fichier trop volumineux (max 12 Mo).'"
                                        :label-max-file-size="'Taille maximale: 12 Mo.'"
                                        accepted-file-types="image/*"
                                        class="text-sm"
                                    />
                                    <p v-if="documentLogoError" class="mt-1 text-xs text-red-600">
                                        {{ documentLogoError }}
                                    </p>
                                </div>
                                <div v-if="documentLogoUrl" class="mt-2 flex items-center gap-3">
                                    <img :src="documentLogoUrl" alt="Logo document" class="h-12 w-auto rounded-md border border-gray-200 bg-white p-1" />
                                    <span class="text-xs text-gray-500">Apercu</span>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100"
                                        @click="removeDocumentLogo"
                                        :disabled="documentLogoSubmitting"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Contact — Adresse</label>
                                <input
                                    v-model="form.document_contact_address"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_contact_address" class="mt-1 text-xs text-red-600">{{ errors.document_contact_address }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Contact — Telephone</label>
                                <input
                                    v-model="form.document_contact_phone"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_contact_phone" class="mt-1 text-xs text-red-600">{{ errors.document_contact_phone }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Contact — Email</label>
                                <input
                                    v-model="form.document_contact_email"
                                    type="email"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_contact_email" class="mt-1 text-xs text-red-600">{{ errors.document_contact_email }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Legal — NIF</label>
                                <input
                                    v-model="form.document_legal_nif"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_legal_nif" class="mt-1 text-xs text-red-600">{{ errors.document_legal_nif }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Legal — RCCM</label>
                                <input
                                    v-model="form.document_legal_rccm"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_legal_rccm" class="mt-1 text-xs text-red-600">{{ errors.document_legal_rccm }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Texte Header</label>
                                <textarea
                                    v-model="form.document_header_text"
                                    rows="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_header_text" class="mt-1 text-xs text-red-600">{{ errors.document_header_text }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Texte Footer</label>
                                <textarea
                                    v-model="form.document_footer_text"
                                    rows="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    @input="scheduleAutoSave('documents')"
                                />
                                <p v-if="errors.document_footer_text" class="mt-1 text-xs text-red-600">{{ errors.document_footer_text }}</p>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import axios from 'axios';
import vueFilePond from 'vue-filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';
import { router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

const FilePond = vueFilePond(FilePondPluginImagePreview);

export default {
    name: 'HotelIndex',
    components: { ConfigLayout, PrimaryButton, FilePond },
    props: {
        hotel: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            activeTab: 'general',
            tabs: [
                { value: 'general', label: 'Informations' },
                { value: 'policies', label: 'Politiques' },
                { value: 'documents', label: 'Documents' },
            ],
            autoSaveDelay: 700,
            segmentStatus: {
                general: 'idle',
                policies: 'idle',
                documents: 'idle',
            },
            segmentSaving: {
                general: false,
                policies: false,
                documents: false,
            },
            segmentTimers: {},
            documentLogoSubmitting: false,
            documentLogoError: '',
            documentLogoPreviewUrl: this.hotel?.document_settings?.logo_url || '',
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
                document_display_name: this.hotel?.document_settings?.display_name || this.hotel?.name || '',
                document_contact_address: this.hotel?.document_settings?.contact?.address || '',
                document_contact_phone: this.hotel?.document_settings?.contact?.phone || '',
                document_contact_email: this.hotel?.document_settings?.contact?.email || '',
                document_legal_nif: this.hotel?.document_settings?.legal?.nif || '',
                document_legal_rccm: this.hotel?.document_settings?.legal?.rccm || '',
                document_header_text: this.hotel?.document_settings?.header_text || '',
                document_footer_text: this.hotel?.document_settings?.footer_text || '',
                document_logo_path: this.hotel?.document_settings?.logo_path || null,
            },
            hasHotel: Boolean(this.hotel && this.hotel.id),
        };
    },
    computed: {
        errors() {
            return this.$page.props?.errors ?? {};
        },
        isOwner() {
            return (this.$page.props?.auth?.user?.roles || []).some((r) => r.name === 'owner');
        },
        canManageHotel() {
            return this.isOwner || this.canUpdateDocuments;
        },
        canUpdateDocuments() {
            return this.$page.props?.auth?.can?.hotels_documents_update ?? false;
        },
        invoicePreviewUrl() {
            return '/settings/resources/hotel/documents/invoice-preview';
        },
        documentLogoUrl() {
            if (this.documentLogoPreviewUrl) {
                return this.documentLogoPreviewUrl;
            }

            return this.form.document_logo_path ? `/storage/${this.form.document_logo_path}` : '';
        },
        documentLogoServer() {
            return {
                process: this.processDocumentLogo,
                revert: this.revertDocumentLogo,
            };
        },
        hasRequiredFields() {
            return Boolean(this.form.name && this.form.currency && this.form.check_in_time && this.form.check_out_time);
        },
    },
    methods: {
        setActiveTab(tab) {
            const allowed = this.tabs.map((item) => item.value);
            if (!allowed.includes(tab)) {
                return;
            }

            this.activeTab = tab;
        },
        scheduleAutoSave(segment) {
            if (!this.canManageHotel) {
                return;
            }

            if (!this.hasRequiredFields) {
                this.segmentStatus[segment] = 'invalid';
                return;
            }

            if (this.segmentTimers[segment]) {
                clearTimeout(this.segmentTimers[segment]);
            }

            this.segmentStatus[segment] = 'pending';
            this.segmentTimers[segment] = setTimeout(() => {
                this.saveSegment(segment, { silent: true });
            }, this.autoSaveDelay);
        },
        saveSegment(segment, { silent = false } = {}) {
            if (!this.canManageHotel) {
                return;
            }

            if (!this.hasRequiredFields) {
                this.segmentStatus[segment] = 'invalid';
                return;
            }

            this.segmentStatus[segment] = 'saving';
            this.segmentSaving[segment] = true;

            router.put('/settings/resources/hotel', this.form, {
                preserveScroll: true,
                onSuccess: () => {
                    this.segmentStatus[segment] = 'saved';
                    if (!silent) {
                        this.flashSegment(segment, 'saved');
                    }
                },
                onError: () => {
                    this.segmentStatus[segment] = 'error';
                },
                onFinish: () => {
                    this.segmentSaving[segment] = false;
                    if (this.segmentStatus[segment] === 'saved') {
                        setTimeout(() => {
                            if (this.segmentStatus[segment] === 'saved') {
                                this.segmentStatus[segment] = 'idle';
                            }
                        }, 2000);
                    }
                },
            });
        },
        flashSegment(segment, status) {
            if (status !== 'saved') {
                return;
            }

            this.segmentStatus[segment] = 'saved';
        },
        statusLabel(status) {
            if (status === 'saving' || status === 'pending') {
                return 'Sauvegarde en cours...';
            }
            if (status === 'saved') {
                return 'Enregistre';
            }
            if (status === 'error') {
                return 'Erreur lors de la sauvegarde';
            }
            if (status === 'invalid') {
                return 'Completer les champs requis';
            }
            return ' '; 
        },
        statusClass(status) {
            if (status === 'saving' || status === 'pending') {
                return 'text-blue-600';
            }
            if (status === 'saved') {
                return 'text-emerald-600';
            }
            if (status === 'error') {
                return 'text-red-600';
            }
            if (status === 'invalid') {
                return 'text-amber-600';
            }
            return 'text-gray-400';
        },
        processDocumentLogo(fieldName, file, metadata, load, error, progress, abort) {
            if (!this.canUpdateDocuments) {
                error('Permission refusee.');
                return { abort };
            }

            this.documentLogoError = '';
            this.documentLogoSubmitting = true;

            const formData = new FormData();
            formData.append('logo', file);

            const controller = new AbortController();

            axios
                .post('/settings/resources/hotel/documents/logo', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                        Accept: 'application/json',
                    },
                    signal: controller.signal,
                    onUploadProgress: (event) => {
                        if (event.total) {
                            progress(true, event.loaded, event.total);
                        }
                    },
                })
                .then((response) => {
                    this.form.document_logo_path = response.data?.path ?? null;
                    this.documentLogoPreviewUrl = response.data?.url ?? '';
                    load(response.data?.path ?? file.name);
                })
                .catch((err) => {
                    if (err.response?.status === 413) {
                        this.documentLogoError = 'Fichier trop volumineux (max 12 Mo).';
                    } else if (err.response?.status === 422 && err.response?.data?.errors?.logo?.[0]) {
                        this.documentLogoError = err.response.data.errors.logo[0];
                    } else {
                        this.documentLogoError = err.response?.data?.message ?? 'Impossible de charger le logo.';
                    }
                    error(this.documentLogoError);
                })
                .finally(() => {
                    this.documentLogoSubmitting = false;
                });

            return {
                abort: () => {
                    controller.abort();
                    abort();
                },
            };
        },
        revertDocumentLogo(uniqueFileId, load, error) {
            if (!this.canUpdateDocuments || !this.form.document_logo_path) {
                load();
                return;
            }

            this.documentLogoSubmitting = true;
            this.documentLogoError = '';

            axios
                .delete('/settings/resources/hotel/documents/logo', {
                    headers: { Accept: 'application/json' },
                })
                .then(() => {
                    this.form.document_logo_path = null;
                    this.documentLogoPreviewUrl = '';
                    load();
                })
                .catch((err) => {
                    this.documentLogoError = err.response?.data?.message ?? 'Impossible de supprimer le logo.';
                    error(this.documentLogoError);
                })
                .finally(() => {
                    this.documentLogoSubmitting = false;
                });
        },
        async removeDocumentLogo() {
            if (!this.canUpdateDocuments || !this.form.document_logo_path) {
                return;
            }

            this.documentLogoSubmitting = true;
            this.documentLogoError = '';

            try {
                await axios.delete('/settings/resources/hotel/documents/logo', {
                    headers: { Accept: 'application/json' },
                });
                this.form.document_logo_path = null;
                this.documentLogoPreviewUrl = '';
            } catch (error) {
                this.documentLogoError = error.response?.data?.message ?? 'Impossible de supprimer le logo.';
            } finally {
                this.documentLogoSubmitting = false;
            }
        },
    },
};
</script>
