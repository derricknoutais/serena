<template>
    <div class="space-y-6">
            <div class="flex flex-wrap gap-2 items-center justify-between w-full mb-4">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === 'arrivals' ? 'bg-serena-primary text-white' : 'bg-gray-100  text-gray-700 border border-gray-100 hover:bg-gray-200'"
                        @click="loadTab('arrivals')"
                    >
                        Arrivées du jour
                    </button>
                    <button
                        type="button"
                        class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === 'departures' ? 'bg-serena-primary text-white' : 'bg-gray-100  text-gray-700 border border-gray-100 hover:bg-gray-200'"
                        @click="loadTab('departures')"
                    >
                        Départs du jour
                    </button>
                    <button
                        type="button"
                        class="hover:cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="activeTab === 'inHouse' ? 'bg-serena-primary text-white' : 'bg-gray-100  text-gray-700 border border-gray-100 hover:bg-gray-200'"
                        @click="loadTab('inHouse')"
                    >
                        Clients en séjour
                    </button>
                </div>
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
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Client</th>
                                    <th class="px-4 py-2">Chambre / Type</th>
                                    <th class="px-4 py-2">Offre</th>
                                    <th class="px-4 py-2">Arrivée</th>
                                    <th class="px-4 py-2">Départ</th>
                                    <th class="px-4 py-2">Statut</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="reservation in currentList" :key="reservation.id">
                                    <td class="px-4 py-3 text-gray-900">
                                        {{ reservation.guest.name }}
                                        <div class="flex flex-col gap-1">
                                            <div>
                                                <span v-if="reservation.room.number">{{ reservation.room.number }}</span>
                                                <span v-else>{{ reservation.room.type || '—' }}</span>
                                            </div>
                                            <span
                                                class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                                :class="hkBadge(reservation.room.hk_status).classes"
                                            >
                                                {{ hkBadge(reservation.room.hk_status).label }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <span v-if="reservation.room.number">{{ reservation.room.number }}</span>
                                        <span v-else>{{ reservation.room.type || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ reservation.offer.name || '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ reservation.check_in_date || '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ reservation.check_out_date || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                            {{ reservation.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="action in reservation.actions"
                                                :key="action"
                                                type="button"
                                                class="rounded-lg bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                                @click="changeStatus(action, reservation.id)"
                                            >
                                                {{ actionLabel(action) }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'FrontdeskOperationsBoard',
    components: { },
    data() {
        return {
            activeTab: 'arrivals',
            arrivals: [],
            departures: [],
            inHouse: [],
            loading: false,
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

            return this.inHouse;
        },
        tabTitle() {
            const map = {
                arrivals: 'Arrivées du jour',
                departures: 'Départs du jour',
                inHouse: 'Clients en séjour',
            };

            return map[this.activeTab] || '';
        },
    },
    created() {
        this.loadTab('arrivals');
    },
    methods: {
        loadTab(tab) {
            this.activeTab = tab;
            this.loading = true;

            const endpointMap = {
                arrivals: '/frontdesk/arrivals',
                departures: '/frontdesk/departures',
                inHouse: '/frontdesk/in-house',
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
                })
                .finally(() => {
                    this.loading = false;
                });
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

            if (action === 'check_in') {
                const reservation = this.findReservation(reservationId);
                const hkStatus = reservation?.room?.hk_status ?? null;

                if (hkStatus && hkStatus !== 'inspected') {
                    const warning = await Swal.fire({
                        title: 'Chambre non prête',
                        text: 'Cette chambre n’est pas inspectée. Voulez-vous quand même effectuer le check-in ?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Oui',
                        cancelButtonText: 'Non',
                    });

                    if (!warning.isConfirmed) {
                        return;
                    }
                }
            }

            const result = await Swal.fire({
                title: 'Confirmer ?',
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
                    + '</div>'
                    + '<div class="mt-2 text-left">'
                    + '<label class="block text-xs font-semibold text-gray-600">Note (optionnelle)</label>'
                    + '<input id="swal-penalty-note" type="text" class="swal2-input" />'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Valider',
                cancelButtonText: 'Annuler',
                focusConfirm: false,
                preConfirm: () => {
                    const amountInput = document.getElementById('swal-penalty-amount');
                    const noteInput = document.getElementById('swal-penalty-note');
                    const amount = parseFloat((amountInput?.value ?? '0').toString());

                    if (Number.isNaN(amount) || amount < 0) {
                        Swal.showValidationMessage('Le montant doit être un nombre positif.');

                        return false;
                    }

                    return {
                        amount,
                        note: (noteInput?.value ?? '').toString(),
                    };
                },
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                this.sendStatusRequest(action, reservationId, {
                    penalty_amount: result.value?.amount ?? 0,
                    penalty_note: result.value?.note ?? '',
                });
            });
        },
        async promptActualDateTime(action, reservationId) {
            const isCheckIn = action === 'check_in';
            const title = isCheckIn ? 'Confirmer le check-in ?' : 'Confirmer le check-out ?';
            const label = isCheckIn ? 'Date et heure de check-in' : 'Date et heure de check-out';

            const defaultValue = new Date().toISOString().slice(0, 16);

            const { value, isConfirmed } = await Swal.fire({
                title,
                html:
                    '<div class="text-left">'
                    + `<label class="block text-xs font-semibold text-gray-600">${label}</label>`
                    + `<input id="swal-datetime" type="datetime-local" value="${defaultValue}" class="swal2-input" />`
                    + '<p class="mt-1 text-[11px] text-gray-500">Vous pouvez ajuster la date/heure réelle du check-in/out.</p>'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Valider',
                cancelButtonText: 'Annuler',
                focusConfirm: false,
                preConfirm: () => {
                    const input = document.getElementById('swal-datetime');
                    const datetime = (input?.value ?? '').toString();

                    if (!datetime) {
                        Swal.showValidationMessage('Veuillez saisir une date et heure valides.');

                        return false;
                    }

                    return datetime;
                },
            });

            if (!isConfirmed) {
                return;
            }

            const payload = isCheckIn
                ? { actual_check_in_at: value }
                : { actual_check_out_at: value };

            this.sendStatusRequest(action, reservationId, payload);
        },
        sendStatusRequest(action, reservationId, payload) {
            const url = `/reservations/${reservationId}/status`;
            const data = {
                action,
                ...(payload || {}),
            };

            this.$inertia.patch(
                url,
                data,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            timer: 1000,
                            showConfirmButton: false,
                        });
                        this.loadTab(this.activeTab);
                    },
                    onError: (errors) => {
                        const message =
                            (typeof errors === 'string'
                                ? errors
                                : Object.values(errors || {})[0])
                            || 'Erreur lors de la mise à jour.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: message,
                        });
                    },
                },
            );
        },
        findReservation(reservationId) {
            return [...this.arrivals, ...this.departures, ...this.inHouse].find(
                (reservation) => reservation.id === reservationId,
            );
        },
        hkBadge(status) {
            switch (status) {
                case 'dirty':
                    return {
                        label: 'Sale',
                        classes: 'border border-gray-300 bg-gray-50 text-gray-700',
                    };
                case 'cleaning':
                    return {
                        label: 'En cours',
                        classes: 'border border-blue-300 bg-blue-50 text-blue-700',
                    };
                case 'awaiting_inspection':
                    return {
                        label: 'En attente d’inspection',
                        classes: 'border border-teal-300 bg-teal-50 text-teal-700',
                    };
                case 'inspected':
                    return {
                        label: 'Inspectée',
                        classes: 'border border-emerald-300 bg-emerald-50 text-emerald-700',
                    };
                case 'redo':
                    return {
                        label: 'À refaire',
                        classes: 'border border-rose-300 bg-rose-50 text-rose-700',
                    };
                default:
                    return {
                        label: status ?? '—',
                        classes: 'border border-gray-200 bg-gray-50 text-gray-600',
                    };
            }
        },
        getActionText(action) {
            switch (action) {
                case 'confirm':
                    return 'Confirmer cette réservation ?';
                case 'check_in':
                    return 'Effectuer le check-in de ce client ?';
                case 'check_out':
                    return 'Effectuer le check-out de ce client ?';
                case 'cancel':
                    return 'Annuler cette réservation ?';
                case 'no_show':
                    return 'Marquer cette réservation comme no-show ?';
                default:
                    return 'Confirmer cette action ?';
            }
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
    },
};
</script>
