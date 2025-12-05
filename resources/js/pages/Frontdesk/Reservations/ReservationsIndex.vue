<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Réservations</h1>
                <p class="text-sm text-gray-500">Vue calendrier des réservations.</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
                <FullCalendar :options="calendarOptions" />
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800">Détails</h3>
                <p class="text-sm text-gray-500">Cliquez sur un événement pour voir le détail.</p>

                <div v-if="selectedEvent" class="mt-4 space-y-2 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">{{ selectedEvent.title }}</span>
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="statusClass(selectedEvent.status)"
                        >
                            {{ statusLabel(selectedEvent.status) }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-600">
                        Arrivée : <span class="font-medium text-gray-800">{{ selectedEvent.start }}</span>
                    </p>
                    <p class="text-xs text-gray-600">
                        Départ : <span class="font-medium text-gray-800">{{ selectedEvent.end }}</span>
                    </p>
                    <p class="text-xs text-gray-600" v-if="selectedEvent.id">ID: {{ selectedEvent.id }}</p>

                    <div class="mt-3 space-y-2">
                        <label class="text-xs font-semibold text-gray-700">Actions</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="selectedEvent?.status === 'pending'"
                                type="button"
                                class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="statusSubmitting"
                                @click="performStatusAction('confirm')"
                            >
                                Confirmer
                            </button>
                            <button
                                v-if="selectedEvent?.status === 'confirmed'"
                                type="button"
                                class="rounded-lg bg-green-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="statusSubmitting"
                                @click="performStatusAction('check_in')"
                            >
                                Check-in
                            </button>
                            <button
                                v-if="selectedEvent?.status === 'in_house'"
                                type="button"
                                class="rounded-lg bg-gray-800 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="statusSubmitting"
                                @click="performStatusAction('check_out')"
                            >
                                Check-out
                            </button>
                        </div>
                        <button
                            type="button"
                            class="mt-2 w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="!selectedEvent?.id || folioLoading"
                            @click="openFolioForSelectedReservation"
                        >
                            {{ folioLoading ? 'Ouverture du folio...' : 'Ouvrir le folio' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeModal"
        >
            <div class="w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ editingId ? 'Modifier la réservation' : 'Nouvelle réservation' }}</h2>
                        <p class="text-sm text-gray-500">
                            Mode {{ fullMode ? 'complet' : 'rapide' }} · Date pré-remplie depuis le calendrier.
                        </p>
                    </div>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeModal">
                        Fermer
                    </button>
                </div>

                <div
                    v-if="fullMode"
                    class="mb-3 flex items-center gap-2 text-xs font-medium"
                >
                    <span :class="currentStep === 1 ? 'text-indigo-600' : 'text-gray-400'">
                        Étape 1 : Infos principales
                    </span>
                    <span>›</span>
                    <span :class="currentStep === 2 ? 'text-indigo-600' : 'text-gray-400'">
                        Étape 2 : Détails tarifaires
                    </span>
                </div>

                <div class="mb-3 flex items-center gap-2 text-sm">
                    <span class="text-gray-600">Mode :</span>
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1 text-xs font-semibold"
                        :class="fullMode ? 'border-gray-300 text-gray-700' : 'border-indigo-500 text-indigo-600'"
                        @click="fullMode = false"
                    >
                        Rapide
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1 text-xs font-semibold"
                        :class="fullMode ? 'border-indigo-500 text-indigo-600' : 'border-gray-300 text-gray-700'"
                        @click="fullMode = true"
                        >
                            Complet
                        </button>
                    </div>

                <form class="space-y-4" @submit.prevent>
                    <div v-if="!fullMode || currentStep === 1" class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Code *</label>
                            <input
                                v-model="form.code"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                required
                            />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Client *</label>
                                <Multiselect
                                    v-model="selectedGuest"
                                    :options="guests"
                                    track-by="id"
                                    label="name"
                                    placeholder="Sélectionner un client"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Type de chambre *</label>
                                <Multiselect
                                    v-model="selectedRoomType"
                                    :options="roomTypes"
                                    track-by="id"
                                    label="name"
                                    placeholder="Sélectionner un type"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Chambre</label>
                                <Multiselect
                                    v-model="selectedRoom"
                                    :options="filteredRooms"
                                    track-by="id"
                                    :custom-label="roomLabel"
                                    placeholder="Sélectionner une chambre"
                                    class="mt-1"
                                    :allow-empty="true"
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Offre</label>
                                <Multiselect
                                    v-model="selectedOffer"
                                    :options="filteredOffers"
                                    track-by="id"
                                    label="name"
                                    placeholder="Sélectionner une offre"
                                    class="mt-1"
                                    :allow-empty="true"
                                />
                                <div v-if="form.unit_price > 0" class="mt-1 text-xs text-gray-600">
                                    Prix de l’offre :
                                    <span class="font-semibold">
                                        {{ form.unit_price }} {{ form.currency }}
                                    </span>
                                </div>
                                <div v-else class="mt-1 text-xs text-gray-400">
                                    Aucun tarif configuré pour cette combinaison type de chambre / offre.
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Arrivée</label>
                                <input
                                    v-model="form.check_in_date"
                                    :type="form.offer_kind === 'short_stay' ? 'datetime-local' : 'date'"
                                    class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    :class="dateError ? 'border-red-400 focus:border-red-400 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500'"
                                    required
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Départ</label>
                                <input
                                    v-model="form.check_out_date"
                                    :type="form.offer_kind === 'short_stay' ? 'datetime-local' : 'date'"
                                    class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    :class="dateError ? 'border-red-400 focus:border-red-400 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500'"
                                    required
                                />
                            </div>
                        </div>

                        <p v-if="staySummary" class="mt-1 text-[11px] text-gray-500">
                            {{ staySummary }}
                        </p>

                        <div
                            v-if="form.total_amount > 0"
                            class="mt-1 text-xs text-gray-700"
                        >
                            Total pour le séjour :
                            <span class="font-semibold">
                                {{ form.total_amount }} {{ form.currency }}
                            </span>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Statut</label>
                            <select
                                v-model="form.status"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            >
                                <option v-for="status in statusOptions" :key="status" :value="status">
                                    {{ status }}
                                </option>
                            </select>
                        </div>

                        <p v-if="dateError" class="mt-1 text-xs text-red-600">
                            {{ dateError }}
                        </p>
                    </div>

                    <div v-if="fullMode && currentStep === 2" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Devise</label>
                                <input
                                    v-model="form.currency"
                                    type="text"
                                    maxlength="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    required
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Prix unitaire</label>
                                <input
                                    v-model.number="form.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Source</label>
                                <input
                                    v-model="form.source"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    placeholder="walk_in, phone, ota..."
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Heure d'arrivée prévue</label>
                                <input
                                    v-model="form.expected_arrival_time"
                                    type="time"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Montant net</label>
                                <input
                                    v-model.number="form.base_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    required
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Montant taxe</label>
                                <input
                                    v-model.number="form.tax_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    required
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Total</label>
                                <input
                                    v-model.number="form.total_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Adultes</label>
                                <input
                                    v-model.number="form.adults"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Enfants</label>
                                <input
                                    v-model.number="form.children"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="closeModal">
                            Annuler
                        </button>
                        <button
                            v-if="!fullMode"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="submitting"
                            @click="submitForm"
                        >
                            Enregistrer
                        </button>
                        <button
                            v-if="fullMode && currentStep === 1"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            @click="goToStep(2)"
                        >
                            Suivant
                        </button>
                        <button
                            v-if="fullMode && currentStep === 2"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="submitting"
                            @click="submitForm"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <FolioModal
            v-if="folioData"
            :show="showFolioModal"
            :folio="folioData.folio"
            :reservation="folioData.reservation"
            :items="folioData.items"
            :payments="folioData.payments"
            :invoices="folioData.invoices"
            :payment-methods="folioData.paymentMethods"
            @close="showFolioModal = false"
            @updated="refreshFolioData"
        />
    </ConfigLayout>
</template>

<script>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import FolioModal from '@/components/Frontdesk/FolioModal.vue';
import Multiselect from 'vue-multiselect';
import Swal from 'sweetalert2';

export default {
    name: 'ReservationsIndex',
    components: { ConfigLayout, FullCalendar, FolioModal, Multiselect },
    props: {
        events: {
            type: Array,
            required: true,
        },
        guests: {
            type: Array,
            required: true,
        },
        roomTypes: {
            type: Array,
            required: true,
        },
        statusOptions: {
            type: Array,
            required: true,
        },
        defaults: {
            type: Object,
            required: true,
        },
        rooms: {
            type: Array,
            required: true,
        },
        offers: {
            type: Array,
            required: true,
        },
        offerRoomTypePrices: {
            type: Array,
            default: () => [],
        },
        canManageTimes: {
            type: Boolean,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
            form: {
                code: '',
                check_in_date: '',
                check_out_date: '',
                status: 'pending',
                guest_id: '',
                room_type_id: '',
                room_id: null,
                offer_id: null,
                currency: this.defaults.currency || 'XAF',
                unit_price: 0,
                base_amount: 0,
                tax_amount: 0,
                total_amount: 0,
                adults: 1,
                children: 0,
                notes: '',
                source: '',
                expected_arrival_time: '',
                offer_name: '',
                offer_kind: '',
            },
            fullMode: false,
            currentStep: 1,
            dateError: '',
            selectedEvent: null,
            editingId: null,
            eventsLocal: [...this.events],
            submitting: false,
            statusSubmitting: false,
            calendarOptions: {
                plugins: [dayGridPlugin],
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek,dayGridDay',
                },
                height: 'auto',
                eventDisplay: 'block',
                events: [...this.events],
                dayCellDidMount: this.attachDayClick,
                eventClick: this.onEventClick,
                eventDidMount: this.onEventDidMount,
                eventClassNames: this.eventClassNames,
            },
            selectedGuest: null,
            selectedRoomType: null,
            selectedRoom: null,
            selectedOffer: null,
            priceMatrix: {},
            showFolioModal: false,
            folioData: null,
            folioLoading: false,
        };
    },
    created() {
        this.buildPriceMatrix();
    },
    watch: {
        events: {
            immediate: true,
            handler(newEvents) {
                this.eventsLocal = [...newEvents];
                this.calendarOptions = {
                    ...this.calendarOptions,
                    events: this.eventsLocal,
                };
            },
        },
        offerRoomTypePrices: {
            immediate: true,
            handler() {
                this.buildPriceMatrix();
            },
        },
        selectedGuest(newVal) {
            this.form.guest_id = newVal ? newVal.id : '';
        },
        selectedRoomType(newVal) {
            this.form.room_type_id = newVal ? newVal.id : '';
            if (newVal) {
                if (
                    this.selectedRoom &&
                    this.selectedRoom.room_type_id !== newVal.id
                ) {
                    this.selectedRoom = null;
                    this.form.room_id = null;
                }
                if (
                    this.selectedOffer &&
                    this.selectedOffer.room_type_id !== newVal.id
                ) {
                    this.selectedOffer = null;
                    this.form.offer_id = null;
                }
            } else {
                this.selectedRoom = null;
                this.form.room_id = null;
                this.selectedOffer = null;
                this.form.offer_id = null;
            }
            this.updateUnitPriceFromSelection();
        },
        selectedRoom(newVal) {
            this.form.room_id = newVal ? newVal.id : null;
            if (newVal) {
                const rt = this.roomTypes.find(
                    (rt) => rt.id === newVal.room_type_id,
                );
                if (rt) {
                    this.selectedRoomType = rt;
                    this.form.room_type_id = rt.id;
                }
            }
            this.updateUnitPriceFromSelection();
        },
        selectedOffer(newVal) {
            this.form.offer_id = newVal ? newVal.id : null;
            this.form.offer_name = newVal ? newVal.name : '';
            this.form.offer_kind = newVal ? newVal.kind : '';
            this.applyOfferDates(newVal);
            this.updateUnitPriceFromSelection();
        },
	        'form.check_in_date'() {
	            this.validateDates();
	            this.recalculateAmounts();
	        },
	        'form.check_out_date'() {
	            this.validateDates();
	            this.recalculateAmounts();
	        },
	    },
    computed: {
        filteredRooms() {
            if (this.selectedRoomType && this.selectedRoomType.id) {
                return this.rooms.filter(
                    (room) =>
                        room.room_type_id === this.selectedRoomType.id &&
                        room.status === 'active',
                );
            }

            return this.rooms.filter((room) => room.status === 'active');
        },
        filteredOffers() {
            if (this.selectedRoomType && this.selectedRoomType.id) {
                const roomTypeId = this.selectedRoomType.id;
                const offerIdsForRoomType = (this.offerRoomTypePrices || [])
                    .filter((entry) => entry.room_type_id === roomTypeId)
                    .map((entry) => entry.offer_id);

                if (!offerIdsForRoomType.length) {
                    return [];
                }

                return this.offers.filter((offer) =>
                    offerIdsForRoomType.includes(offer.id),
                );
            }

            return this.offers;
        },
        staySummary() {
            if (!this.selectedOffer || !this.form.check_in_date || !this.form.check_out_date) {
                return '';
            }

            const start = this.form.check_in_date;
            const end = this.form.check_out_date;
            const kind = this.selectedOffer.kind ?? 'night';

            if (kind === 'short_stay') {
                return `Séjour courte durée (~3h) le ${start}.`;
            }

            if (kind === 'weekend') {
                return `Séjour week-end du ${start} au ${end}.`;
            }

            if (kind === 'full_day') {
                return `Séjour 24h du ${start} au ${end}.`;
            }

            return `Séjour du ${start} au ${end}.`;
        },
    },
    methods: {
        async openFolioForSelectedReservation() {
            if (!this.selectedEvent?.id) {
                return;
            }

            this.folioLoading = true;

            try {
                const http = window.axios ?? axios;
                const response = await http.get(`/reservations/${this.selectedEvent.id}/folio`);
                this.folioData = response.data;
                this.showFolioModal = true;
            } finally {
                this.folioLoading = false;
            }
        },
        async refreshFolioData() {
            if (!this.selectedEvent?.id || !this.showFolioModal) {
                return;
            }

            const http = window.axios ?? axios;
            const response = await http.get(`/reservations/${this.selectedEvent.id}/folio`);
            this.folioData = response.data;
            this.$inertia.reload({ only: ['events'] });
        },
        attachDayClick(arg) {
            arg.el.style.cursor = 'pointer';
            arg.el.addEventListener('click', (e) => {
                if (e.target.closest('.fc-event')) {
                    return;
                }
                const dateStr = this.formatDate(arg.date);
                this.handleDateClick(dateStr);
            });
        },
        handleDateClick(dateStr) {
            this.form = {
                ...this.form,
                code: `RSV-${new Date().getTime()}`,
                check_in_date: dateStr,
                check_out_date: dateStr,
                status: 'pending',
                guest_id: '',
                room_type_id: '',
                room_id: null,
                offer_id: null,
                source: '',
                expected_arrival_time: '',
            };
            this.selectedGuest = null;
            this.selectedRoomType = null;
            this.selectedRoom = null;
            this.selectedOffer = null;
            this.dateError = '';
            this.fullMode = false;
            this.currentStep = 1;
            this.showModal = true;
            this.editingId = null;
        },
        onEventClick(arg) {
            const event = arg.event;
            this.selectedEvent = {
                id: event.id,
                title: event.title,
                status: event.extendedProps?.status ?? event.status ?? '',
                start: event.startStr,
                end: event.endStr,
            };
        },
        onEventDidMount(arg) {
            arg.el.addEventListener('dblclick', () => {
                const event = arg.event;
                this.editingId = event.id ? event.id.toString() : null;
                this.selectedEvent = {
                    id: event.id,
                    title: event.title,
                    status: event.extendedProps?.status ?? event.status ?? '',
                    start: event.startStr,
                    end: event.endStr,
                };
                this.form = {
                    ...this.form,
                    code: event.title || '',
                    status: event.extendedProps?.status ?? event.status ?? 'pending',
                    guest_id: event.extendedProps?.guest_id ?? '',
                    room_type_id: event.extendedProps?.room_type_id ?? '',
                    currency: event.extendedProps?.currency ?? this.defaults.currency ?? 'XAF',
                    unit_price: event.extendedProps?.unit_price ?? 0,
                    base_amount: event.extendedProps?.base_amount ?? 0,
                    tax_amount: event.extendedProps?.tax_amount ?? 0,
                    total_amount: event.extendedProps?.total_amount ?? 0,
                    adults: event.extendedProps?.adults ?? 1,
                    children: event.extendedProps?.children ?? 0,
                    notes: event.extendedProps?.notes ?? '',
                    check_in_date: event.extendedProps?.check_in_date ?? this.formatDate(event.start),
                    check_out_date:
                        event.extendedProps?.check_out_date ??
                        (event.end
                            ? this.formatDate(new Date(new Date(event.end).getTime() - 24 * 60 * 60 * 1000))
                            : this.formatDate(event.start)),
                    source: event.extendedProps?.source ?? '',
                    expected_arrival_time:
                        event.extendedProps?.expected_arrival_time ?? '',
                    room_id: event.extendedProps?.room_id ?? null,
                    offer_id: event.extendedProps?.offer_id ?? null,
                };
                const guest = this.guests.find(
                    (g) => g.id === this.form.guest_id,
                );
                const roomType = this.roomTypes.find(
                    (rt) => rt.id === this.form.room_type_id,
                );
                const room = this.rooms.find(
                    (r) => r.id === this.form.room_id,
                );
                const offer = this.offers.find(
                    (o) => o.id === this.form.offer_id,
                );
                this.selectedGuest = guest || null;
                this.selectedRoomType = roomType || null;
                this.selectedRoom = room || null;
                this.selectedOffer = offer || null;
                this.fullMode = true;
                this.currentStep = 1;
                this.dateError = '';
                this.showModal = true;
            });
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.currentStep = 1;
            this.dateError = '';
        },
        performStatusAction(action) {
            if (!this.selectedEvent?.id) {
                return;
            }

            const canEdit = this.canManageTimes;
            const now = new Date();

            const config = {
                title: '',
                text: '',
                input: '',
                inputLabel: '',
                inputValue: '',
            };

            if (action === 'confirm') {
                const currentTime = now.toTimeString().slice(0, 5);
                config.title = 'Confirmer la réservation';
                config.text = "Veuillez confirmer l’heure d’arrivée prévue.";
                config.input = 'time';
                config.inputLabel = "Heure d’arrivée prévue";
                config.inputValue = currentTime;
            } else if (action === 'check_in') {
                const currentDateTimeLocal = this.toDateTimeLocal(now);
                config.title = 'Enregistrer le check-in';
                config.text =
                    'Veuillez confirmer la date et l’heure de check-in.';
                config.input = 'datetime-local';
                config.inputLabel = 'Date et heure de check-in';
                config.inputValue = currentDateTimeLocal;
            } else if (action === 'check_out') {
                const currentDateTimeLocal = this.toDateTimeLocal(now);
                config.title = 'Enregistrer le check-out';
                config.text =
                    'Veuillez confirmer la date et l’heure de check-out.';
                config.input = 'datetime-local';
                config.inputLabel = 'Date et heure de check-out';
                config.inputValue = currentDateTimeLocal;
            }

            this.statusSubmitting = true;

            Swal.fire({
                title: config.title,
                text: config.text,
                input: config.input,
                inputLabel: config.inputLabel,
                inputValue: config.inputValue,
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler',
                inputAttributes: canEdit ? {} : { readonly: true },
                didOpen: (popup) => {
                    if (!canEdit) {
                        const input = popup.querySelector('input');
                        if (input) {
                            input.setAttribute('readonly', 'readonly');
                        }
                    }
                },
                preConfirm: (value) => value,
            })
                .then((result) => {
                    if (!result.isConfirmed) {
                        this.statusSubmitting = false;

                        return;
                    }

                    const payload = { action };

                    if (canEdit) {
                        if (action === 'confirm') {
                            payload.expected_arrival_time =
                                result.value || config.inputValue;
                        } else if (
                            action === 'check_in' ||
                            action === 'check_out'
                        ) {
                            payload.event_datetime =
                                result.value || config.inputValue;
                        }
                    }

                    router.patch(
                        `/reservations/${this.selectedEvent.id}/status`,
                        payload,
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                let newStatus = this.selectedEvent?.status || '';

                                if (action === 'confirm') {
                                    newStatus = 'confirmed';
                                } else if (action === 'check_in') {
                                    newStatus = 'in_house';
                                } else if (action === 'check_out') {
                                    newStatus = 'checked_out';
                                }

                                if (this.selectedEvent) {
                                    this.selectedEvent.status = newStatus;
                                }

                                const idx = this.eventsLocal.findIndex(
                                    (e) => String(e.id) === String(this.selectedEvent.id),
                                );
                                if (idx !== -1) {
                                    const ev = this.eventsLocal[idx];
                                    if (!ev.extendedProps) {
                                        ev.extendedProps = {};
                                    }
                                    ev.extendedProps.status = newStatus;
                                    ev.status = newStatus;
                                    this.eventsLocal.splice(idx, 1, { ...ev });
                                }

                                this.calendarOptions = {
                                    ...this.calendarOptions,
                                    events: [...this.eventsLocal],
                                };
                            },
                            onFinish: () => {
                                this.statusSubmitting = false;
                            },
                        },
                    );
                })
                .catch(() => {
                    this.statusSubmitting = false;
                });
        },
        validateDates() {
            this.dateError = '';

            if (!this.form.check_in_date || !this.form.check_out_date) {
                return true;
            }

            const start = new Date(this.form.check_in_date);
            const end = new Date(this.form.check_out_date);

            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return true;
            }

            if (end.getTime() <= start.getTime()) {
                this.dateError = 'La date de départ doit être postérieure à la date d’arrivée.';

                return false;
            }

            return true;
        },
        recalculateAmounts() {
            if (!this.form.check_in_date || !this.form.check_out_date) {
                this.form.base_amount = 0;
                this.form.tax_amount = 0;
                this.form.total_amount = 0;

                return;
            }

            const start = new Date(this.form.check_in_date);
            const end = new Date(this.form.check_out_date);

            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return;
            }

            let units = 1;
            const kind = this.form.offer_kind || 'night';

            if (kind === 'short_stay') {
                units = 1;
            } else {
                const diffMs = end.getTime() - start.getTime();
                const diffDays = Math.max(1, Math.round(diffMs / (1000 * 60 * 60 * 24)));

                units = diffDays;

                if (kind === 'weekend') {
                    units = Math.max(2, diffDays);
                }
            }

            const unit = Number(this.form.unit_price || 0);
            const base = units * unit;
            const tax = 0;
            const total = base + tax;

            this.form.base_amount = base;
            this.form.tax_amount = tax;
            this.form.total_amount = total;
        },
        applyOfferDates(offer) {
            if (!offer) {
                return;
            }

            if (!this.form.check_in_date) {
                this.form.check_in_date = this.formatDate(new Date());
            }

            const start = new Date(this.form.check_in_date);
            const end = new Date(start);
            const kind = offer.kind ?? 'night';

            if (kind === 'weekend') {
                end.setDate(end.getDate() + 2);
            } else if (kind === 'full_day' || kind === 'night') {
                end.setDate(end.getDate() + 1);
            } else if (kind === 'short_stay') {
                // same day
            } else {
                end.setDate(end.getDate() + 1);
            }

            this.form.check_out_date = this.formatDate(end);
        },
        buildPriceMatrix() {
            const matrix = {};
            (this.offerRoomTypePrices || []).forEach((entry) => {
                const key = `${entry.room_type_id}|${entry.offer_id}`;
                matrix[key] = {
                    price: Number(entry.price || 0),
                    currency: entry.currency || this.defaults.currency || 'XAF',
                };
            });
            this.priceMatrix = matrix;
        },
        updateUnitPriceFromSelection() {
            const rtId = this.form.room_type_id;
            const ofId = this.form.offer_id;

            if (!rtId || !ofId) {
                this.form.unit_price = 0;
                this.form.base_amount = 0;
                this.form.tax_amount = 0;
                this.form.total_amount = 0;

                return;
            }

            const entry = this.priceMatrix[`${rtId}|${ofId}`];

            if (entry) {
                this.form.unit_price = entry.price;
                this.form.currency = entry.currency;
            } else {
                this.form.unit_price = 0;
            }

            this.recalculateAmounts();
        },
        goToStep(step) {
            if (step === 2) {
                if (!this.validateDates()) {
                    return;
                }

                if (
                    !this.form.guest_id ||
                    !this.form.room_type_id ||
                    !this.form.check_in_date ||
                    !this.form.check_out_date
                ) {
                    alert(
                        'Veuillez remplir au minimum : client, type de chambre et dates.',
                    );

                    return;
                }
            }

            this.currentStep = step;
        },
        submitForm() {
            if (!this.validateDates()) {
                return;
            }

            this.submitting = true;
            const payload = {
                ...this.form,
            };

            if (this.editingId) {
                router.put(
                    `/reservations/${this.editingId}`,
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            router.reload({ only: ['events'] });
                            this.closeModal();
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            } else {
                router.post(
                    '/reservations',
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            router.reload({ only: ['events'] });
                            this.closeModal();
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            }
        },
        formatDate(dateObj) {
            if (!dateObj) {
                return '';
            }
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        addOneDay(dateStr) {
            if (!dateStr) {
                return '';
            }
            const d = new Date(dateStr);
            d.setDate(d.getDate() + 1);
            return this.formatDate(d);
        },
        eventClassNames(arg) {
            const status = arg.event.extendedProps?.status ?? arg.event.status ?? '';
            const map = {
                pending: 'fc-status-pending',
                confirmed: 'fc-status-confirmed',
                in_house: 'fc-status-in-house',
                checked_out: 'fc-status-checked-out',
                cancelled: 'fc-status-cancelled',
                no_show: 'fc-status-no-show',
            };
            return map[status] ? [map[status]] : [];
        },
        statusClass(status) {
            const map = {
                pending: 'bg-yellow-50 text-yellow-700',
                confirmed: 'bg-blue-50 text-blue-700',
                in_house: 'bg-green-50 text-green-700',
                checked_out: 'bg-gray-100 text-gray-700',
                cancelled: 'bg-red-50 text-red-700',
                no_show: 'bg-orange-50 text-orange-700',
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        },
        statusLabel(status) {
            const map = {
                pending: 'En attente',
                confirmed: 'Confirmée',
                in_house: 'En séjour',
                checked_out: 'Départ effectué',
                cancelled: 'Annulée',
                no_show: 'No-show',
            };

            return map[status] || status;
        },
        toDateTimeLocal(date) {
            const pad = (n) => String(n).padStart(2, '0');
            const year = date.getFullYear();
            const month = pad(date.getMonth() + 1);
            const day = pad(date.getDate());
            const hours = pad(date.getHours());
            const minutes = pad(date.getMinutes());

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        },
        roomLabel(room) {
            if (! room) {
                return '';
            }

            const typeName = room.room_type_name
                ? ` · ${room.room_type_name}`
                : '';

            return `Chambre ${room.number}${typeName}`;
        },
    },
};
</script>

<style scoped>
.fc-status-pending {
    background-color: #fef3c7 !important;
    border-color: #facc15 !important;
    color: #854d0e !important;
}
.fc-status-confirmed {
    background-color: #dbeafe !important;
    border-color: #3b82f6 !important;
    color: #1d4ed8 !important;
}
.fc-status-in-house {
    background-color: #dcfce7 !important;
    border-color: #22c55e !important;
    color: #15803d !important;
}
.fc-status-checked-out {
    background-color: #e5e7eb !important;
    border-color: #9ca3af !important;
    color: #374151 !important;
}
.fc-status-cancelled {
    background-color: #fee2e2 !important;
    border-color: #ef4444 !important;
    color: #991b1b !important;
}
.fc-status-no-show {
    background-color: #ffedd5 !important;
    border-color: #f97316 !important;
    color: #c2410c !important;
}
</style>
