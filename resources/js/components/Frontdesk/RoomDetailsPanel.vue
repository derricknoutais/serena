<template>
    <div class="relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:sticky lg:top-6 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
        <div
            v-if="isLoading"
            class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/80"
        >
            <svg class="h-6 w-6 animate-spin text-serena-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </div>
        <h3 class="text-base font-semibold text-gray-800">
            Détails de la chambre
        </h3>
        <p class="text-sm text-gray-500">
            Sélectionnez une chambre dans le board pour voir les détails.
        </p>

        <div v-if="selectedRoom" class="mt-4 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl font-bold text-serena-text-main">
                        Chambre {{ selectedRoom.number }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ selectedRoom.room_type_name || 'Type inconnu' }} ·
                        Étage {{ selectedRoom.floor ?? '-' }}
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1 text-[10px]">
                    <span
                        v-if="availabilityBadge(selectedRoom)"
                        class="rounded-full border px-2 py-0.5 font-semibold"
                        :class="availabilityBadge(selectedRoom).classes"
                    >
                        {{ availabilityBadge(selectedRoom).label }}
                    </span>
                    <span
                        v-if="selectedRoom.maintenance_blocking_count > 0"
                        class="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 font-semibold text-rose-700"
                    >
                        Bloque vente
                    </span>
                    <span
                        v-if="hkBadge(selectedRoom)"
                        class="rounded-full border px-2 py-0.5 font-semibold"
                        :class="hkBadge(selectedRoom).classes"
                    >
                        <component
                            v-if="hkBadge(selectedRoom).icon"
                            :is="hkBadge(selectedRoom).icon"
                            class="mr-1 inline-block h-3 w-3"
                            :class="hkBadge(selectedRoom).iconClass"
                        />
                        {{ hkBadge(selectedRoom).label }}
                    </span>
                    <span
                        v-if="selectedRoom.maintenance_ticket"
                        class="rounded-full border px-2 py-0.5 font-semibold"
                        :class="maintenanceBadge(selectedRoom.maintenance_ticket).classes"
                    >
                        {{ maintenanceBadge(selectedRoom.maintenance_ticket).label }}
                    </span>
                    <span
                        v-if="selectedRoom.pending_sync"
                        class="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                    >
                        Sync en attente
                    </span>
                </div>
            </div>

            <div
                v-if="selectedRoom.current_reservation"
                class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs"
            >
                <div class="mb-1 flex items-center justify-between">
                    <span class="font-semibold text-gray-800">Réservation en cours</span>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold">
                        {{ selectedRoom.current_reservation.status }}
                    </span>
                </div>
                <div
                    v-if="selectedRoom.current_reservation.is_overstay"
                    class="mb-2 rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                >
                    Séjour dépassé
                </div>
                <div class="space-y-1">
                    <div>
                        <span class="font-semibold text-gray-700">Code :</span>
                        <span class="ml-1">{{ selectedRoom.current_reservation.code }}</span>
                    </div>
                    <div v-if="selectedRoom.current_reservation.guest_name">
                        <span class="font-semibold text-gray-700">Client :</span>
                        <span class="ml-1">
                            {{ selectedRoom.current_reservation.guest_name }}
                        </span>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-700">Séjour :</span>
                        <span class="ml-1">
                            {{ formatDateTime(selectedRoom.current_reservation.check_in_at || selectedRoom.current_reservation.check_in_date) }} →
                            {{ formatDateTime(selectedRoom.current_reservation.check_out_at || selectedRoom.current_reservation.check_out_date) }}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-3 w-full rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                    @click="onViewReservation(selectedRoom)"
                >
                    Voir la réservation
                </button>
            </div>

            <div
                v-if="canManageHousekeepingActions"
                class="space-y-2"
            >
                <h4 class="text-xs font-semibold text-gray-700">
                    Actions
                </h4>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm"
                        :class="canOpenWalkIn
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'cursor-not-allowed border border-indigo-200 bg-indigo-50 text-indigo-400'"
                        :disabled="!canOpenWalkIn"
                        @click="onOpenWalkIn(selectedRoom)"
                    >
                        Nouvelle réservation / Check-in rapide
                    </button>

                    <button
                        v-if="selectedRoom && selectedRoom.current_reservation"
                        type="button"
                        class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="folioLoading"
                        @click="onOpenFolio('payments')"
                    >
                        {{ folioLoading ? 'Ouverture du folio...' : 'Encaisser / Folio' }}
                    </button>
                </div>
                <p
                    v-if="!canOpenWalkIn && walkInBlockers.length"
                    class="text-[11px] text-amber-700"
                >
                    Impossible pour le moment : {{ walkInBlockers.join(' ') }}
                </p>

                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-if="canMarkDirty"
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                        @click="onUpdateRoomHkStatus(selectedRoom.id, 'dirty')"
                    >
                        Marquer comme sale
                    </button>
                </div>
            </div>

            <div
                v-if="selectedRoom.current_reservation"
                class="space-y-2"
            >
                <h4 class="text-xs font-semibold text-gray-700">
                    Statut & séjour
                </h4>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="selectedRoom.current_reservation.status === 'pending'"
                        type="button"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('confirm')"
                    >
                        Confirmer
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'pending'"
                        type="button"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('cancel')"
                    >
                        Annuler
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('check_in')"
                    >
                        Check-in
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('cancel')"
                    >
                        Annuler
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('no_show')"
                    >
                        No-show
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'in_house'"
                        type="button"
                        class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-gray-900 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="onChangeStatus('check_out')"
                    >
                        Check-out
                    </button>
                    <button
                        v-if="canExtendStayAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="onOpenStayModal('extend')"
                    >
                        Prolonger
                    </button>
                    <button
                        v-if="canShortenStayAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="onOpenStayModal('shorten')"
                    >
                        Raccourcir
                    </button>
                    <button
                        v-if="canChangeRoomAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="onOpenChangeRoomModal"
                    >
                        Changer de chambre
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-gray-700">
                        Inspection
                    </h4>
                    <span
                        v-if="selectedRoom?.last_inspection?.outcome"
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="inspectionOutcomeClasses(selectedRoom.last_inspection.outcome)"
                    >
                        {{ inspectionOutcomeLabel(selectedRoom.last_inspection.outcome) }}
                    </span>
                </div>
                <p v-if="selectedRoom?.last_inspection?.ended_at" class="mt-1 text-[11px] text-gray-500">
                    Dernière inspection : {{ formatDateTime(selectedRoom.last_inspection.ended_at) }}
                </p>
                <p v-else class="mt-1 text-[11px] text-gray-400">
                    Aucune inspection enregistrée.
                </p>
                <div
                    v-if="selectedRoom?.last_inspection?.remarks?.length"
                    class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2 text-[11px] text-rose-800"
                >
                    <p class="font-semibold">Remarques (à refaire)</p>
                    <ul class="mt-1 space-y-1">
                        <li
                            v-for="(remark, idx) in selectedRoom.last_inspection.remarks"
                            :key="idx"
                        >
                            <span v-if="remark.label" class="font-semibold">{{ remark.label }} :</span>
                            <span>{{ remark.note }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="space-y-3 rounded-lg border border-amber-100 bg-amber-50/40 p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-semibold text-amber-900">
                            Maintenance
                        </h4>
                        <p
                            v-if="selectedRoomMaintenanceTickets.length"
                            class="text-[11px] text-amber-700"
                        >
                            {{ selectedRoomMaintenanceTickets.length }} ticket(s) ouvert(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="canReportMaintenance"
                            type="button"
                            class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50"
                            @click="onOpenMaintenanceModal(selectedRoom)"
                        >
                            Déclarer un problème
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50"
                            @click="onGoToMaintenance(selectedRoom)"
                        >
                            Voir dans Maintenance
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-white/80 p-3 text-xs text-amber-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">Tickets ouverts :</span>
                        <span>{{ selectedRoomMaintenanceTickets.length }}</span>
                        <span
                            v-if="selectedRoom.maintenance_blocking_count > 0"
                            class="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
                        >
                            Bloque vente
                        </span>
                    </div>
                    <ul v-if="selectedRoomMaintenanceTickets.length" class="mt-2 space-y-1 text-[11px]">
                        <li
                            v-for="ticket in selectedRoomMaintenanceTickets.slice(0, 2)"
                            :key="ticket.id"
                            class="flex items-center gap-2"
                        >
                            <span class="font-semibold">•</span>
                            <span class="truncate">{{ ticket.title }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-[11px] text-amber-800">
                        Aucun ticket de maintenance actif pour cette chambre.
                    </p>
                </div>
            </div>

            <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-gray-700">
                        Historique de la chambre
                    </h4>
                    <button
                        type="button"
                        class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700"
                        @click="onLoadRoomActivity"
                    >
                        Actualiser
                    </button>
                </div>
                <div v-if="roomActivityLoading" class="text-[11px] text-gray-500">
                    Chargement de l’historique…
                </div>
                <div
                    v-else-if="roomActivity.length === 0"
                    class="text-[11px] text-gray-400"
                >
                    Aucune activité récente sur cette chambre.
                </div>
                <ul
                    v-else
                    class="max-h-36 space-y-1 overflow-y-auto text-[11px] text-gray-700"
                >
                    <li
                        v-for="entry in roomActivity"
                        :key="entry.id"
                        class="flex items-start justify-between gap-2"
                    >
                        <div>
                            <p class="font-medium text-gray-800">
                                {{ roomActivityLabel(entry) }}
                            </p>
                            <p
                                v-if="entry.properties?.room_number"
                                class="text-[10px] text-gray-500"
                            >
                                Chambre {{ entry.properties.room_number }}
                            </p>
                        </div>
                        <span class="whitespace-nowrap text-[10px] text-gray-400">
                            {{ entry.created_at }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
import { AlertTriangle, CheckCircle, ShieldCheck, Wrench } from 'lucide-vue-next';

export default {
    name: 'RoomDetailsPanel',
    props: {
        selectedRoom: {
            type: Object,
            default: null,
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
        folioLoading: {
            type: Boolean,
            default: false,
        },
        statusSubmitting: {
            type: Boolean,
            default: false,
        },
        canManageHousekeepingActions: {
            type: Boolean,
            default: false,
        },
        canMarkDirty: {
            type: Boolean,
            default: false,
        },
        canReportMaintenance: {
            type: Boolean,
            default: false,
        },
        canExtendStayAction: {
            type: Boolean,
            default: false,
        },
        canShortenStayAction: {
            type: Boolean,
            default: false,
        },
        canChangeRoomAction: {
            type: Boolean,
            default: false,
        },
        roomActivity: {
            type: Array,
            default: () => [],
        },
        roomActivityLoading: {
            type: Boolean,
            default: false,
        },
        formatDateTime: {
            type: Function,
            required: true,
        },
        onOpenWalkIn: {
            type: Function,
            default: () => {},
        },
        onOpenFolio: {
            type: Function,
            default: () => {},
        },
        onViewReservation: {
            type: Function,
            default: () => {},
        },
        onUpdateRoomHkStatus: {
            type: Function,
            default: () => {},
        },
        onChangeStatus: {
            type: Function,
            default: () => {},
        },
        onOpenStayModal: {
            type: Function,
            default: () => {},
        },
        onOpenChangeRoomModal: {
            type: Function,
            default: () => {},
        },
        onOpenMaintenanceModal: {
            type: Function,
            default: () => {},
        },
        onGoToMaintenance: {
            type: Function,
            default: () => {},
        },
        onLoadRoomActivity: {
            type: Function,
            default: () => {},
        },
    },
    computed: {
        selectedRoomMaintenanceTickets() {
            if (!this.selectedRoom) {
                return [];
            }

            if (Array.isArray(this.selectedRoom.maintenance_tickets)) {
                return this.selectedRoom.maintenance_tickets;
            }

            return this.selectedRoom.maintenance_ticket ? [this.selectedRoom.maintenance_ticket] : [];
        },
        walkInBlockers() {
            if (!this.selectedRoom) {
                return [];
            }

            const blockers = [];

            if (this.selectedRoom.current_reservation) {
                blockers.push('Une réservation est déjà en cours.');
            }

            if (this.selectedRoom.ui_status === 'occupied') {
                blockers.push('La chambre est occupée.');
            }

            if (this.selectedRoom.status === 'out_of_order' || this.selectedRoom.ui_status === 'out_of_order') {
                blockers.push('La chambre est hors service.');
            }

            if (this.selectedRoom.status === 'inactive') {
                blockers.push('La chambre est inactive.');
            }

            if (this.selectedRoom.is_sellable === false) {
                blockers.push('La chambre est non vendable.');
            }

            if ((this.selectedRoom.maintenance_blocking_count ?? 0) > 0) {
                blockers.push('La maintenance bloque la vente.');
            }

            if (this.selectedRoom.hk_status !== 'inspected') {
                blockers.push(
                    `Inspection requise (statut ménage : ${this.hkStatusLabel(this.selectedRoom.hk_status)}).`,
                );
            }

            return blockers;
        },
        canOpenWalkIn() {
            return Boolean(this.selectedRoom && this.walkInBlockers.length === 0);
        },
    },
    methods: {
        availabilityBadge(room) {
            if (room.ui_status === 'occupied') {
                return {
                    label: 'Occupée',
                    classes: 'bg-red-100 text-red-700 border-red-300',
                };
            }

            if (room.status === 'out_of_order' || room.ui_status === 'out_of_order') {
                return {
                    label: 'Hors service',
                    classes: 'bg-black text-white border-black',
                };
            }

            if (room.status === 'inactive') {
                return {
                    label: 'Inactive',
                    classes: 'bg-neutral-100 text-neutral-500 border-neutral-300',
                };
            }

            if (room.is_sellable === false) {
                return {
                    label: 'Non vendable',
                    classes: 'bg-rose-100 text-rose-700 border-rose-300',
                };
            }

            return {
                label: 'Disponible',
                classes: 'bg-emerald-100 text-emerald-700 border-emerald-300',
            };
        },
        hkStatusLabel(status) {
            switch (status) {
                case 'dirty':
                    return 'Sale';
                case 'cleaning':
                    return 'En cours';
                case 'awaiting_inspection':
                    return 'En attente d’inspection';
                case 'inspected':
                    return 'Inspectée';
                case 'in_use':
                    return 'En usage';
                case 'redo':
                    return 'À refaire';
                default:
                    return status ?? '—';
            }
        },
        hkBadge(room) {
            switch (room.hk_status) {
                case 'maintenance':
                    return {
                        label: 'Maintenance',
                        classes: 'bg-slate-100 text-slate-700 border-slate-300',
                        icon: Wrench,
                        iconClass: 'text-slate-600',
                    };
                case 'cleaning':
                    return {
                        label: 'En cours',
                        classes: 'bg-blue-100 text-blue-700 border-blue-300',
                        icon: CheckCircle,
                        iconClass: 'text-blue-600',
                    };
                case 'awaiting_inspection':
                    return {
                        label: 'En attente d’inspection',
                        classes: 'bg-teal-100 text-teal-700 border-teal-300',
                        icon: ShieldCheck,
                        iconClass: 'text-teal-600',
                    };
                case 'inspected':
                    return {
                        label: 'Inspectée',
                        classes: 'bg-emerald-100 text-emerald-700 border-emerald-300',
                        icon: ShieldCheck,
                        iconClass: 'text-emerald-600',
                    };
                case 'in_use':
                    return {
                        label: 'En usage',
                        classes: 'bg-amber-100 text-amber-700 border-amber-300',
                        icon: ShieldCheck,
                        iconClass: 'text-amber-600',
                    };
                case 'dirty':
                    return {
                        label: 'Sale',
                        classes: 'bg-gray-100 text-gray-700 border-gray-300',
                        icon: AlertTriangle,
                        iconClass: 'text-gray-600',
                    };
                case 'redo':
                    return {
                        label: 'À refaire',
                        classes: 'bg-rose-100 text-rose-700 border-rose-300',
                        icon: AlertTriangle,
                        iconClass: 'text-rose-600',
                    };
                default:
                    return {
                        label: room.hk_status ?? '—',
                        classes: 'bg-gray-100 text-gray-600 border-gray-300',
                        icon: null,
                        iconClass: '',
                    };
            }
        },
        inspectionOutcomeLabel(outcome) {
            switch (outcome) {
                case 'passed':
                    return 'Validée';
                case 'failed':
                    return 'À refaire';
                default:
                    return 'Inconnue';
            }
        },
        inspectionOutcomeClasses(outcome) {
            switch (outcome) {
                case 'passed':
                    return 'bg-emerald-100 text-emerald-700';
                case 'failed':
                    return 'bg-rose-100 text-rose-700';
                default:
                    return 'bg-gray-100 text-gray-600';
            }
        },
        maintenanceBadge(ticket) {
            if (!ticket) {
                return {
                    label: '',
                    classes: '',
                    dotClass: '',
                };
            }

            switch (ticket.severity) {
                case 'critical':
                    return {
                        label: 'Maintenance critique',
                        classes: 'border-rose-400 bg-rose-50 text-rose-800',
                        dotClass: 'bg-rose-500',
                    };
                case 'high':
                    return {
                        label: 'Maintenance critique',
                        classes: 'border-red-300 bg-red-50 text-red-700',
                        dotClass: 'bg-red-500',
                    };
                case 'medium':
                    return {
                        label: 'Maintenance en cours',
                        classes: 'border-amber-300 bg-amber-50 text-amber-800',
                        dotClass: 'bg-amber-500',
                    };
                case 'low':
                default:
                    return {
                        label: 'Maintenance',
                        classes: 'border-gray-300 bg-gray-50 text-gray-700',
                        dotClass: 'bg-gray-500',
                    };
            }
        },
        roomActivityLabel(entry) {
            const event = entry.event || entry.description || '';

            switch (event) {
                case 'hk_updated': {
                    const fromStatus = entry.properties?.from_hk_status;
                    const toStatus = entry.properties?.to_hk_status;
                    const remarks = entry.properties?.remarks;
                    const label = `${this.hkStatusLabel(fromStatus)} → ${this.hkStatusLabel(toStatus)}`;

                    if (remarks) {
                        return `Ménage: ${label} · ${remarks}`;
                    }

                    return `Ménage: ${label}`;
                }
                default:
                    return entry.action_label_fr || entry.description || 'Action';
            }
        },
    },
};
</script>
