<template>
    <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <div class="space-y-4">
            <div class="flex flex-wrap gap-2 items-center">
                <button
                    type="button"
                    class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'arrivals' ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 border border-gray-100 hover:bg-gray-200'"
                    @click="loadTab('arrivals')"
                >
                    Arrivées du jour
                </button>
                <button
                    type="button"
                    class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'departures' ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 border border-gray-100 hover:bg-gray-200'"
                    @click="loadTab('departures')"
                >
                    Départs du jour
                </button>
                <button
                    type="button"
                    class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'inHouse' ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 border border-gray-100 hover:bg-gray-200'"
                    @click="loadTab('inHouse')"
                >
                    Clients en séjour
                </button>
                <button
                    type="button"
                    class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'issues' ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700 border border-gray-100 hover:bg-gray-200'"
                    @click="loadTab('issues')"
                >
                    À problème
                </button>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">
                    {{ tabTitle }}
                </div>
                <div v-if="loading" class="p-4 text-sm text-gray-500">
                    Chargement...
                </div>
                <div v-else>
                    <div v-if="currentList.length === 0" class="p-4 text-sm text-gray-500">
                        Aucun enregistrement pour cet onglet.
                    </div>
                    <div v-else class="max-h-[70vh] overflow-y-auto">
                        <button
                            v-for="reservation in currentList"
                            :key="reservation.id"
                            type="button"
                            class="w-full border-b border-gray-100 px-4 py-3 text-left transition hover:bg-gray-50"
                            :class="selectedReservationId === reservation.id ? 'bg-indigo-50' : ''"
                            @click="selectReservation(reservation)"
                        >
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ reservation.guest.name || '—' }}
                                </div>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700">
                                    {{ reservation.status_label }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span v-if="reservation.room.number">Ch. {{ reservation.room.number }}</span>
                                <span v-else>{{ reservation.room.type || '—' }}</span>
                                <span>•</span>
                                <span>{{ reservation.check_in_date || '—' }} → {{ reservation.check_out_date || '—' }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Réservation sélectionnée</p>
                        <p v-if="selectedReservation" class="text-lg font-semibold text-gray-900">
                            {{ selectedReservation.code }} — {{ selectedReservation.guest?.full_name || selectedReservation.guest?.name || '—' }}
                        </p>
                        <p v-else class="text-sm text-gray-500">Sélectionnez une réservation à gauche.</p>
                        <p v-if="selectedReservation" class="mt-1 text-sm text-gray-500">
                            {{ selectedReservation.room?.number ? `Ch. ${selectedReservation.room.number}` : 'Chambre non assignée' }}
                            · {{ selectedReservation.room_type?.name || '—' }}
                            <span v-if="selectedReservation.offer?.name">· {{ selectedReservation.offer.name }}</span>
                        </p>
                    </div>
                    <div v-if="selectedReservation" class="text-right">
                        <span class="inline-flex items-center rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">
                            {{ selectedReservation.status_label }}
                        </span>
                        <div class="mt-2 text-xs text-gray-500">
                            Séjour: {{ formatDate(selectedReservation.check_in_date) }} → {{ formatDate(selectedReservation.check_out_date) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-5" v-if="selectedReservation">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-[11px] font-semibold uppercase text-gray-500">Total séjour</p>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ formatMoney(selectedReservation.total_amount, selectedReservation.currency) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-[11px] font-semibold uppercase text-gray-500">Total folio</p>
                        <p class="text-sm font-semibold text-gray-800">
                            {{ formatMoney(detailsFolio?.total_charges, detailsFolio?.currency) }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-[11px] font-semibold uppercase text-gray-500">Solde</p>
                        <p class="text-sm font-semibold text-indigo-600">
                            {{ formatMoney(detailsFolio?.balance, detailsFolio?.currency) }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="action in selectedActions"
                        :key="action"
                        type="button"
                        class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                        @click="changeStatus(action, selectedReservation.id)"
                    >
                        {{ actionLabel(action) }}
                    </button>
                    <button
                        v-if="capabilities.add_folio_item || capabilities.create_payment"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="openFolioModal('charges')"
                    >
                        Ouvrir le folio
                    </button>
                    <button
                        v-if="capabilities.adjust_folio"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="promptFolioAdjustment"
                    >
                        Ajuster le folio
                    </button>
                    <button
                        v-if="capabilities.change_guest"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="promptChangeGuest"
                    >
                        Changer le client
                    </button>
                    <button
                        v-if="capabilities.change_offer"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="promptChangeOffer"
                    >
                        Changer l’offre
                    </button>
                    <button
                        v-if="capabilities.override_times"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="promptOverrideTimes"
                    >
                        Ajuster les dates/heures
                    </button>
                    <button
                        v-if="capabilities.move_room"
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200"
                        @click="promptChangeRoom"
                    >
                        Changer de chambre
                    </button>
                </div>

                <div class="rounded-lg border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">Historique</p>
                        <span v-if="timelineLoading" class="text-xs text-gray-400">Chargement…</span>
                    </div>
                    <div v-if="!timelineLoading && timeline.length === 0" class="mt-3 text-xs text-gray-500">
                        Aucun historique pour cette réservation.
                    </div>
                    <div v-else class="mt-3 space-y-3">
                        <div v-for="entry in timeline" :key="entry.id" class="text-sm text-gray-700">
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-gray-900">{{ entry.sentence_fr }}</p>
                                <span class="text-[11px] text-gray-400">{{ entry.happened_at }}</span>
                            </div>
                            <div v-if="entry.meta && entry.meta.length" class="mt-1 flex flex-wrap gap-2 text-[11px] text-gray-500">
                                <span v-for="(line, idx) in entry.meta" :key="idx" class="rounded-full bg-gray-50 px-2 py-0.5">
                                    {{ line }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-6 text-sm text-gray-500">
                Sélectionnez une réservation pour afficher les actions disponibles.
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
            :initial-tab="folioInitialTab"
            :permissions="folioData.permissions || {}"
            @close="closeFolioModal"
            @updated="refreshFolioData"
        />
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import FolioModal from '@/components/Frontdesk/FolioModal.vue';

export default {
    name: 'FrontdeskOperationsBoard',
    components: {
        FolioModal,
    },
    props: {
        guests: {
            type: Array,
            default: () => [],
        },
        offers: {
            type: Array,
            default: () => [],
        },
        rooms: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            activeTab: 'arrivals',
            arrivals: [],
            departures: [],
            inHouse: [],
            issues: [],
            loading: false,
            selectedReservationId: null,
            selectedSummary: null,
            selectedReservation: null,
            detailsFolio: null,
            capabilities: {},
            detailLoading: false,
            timeline: [],
            timelineLoading: false,
            showFolioModal: false,
            folioData: null,
            folioLoading: false,
            folioInitialTab: 'charges',
        };
    },
    computed: {
        currentList() {
            if (this.activeTab === 'arrivals') {
                return this.arrivals;
            }

            if (this.activeTab === 'departures') {
                return this.departures;
            }

            if (this.activeTab === 'issues') {
                return this.issues;
            }

            return this.inHouse;
        },
        tabTitle() {
            const map = {
                arrivals: 'Arrivées du jour',
                departures: 'Départs du jour',
                inHouse: 'Clients en séjour',
                issues: 'Séjours à problème',
            };

            return map[this.activeTab] || '';
        },
        selectedActions() {
            return this.selectedSummary?.actions ?? [];
        },
    },
    created() {
        this.loadTab('arrivals');
    },
    methods: {
        loadTab(tab, options = {}) {
            this.activeTab = tab;
            this.loading = true;

            const endpointMap = {
                arrivals: '/frontdesk/arrivals',
                departures: '/frontdesk/departures',
                inHouse: '/frontdesk/in-house',
                issues: '/frontdesk/issues',
            };

            const url = endpointMap[tab];

            if (!url) {
                this.loading = false;
                return;
            }

            axios
                .get(url)
                .then((response) => {
                    this[tab] = response.data.data;

                    if (options.preserveSelection && this.selectedReservationId) {
                        const stillExists = this[tab].find((item) => item.id === this.selectedReservationId);
                        if (stillExists) {
                            this.selectedSummary = stillExists;
                            return;
                        }
                    }

                    if (this[tab].length > 0) {
                        this.selectReservation(this[tab][0]);
                    } else {
                        this.selectedReservationId = null;
                        this.selectedSummary = null;
                        this.selectedReservation = null;
                        this.detailsFolio = null;
                        this.timeline = [];
                    }
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        selectReservation(reservation) {
            if (!reservation) {
                return;
            }

            this.selectedReservationId = reservation.id;
            this.selectedSummary = reservation;
            this.loadReservationDetails(reservation.id);
        },
        async loadReservationDetails(reservationId) {
            this.detailLoading = true;

            try {
                const response = await axios.get(`/frontdesk/reservations/${reservationId}/details`);
                this.selectedReservation = response.data.reservation;
                this.detailsFolio = response.data.folio;
                this.capabilities = response.data.capabilities || {};
                await this.loadTimeline(reservationId);
            } catch (error) {
                this.selectedReservation = null;
                this.detailsFolio = null;
                this.capabilities = {};
            } finally {
                this.detailLoading = false;
            }
        },
        async loadTimeline(reservationId) {
            this.timelineLoading = true;

            try {
                const response = await axios.get(`/reservations/${reservationId}/timeline`);
                this.timeline = response.data.timeline || [];
            } catch (error) {
                this.timeline = [];
            } finally {
                this.timelineLoading = false;
            }
        },
        async changeStatus(action, reservationId) {
            if (['cancel', 'no_show'].includes(action)) {
                this.promptPenalty(action, reservationId);
                return;
            }

            if (['check_in', 'check_out'].includes(action)) {
                await this.promptActualDateTime(action, reservationId);
                return;
            }

            const result = await Swal.fire({
                title: 'Confirmer ? ',
                text: this.getActionText(action),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non',
            });

            if (!result.isConfirmed) {
                return;
            }

            this.sendStatusRequest(action, reservationId, {});
        },
        promptPenalty(action, reservationId) {
            const title = action === 'cancel' ? 'Annuler la réservation ?' : 'Marquer no-show ?';

            Swal.fire({
                title,
                text: 'Vous pouvez saisir un montant de pénalité ou laisser 0 pour ne pas pénaliser.',
                icon: 'warning',
                html:
                    '<div class="text-left">'
                    + '<label class="block text-xs font-semibold text-gray-600">Montant de la pénalité</label>'
                    + '<input id="swal-penalty-amount" type="number" min="0" step="0.01" value="0" class="swal2-input" />'
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Note (optionnel)</label>'
                    + '<input id="swal-penalty-note" type="text" class="swal2-input" />'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler',
                preConfirm: () => {
                    const amount = parseFloat(document.getElementById('swal-penalty-amount').value || 0);
                    const note = document.getElementById('swal-penalty-note').value || null;

                    return { amount, note };
                },
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                this.sendStatusRequest(action, reservationId, {
                    penalty_amount: result.value.amount,
                    penalty_note: result.value.note,
                });
            });
        },
        async promptActualDateTime(action, reservationId) {
            const title = action === 'check_in' ? 'Heure d’arrivée réelle' : 'Heure de départ réelle';

            const { value: actualDateTime } = await Swal.fire({
                title,
                input: 'datetime-local',
                inputValue: null,
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler',
            });

            if (!actualDateTime) {
                return;
            }

            const payload = action === 'check_in'
                ? { actual_check_in_at: actualDateTime }
                : { actual_check_out_at: actualDateTime };

            this.sendStatusRequest(action, reservationId, payload);
        },
        async sendStatusRequest(action, reservationId, payload) {
            try {
                await axios.patch(`/reservations/${reservationId}/status`, {
                    action,
                    ...payload,
                });

                this.loadTab(this.activeTab, { preserveSelection: true });
                await this.loadReservationDetails(reservationId);
            } catch (error) {
                Swal.fire('Erreur', error.response?.data?.message || 'Une erreur est survenue.', 'error');
            }
        },
        async promptChangeGuest() {
            if (!this.selectedReservation) {
                return;
            }

            const options = this.guests.reduce((acc, guest) => {
                acc[guest.id] = guest.name;
                return acc;
            }, {});

            if (Object.keys(options).length === 0) {
                Swal.fire('Aucun client', 'Aucun client disponible.', 'info');
                return;
            }

            const result = await Swal.fire({
                title: 'Changer le client',
                input: 'select',
                inputOptions: options,
                inputValue: this.selectedReservation.guest?.id || '',
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                inputPlaceholder: 'Sélectionner un client',
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            await this.applyChange(`/reservations/${this.selectedReservation.id}/guest`, {
                guest_id: result.value,
            });
        },
        async promptChangeOffer() {
            if (!this.selectedReservation) {
                return;
            }

            const options = this.offers.reduce((acc, offer) => {
                acc[offer.id] = offer.name;
                return acc;
            }, {});

            if (Object.keys(options).length === 0) {
                Swal.fire('Aucune offre', 'Aucune offre disponible.', 'info');
                return;
            }

            const result = await Swal.fire({
                title: 'Changer l’offre',
                input: 'select',
                inputOptions: options,
                inputValue: this.selectedReservation.offer?.id || '',
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                inputPlaceholder: 'Sélectionner une offre',
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            await this.applyChange(`/reservations/${this.selectedReservation.id}/offer`, {
                offer_id: result.value,
            });
        },
        async promptChangeRoom() {
            if (!this.selectedReservation) {
                return;
            }

            const options = this.rooms.reduce((acc, room) => {
                acc[room.id] = room.number ? `Ch. ${room.number}` : `Chambre ${room.id}`;
                return acc;
            }, {});

            if (Object.keys(options).length === 0) {
                Swal.fire('Aucune chambre', 'Aucune chambre disponible.', 'info');
                return;
            }

            const result = await Swal.fire({
                title: 'Changer de chambre',
                input: 'select',
                inputOptions: options,
                inputValue: this.selectedReservation.room?.id || '',
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                inputPlaceholder: 'Sélectionner une chambre',
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            await this.applyChange(`/reservations/${this.selectedReservation.id}/stay/room`, {
                room_id: result.value,
            });
        },
        async promptOverrideTimes() {
            if (!this.selectedReservation) {
                return;
            }

            const result = await Swal.fire({
                title: 'Ajuster les dates/heures',
                html:
                    '<div class="text-left">'
                    + '<label class="block text-xs font-semibold text-gray-600">Date arrivée</label>'
                    + `<input id="swal-check-in" type="datetime-local" value="${this.toDateTimeLocal(this.selectedReservation.check_in_date)}" class="swal2-input" />`
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Date départ</label>'
                    + `<input id="swal-check-out" type="datetime-local" value="${this.toDateTimeLocal(this.selectedReservation.check_out_date)}" class="swal2-input" />`
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Arrivée réelle</label>'
                    + `<input id="swal-actual-in" type="datetime-local" value="${this.toDateTimeLocal(this.selectedReservation.actual_check_in_at)}" class="swal2-input" />`
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Départ réel</label>'
                    + `<input id="swal-actual-out" type="datetime-local" value="${this.toDateTimeLocal(this.selectedReservation.actual_check_out_at)}" class="swal2-input" />`
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Raison</label>'
                    + '<input id="swal-reason" type="text" class="swal2-input" />'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                preConfirm: () => {
                    return {
                        check_in_date: document.getElementById('swal-check-in').value || null,
                        check_out_date: document.getElementById('swal-check-out').value || null,
                        actual_check_in_at: document.getElementById('swal-actual-in').value || null,
                        actual_check_out_at: document.getElementById('swal-actual-out').value || null,
                        reason: document.getElementById('swal-reason').value || null,
                    };
                },
            });

            if (!result.isConfirmed) {
                return;
            }

            await this.applyChange(`/reservations/${this.selectedReservation.id}/stay-datetimes`, result.value);
        },
        async promptFolioAdjustment() {
            if (!this.detailsFolio) {
                Swal.fire('Folio manquant', 'Le folio est indisponible.', 'info');
                return;
            }

            const result = await Swal.fire({
                title: 'Ajustement du folio',
                html:
                    '<div class="text-left">'
                    + '<label class="block text-xs font-semibold text-gray-600">Montant (positif ou négatif)</label>'
                    + '<input id="swal-adjust-amount" type="number" step="0.01" class="swal2-input" />'
                    + '<label class="mt-2 block text-xs font-semibold text-gray-600">Raison</label>'
                    + '<input id="swal-adjust-reason" type="text" class="swal2-input" />'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                preConfirm: () => {
                    const amountValue = document.getElementById('swal-adjust-amount').value;
                    const reasonValue = document.getElementById('swal-adjust-reason').value;

                    if (!amountValue || parseFloat(amountValue) === 0) {
                        Swal.showValidationMessage('Merci de saisir un montant non nul.');
                        return null;
                    }

                    if (!reasonValue) {
                        Swal.showValidationMessage('Merci de préciser la raison.');
                        return null;
                    }

                    return {
                        amount: parseFloat(amountValue),
                        reason: reasonValue,
                    };
                },
            });

            if (!result.isConfirmed) {
                return;
            }

            await this.applyChange(`/folios/${this.detailsFolio.id}/adjustment`, result.value, 'post');
        },
        async applyChange(url, payload, method = 'patch') {
            try {
                await axios[method](url, payload);
                await this.loadReservationDetails(this.selectedReservation.id);
                this.loadTab(this.activeTab, { preserveSelection: true });
            } catch (error) {
                Swal.fire('Erreur', error.response?.data?.message || 'Une erreur est survenue.', 'error');
            }
        },
        async openFolioModal(tab = 'charges') {
            if (!this.selectedReservation) {
                return;
            }

            this.folioLoading = true;
            this.folioInitialTab = tab || 'charges';

            try {
                const response = await axios.get(`/reservations/${this.selectedReservation.id}/folio`);
                this.folioData = response.data;
                this.showFolioModal = true;
            } finally {
                this.folioLoading = false;
            }
        },
        async refreshFolioData() {
            if (!this.folioData?.reservation?.id || !this.showFolioModal) {
                return;
            }

            const reservationId = this.folioData.reservation.id;
            const response = await axios.get(`/reservations/${reservationId}/folio`);
            this.folioData = response.data;
        },
        closeFolioModal() {
            this.showFolioModal = false;
            this.folioInitialTab = 'charges';
        },
        actionLabel(action) {
            const map = {
                confirm: 'Confirmer',
                check_in: 'Check-in',
                check_out: 'Check-out',
                cancel: 'Annuler',
                no_show: 'No-show',
            };

            return map[action] || action;
        },
        getActionText(action) {
            const map = {
                confirm: 'Confirmer la réservation ? ',
                check_in: 'Effectuer le check-in ? ',
                check_out: 'Effectuer le check-out ? ',
                cancel: 'Annuler la réservation ? ',
                no_show: 'Marquer en no-show ? ',
            };

            return map[action] || '';
        },
        hkBadge(status) {
            const map = {
                clean: { label: 'Propre', classes: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
                dirty: { label: 'Sale', classes: 'border-rose-200 bg-rose-50 text-rose-700' },
                inspected: { label: 'Inspectée', classes: 'border-indigo-200 bg-indigo-50 text-indigo-700' },
                out_of_order: { label: 'Hors service', classes: 'border-gray-200 bg-gray-50 text-gray-600' },
            };

            return map[status] || { label: '—', classes: 'border-gray-200 bg-gray-50 text-gray-600' };
        },
        formatDate(value) {
            if (!value) {
                return '—';
            }

            return value.toString().slice(0, 10);
        },
        formatMoney(amount, currency = 'XAF') {
            const value = Number(amount || 0);
            return `${value.toFixed(0)} ${currency}`;
        },
        toDateTimeLocal(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            const pad = (number) => String(number).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
    },
};
</script>
