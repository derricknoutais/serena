<template>
    <div class="space-y-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-serena-text-main">
                Room Board
            </h1>

            <div class="flex items-center space-x-3">
                <input
                    type="date"
                    v-model="currentDate"
                    @change="onDateChange"
                    class="rounded-lg border border-serena-border bg-white px-3 py-1 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                />
                <PrimaryButton
                    type="button"
                    class="px-4 py-1 text-sm"
                    @click="goToday"
                >
                    Today
                </PrimaryButton>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-serena-border bg-white px-4 py-2 text-sm text-serena-text-muted">
            <span class="font-semibold text-serena-text-main">Statut ménage :</span>
            <button
                v-for="option in hkFilterOptions"
                :key="option.value"
                type="button"
                class="rounded-full px-3 py-1 text-xs font-semibold transition"
                :class="hkFilter === option.value ? 'bg-serena-primary text-white' : 'bg-serena-primary-soft text-serena-primary'"
                @click="hkFilter = option.value"
            >
                {{ option.label }}
            </button>
        </div>

        <div v-if="!filteredRoomsByFloor.length" class="rounded-xl bg-serena-card p-6 text-sm text-serena-text-muted shadow-sm">
            Aucune chambre ne correspond à ce filtre.
        </div>

        <div v-else class="flex flex-col gap-4 lg:flex-row">
            <div class="lg:w-2/3">
                <div
                    v-for="(rooms, index) in filteredRoomsByFloor"
                    :key="index"
                    class="mb-8"
                >
                    <h2 class="mb-3 text-lg font-medium text-serena-text-main">
                        Floor {{ rooms[0]?.floor ?? '-' }}
                    </h2>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="room in rooms"
                    :key="room.id"
                    :class="roomClasses(room)"
                    class="relative"
                    @click="selectRoom(room)"
                >
                    <div
                        v-if="loadingRoomId === room.id"
                        class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/80"
                    >
                        <svg class="h-6 w-6 animate-spin text-serena-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>
                    <div class="mb-1 flex items-center justify-between">
                        <div class="text-lg font-semibold text-serena-text-main">
                            {{ room.number }}
                        </div>
                    </div>
                            <div class="mb-2 text-sm text-serena-text-muted">
                                {{ room.room_type_name || 'Type inconnu' }}
                            </div>

                            <div class="flex flex-wrap gap-2 text-[10px]">
                                <span
                                    class="rounded-full border px-2 py-0.5 font-medium"
                                    :class="availabilityBadge(room).classes"
                                >
                                    {{ availabilityBadge(room).label }}
                                </span>
                                <span
                                    v-if="room.maintenance_blocking_count > 0"
                                    class="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 font-medium text-rose-700"
                                >
                                    Bloque vente
                                </span>
                                <span
                                    class="rounded-full border px-2 py-0.5 font-medium"
                                    :class="hkBadge(room).classes"
                                >
                                    <component
                                        v-if="hkBadge(room).icon"
                                        :is="hkBadge(room).icon"
                                        class="mr-1 inline-block h-3 w-3"
                                        :class="hkBadge(room).iconClass"
                                    />
                                    {{ hkBadge(room).label }}
                                </span>
                                <span
                                    v-if="room.hk_priority"
                                    class="rounded-full border px-2 py-0.5 font-medium"
                                    :class="priorityBadge(room.hk_priority).classes"
                                >
                                    <component
                                        v-if="priorityBadge(room.hk_priority).icon"
                                        :is="priorityBadge(room.hk_priority).icon"
                                        class="mr-1 inline-block h-3 w-3"
                                        :class="priorityBadge(room.hk_priority).iconClass"
                                    />
                                    {{ priorityBadge(room.hk_priority).label }}
                                </span>
                                <span
                                    v-if="room.current_reservation?.is_overstay"
                                    class="rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 font-medium text-amber-700"
                                >
                                    Séjour dépassé
                                </span>
                                <span
                                    v-if="room.pending_sync"
                                    class="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 font-semibold text-amber-700"
                                >
                                    Sync en attente
                                </span>
                            </div>

                            <div
                                v-if="room.maintenance_ticket"
                                class="mt-2 text-[10px]"
                            >
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-semibold"
                                    :class="maintenanceBadge(room.maintenance_ticket).classes"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="maintenanceBadge(room.maintenance_ticket).dotClass"
                                    />
                                    {{ maintenanceBadge(room.maintenance_ticket).label }}
                                </span>
                            </div>


                            <div
                                v-if="room.current_reservation"
                                class="mt-3 space-y-1 text-xs text-serena-text-muted"
                            >
                                <p class="font-medium text-serena-text-main">
                                    {{ room.current_reservation.guest_name || 'Réservation' }}
                                </p>
                                <p>
                                    {{ formatDateTime(room.current_reservation.check_in_at || room.current_reservation.check_in_date) }} →
                                    {{ formatDateTime(room.current_reservation.check_out_at || room.current_reservation.check_out_date) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:w-1/3">
                <div class="relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:sticky lg:top-6 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                    <div
                        v-if="selectedRoom && loadingRoomId === selectedRoom.id"
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

                        <div class="space-y-1 text-xs text-gray-600">
                            <div>
                                <span class="font-semibold text-gray-700">Statut inventaire :</span>
                                <span class="ml-1">{{ selectedRoom.status }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Statut ménage :</span>
                                <span class="ml-1">{{ hkStatusLabel(selectedRoom.hk_status) }}</span>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Vente :</span>
                                <span class="ml-1">
                                    {{ selectedRoom.is_sellable ? 'Vendable' : 'Bloquée' }}
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
                                @click="viewCurrentReservation(selectedRoom)"
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
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    @click="openWalkInForRoom(selectedRoom)"
                                >
                                    Nouvelle réservation / Check-in rapide
                                </button>

                                <button
                                    v-if="selectedRoom && selectedRoom.current_reservation"
                                    type="button"
                                    class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="folioLoading"
                                    @click="openFolioFromRoom('payments')"
                                >
                                    {{ folioLoading ? 'Ouverture du folio...' : 'Encaisser / Folio' }}
                                </button>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <!-- <button
                                    v-if="canMarkInspected"
                                    type="button"
                                    class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'inspected')"
                                >
                                    Marquer comme inspectée
                                </button> -->

                                <button
                                    v-if="canMarkDirty"
                                    type="button"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'dirty')"
                                >
                                    Marquer comme sale
                                </button>
                                <!-- <button
                                    v-if="canMarkClean"
                                    type="button"
                                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'cleaning')"
                                >
                                    Marquer en cours
                                </button>
                                <button
                                    v-if="canMarkClean"
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'awaiting_inspection')"
                                >
                                    En attente d’inspection
                                </button>
                                <button
                                    v-if="canMarkDirty"
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'redo')"
                                >
                                    Marquer à refaire
                                </button> -->
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
                                <button
                                    v-if="canReportMaintenance"
                                    type="button"
                                    class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50"
                                    @click="openMaintenanceModal(selectedRoom)"
                                >
                                    Déclarer un problème
                                </button>
                            </div>

                            <div
                                v-if="selectedRoomMaintenanceTickets.length"
                                class="space-y-3 text-xs text-amber-900"
                            >
                                <div
                                    v-for="ticket in selectedRoomMaintenanceTickets"
                                    :key="ticket.id"
                                    class="rounded-lg border border-amber-200 bg-white/80 p-3"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold">Statut :</span>
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-900">
                                            {{ maintenanceStatusLabel(ticket.status) }}
                                        </span>
                                        <span class="font-semibold">Sévérité :</span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            :class="maintenanceBadge(ticket).classes"
                                        >
                                            {{ maintenanceSeverityLabel(ticket.severity) }}
                                        </span>
                                        <span
                                            v-if="ticket.blocks_sale"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
                                        >
                                            Bloque vente
                                        </span>
                                    </div>
                                    <div class="text-[11px]">
                                        <span class="font-semibold">Titre :</span>
                                        <span class="ml-1">{{ ticket.title }}</span>
                                    </div>
                                    <div
                                        v-if="ticket.description"
                                        class="text-[11px]"
                                    >
                                        <span class="font-semibold">Description :</span>
                                        <span class="ml-1">{{ ticket.description }}</span>
                                    </div>
                                    <div class="text-[11px]">
                                        <span class="font-semibold">Déclaré par :</span>
                                        <span class="ml-1">{{ ticket.reported_by?.name || 'N/A' }}</span>
                                    </div>
                                    <div class="text-[11px]">
                                        <span class="font-semibold">Assigné à :</span>
                                        <span class="ml-1">
                                            {{ ticket.assigned_to?.name || 'Non assigné' }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="canOverrideMaintenanceBlocks"
                                        class="pt-2"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-lg border px-3 py-1 text-[11px] font-semibold"
                                            :class="ticket.blocks_sale ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-300 bg-white text-slate-700'"
                                            :disabled="maintenanceStatusSubmitting"
                                            @click="toggleMaintenanceBlocksSale(ticket)"
                                        >
                                            {{ ticket.blocks_sale ? 'Ne bloque pas la vente' : 'Marquer bloquant' }}
                                        </button>
                                    </div>

                                    <div
                                        v-if="canProgressMaintenance || canHandleMaintenance"
                                        class="flex flex-wrap gap-2 pt-2"
                                    >
                                        <button
                                            v-if="canHandleMaintenance && (!ticket.assigned_to || ticket.assigned_to.id !== currentUserId)"
                                            type="button"
                                            class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-60"
                                            :disabled="maintenanceStatusSubmitting"
                                            @click="assignMaintenanceToSelf(ticket)"
                                        >
                                            Me l'assigner
                                        </button>
                                        <button
                                            v-if="canProgressMaintenance && ticket.status === 'open'"
                                            type="button"
                                            class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-60"
                                            :disabled="maintenanceStatusSubmitting"
                                            @click="updateMaintenanceStatus(ticket, 'in_progress')"
                                        >
                                            Mettre en cours
                                        </button>
                                        <button
                                            v-if="canHandleMaintenance && ['open', 'in_progress'].includes(ticket.status)"
                                            type="button"
                                            class="rounded-lg border border-green-300 bg-green-50 px-3 py-1 text-[11px] font-semibold text-green-700 hover:bg-green-100 disabled:opacity-60"
                                            :disabled="maintenanceStatusSubmitting"
                                            @click="updateMaintenanceStatus(ticket, 'resolved')"
                                        >
                                            Résoudre
                                        </button>
                                        <button
                                            v-if="canHandleMaintenance && ticket.status !== 'closed'"
                                            type="button"
                                            class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                                            :disabled="maintenanceStatusSubmitting"
                                            @click="updateMaintenanceStatus(ticket, 'closed')"
                                        >
                                            Clôturer
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="text-xs text-amber-900"
                            >
                                Aucun ticket de maintenance actif pour cette chambre.
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
                                    @click="changeStatus('confirm')"
                                >
                                    Confirmer
                                </button>
                                <button
                                    v-if="selectedRoom.current_reservation.status === 'pending'"
                                    type="button"
                                    class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                                    :disabled="statusSubmitting"
                                    @click="changeStatus('cancel')"
                                >
                                    Annuler
                                </button>
                                <button
                                    v-if="selectedRoom.current_reservation.status === 'confirmed'"
                                    type="button"
                                    class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-60"
                                    :disabled="statusSubmitting"
                                    @click="changeStatus('check_in')"
                                >
                                    Check-in
                                </button>
                                <button
                                    v-if="selectedRoom.current_reservation.status === 'confirmed'"
                                    type="button"
                                    class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                                    :disabled="statusSubmitting"
                                    @click="changeStatus('cancel')"
                                >
                                    Annuler
                                </button>
                                <button
                                    v-if="selectedRoom.current_reservation.status === 'confirmed'"
                                    type="button"
                                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-60"
                                    :disabled="statusSubmitting"
                                    @click="changeStatus('no_show')"
                                >
                                    No-show
                                </button>
                                <button
                                    v-if="selectedRoom.current_reservation.status === 'in_house'"
                                    type="button"
                                    class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-gray-900 disabled:opacity-60"
                                    :disabled="statusSubmitting"
                                    @click="changeStatus('check_out')"
                                >
                                    Check-out
                                </button>
                                <button
                                    v-if="canExtendStayAction"
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    @click="openStayModal('extend')"
                                >
                                    Prolonger
                                </button>
                                <button
                                    v-if="canShortenStayAction"
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    @click="openStayModal('shorten')"
                                >
                                    Raccourcir
                                </button>
                                <button
                                    v-if="canChangeRoomAction"
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    @click="openChangeRoomModal"
                                >
                                    Changer de chambre
                                </button>
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
                                    @click="loadRoomActivity"
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
            </div>
        </div>

        <div
            v-if="isWalkInOpen && walkInRoom && walkInRoomType && walkInDefaultDates"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4"
        >
            <div
                class="w-full max-w-3xl rounded-xl border border-serena-border/30 bg-serena-card p-6 shadow-lg"
            >
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-serena-text-main">
                            Walk-In Reservation
                        </h2>
                        <p class="text-xs text-serena-text-muted">
                            Créez une réservation immédiate pour la chambre sélectionnée.
                        </p>
                    </div>
                    <SecondaryButton
                        type="button"
                        class="h-8 px-3 py-1 text-xs"
                        @click="closeWalkIn"
                    >
                        Fermer
                    </SecondaryButton>
                </div>

                <div class="mb-4 grid gap-4 rounded-lg bg-serena-bg-soft/60 p-4 text-sm md:grid-cols-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-serena-text-muted">
                            Chambre
                        </p>
                        <p class="mt-1 text-base font-semibold text-serena-text-main">
                            {{ walkInRoom.number }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-serena-text-muted">
                            Type
                        </p>
                        <p class="mt-1 text-sm text-serena-text-main">
                            {{ walkInRoom.room_type_name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-serena-text-muted">
                            Capacité
                        </p>
                        <p class="mt-1 text-sm text-serena-text-main">
                            {{ walkInRoomType.capacity_adults }} adultes,
                            {{ walkInRoomType.capacity_children }} enfants
                        </p>
                    </div>
                </div>

                <form v-if="form" @submit.prevent="submitWalkIn" class="space-y-6">
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            Client
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    for="walk_in_guest"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Client
                                </label>
                                <Multiselect
                                    id="walk_in_guest"
                                    v-model="selectedWalkInGuest"
                                    :options="localGuests"
                                    track-by="id"
                                    label="full_name"
                                    placeholder="Sélectionner ou saisir un client"
                                    :taggable="true"
                                    @search-change="onWalkInGuestSearchChange"
                                    @tag="onWalkInGuestTag"
                                    class="mt-1"
                                />
                            </div>
                            <div />
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            Séjour
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    for="offer_id"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Offre
                                </label>
                                <Multiselect
                                    id="offer_id"
                                    v-model="selectedWalkInOffer"
                                    :options="walkInOffers"
                                    track-by="id"
                                    :custom-label="walkInOfferLabel"
                                    placeholder="Choisir une offre"
                                    class="mt-1"
                                />
                                <p
                                    v-if="form.errors.offer_id"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ form.errors.offer_id }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="check_in_at"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Arrivée
                                </label>
                                <input
                                    id="check_in_at"
                                    v-model="form.check_in_at"
                                    type="datetime-local"
                                    @input="onWalkInArrivalChange"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                            </div>
                            <div>
                                <label
                                    for="check_out_at"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Départ
                                </label>
                                <input
                                    id="check_out_at"
                                    v-model="form.check_out_at"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                            </div>
                            <div>
                                <TextInput
                                    id="amount_received"
                                    v-model="form.amount_received"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    label="Montant perçu"
                                />
                                <p
                                    v-if="form.errors.amount_received"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ form.errors.amount_received }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="payment_method_id"
                                    class="mb-1 block text-xs font-medium text-serena-text-muted"
                                >
                                    Mode de paiement
                                </label>
                                <Multiselect
                                    id="payment_method_id"
                                    v-model="selectedWalkInPaymentMethod"
                                    :options="paymentMethodOptions()"
                                    track-by="id"
                                    label="name"
                                    placeholder="Choisir un mode de paiement"
                                    class="mt-1"
                                />
                                <p
                                    v-if="form.errors.payment_method_id"
                                    class="mt-1 text-xs text-serena-danger"
                                >
                                    {{ form.errors.payment_method_id }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            class="px-4 py-2 text-sm"
                            @click="closeWalkIn"
                        >
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            class="px-6 py-2 text-sm"
                            :disabled="form.processing"
                        >
                            Confirmer le Walk-In
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div
        v-if="showReservationModal && selectedRoom && selectedRoom.current_reservation"
        class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
        @click.self="closeReservationModal"
    >
        <div class="w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Réservation {{ selectedRoom.current_reservation.code }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        Chambre {{ selectedRoom.number }} · {{ selectedRoom.room_type_name || 'Type inconnu' }}
                    </p>
                </div>
                <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="closeReservationModal">
                    Fermer
                </button>
            </div>

            <div class="space-y-3 text-sm text-gray-700">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="statusClass(selectedRoom.current_reservation.status)"
                    >
                        {{ statusLabel(selectedRoom.current_reservation.status) }}
                    </span>
                    <span
                        v-if="selectedRoom.current_reservation.is_overstay"
                        class="rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                    >
                        Séjour dépassé
                    </span>
                </div>

                <div v-if="selectedRoom.current_reservation.guest_name">
                    <p class="text-xs font-semibold text-gray-500">Client</p>
                    <p class="text-sm text-gray-800">{{ selectedRoom.current_reservation.guest_name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Arrivée</p>
                        <p>{{ formatDateTime(selectedRoom.current_reservation.check_in_at || selectedRoom.current_reservation.check_in_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Départ</p>
                        <p>{{ formatDateTime(selectedRoom.current_reservation.check_out_at || selectedRoom.current_reservation.check_out_date) }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="selectedRoom.current_reservation.status === 'pending'"
                        type="button"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('confirm')"
                    >
                        Confirmer
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'pending'"
                        type="button"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('cancel')"
                    >
                        Annuler
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('check_in')"
                    >
                        Check-in
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('cancel')"
                    >
                        Annuler
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'confirmed'"
                        type="button"
                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('no_show')"
                    >
                        No-show
                    </button>
                    <button
                        v-if="selectedRoom.current_reservation.status === 'in_house'"
                        type="button"
                        class="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-gray-900 disabled:opacity-60"
                        :disabled="statusSubmitting"
                        @click="changeStatus('check_out')"
                    >
                        Check-out
                    </button>
                </div>

                <button
                    type="button"
                    class="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-70"
                    :disabled="folioLoading"
                    @click="openFolioFromRoom('payments')"
                >
                    {{ folioLoading ? 'Ouverture du folio...' : 'Encaisser / Folio' }}
                </button>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canExtendStayAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="openStayModal('extend')"
                    >
                        Prolonger
                    </button>
                    <button
                        v-if="canShortenStayAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="openStayModal('shorten')"
                    >
                        Raccourcir
                    </button>
                    <button
                        v-if="canChangeRoomAction"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        @click="openChangeRoomModal"
                    >
                        Changer de chambre
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="showStayModal && selectedRoom && selectedRoom.current_reservation"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ stayModalTitle }}
                    </h3>
                    <p class="text-xs text-gray-500">
                        Chambre {{ selectedRoom.number }} · Réservation {{ selectedRoom.current_reservation.code }}
                    </p>
                </div>
                <button type="button" class="text-sm text-gray-500" @click="closeStayModal">
                    Fermer
                </button>
            </div>

            <div class="space-y-3 text-sm text-gray-700">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Arrivée</p>
                        <p>{{ formatDateTime(selectedRoom.current_reservation.check_in_at || selectedRoom.current_reservation.check_in_date) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Départ actuel</p>
                        <p>{{ formatDateTime(selectedRoom.current_reservation.check_out_at || selectedRoom.current_reservation.check_out_date) }}</p>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Offre</label>
                    <Multiselect
                        v-model="stayModalOffer"
                        :options="stayOfferOptions"
                        label="label"
                        track-by="id"
                        placeholder="Choisir une offre"
                        :allow-empty="true"
                        class="mt-1"
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Nouveau départ</label>
                    <input
                        v-model="stayModalDate"
                        type="datetime-local"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        :min="stayModalMin"
                        :max="stayModalMax"
                        @input="onStayModalDateChange"
                    />
                </div>
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs font-semibold text-gray-500">Résumé</p>
                    <p class="text-sm">
                        {{ stayModalSummary.nights }} nuit(s) · {{ formatAmount(stayModalSummary.total) }}
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <button type="button" class="text-sm text-gray-500" @click="closeStayModal">
                    Annuler
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="stayModalSubmitting"
                    @click="submitStayModal"
                >
                    Valider
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="showChangeRoomModal && selectedRoom && selectedRoom.current_reservation"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Changer de chambre</h3>
                    <p class="text-xs text-gray-500">
                        Réservation {{ selectedRoom.current_reservation.code }}
                    </p>
                </div>
                <button type="button" class="text-sm text-gray-500" @click="closeChangeRoomModal">
                    Fermer
                </button>
            </div>

            <div class="space-y-3 text-sm text-gray-700">
                <div>
                    <p class="text-xs font-semibold text-gray-500">Chambre actuelle</p>
                    <p>Chambre {{ selectedRoom.number }}</p>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Nouvelle chambre</label>
                    <select
                        v-model="changeRoomSelection"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    >
                        <option disabled :value="null">Sélectionner</option>
                        <option
                            v-for="room in changeRoomOptions"
                            :key="room.id"
                            :value="room.id"
                        >
                            Chambre {{ room.number }} · {{ room.room_type_name || 'Type inconnu' }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <button type="button" class="text-sm text-gray-500" @click="closeChangeRoomModal">
                    Annuler
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="!changeRoomSelection || changeRoomSubmitting"
                    @click="submitChangeRoom"
                >
                    Valider
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="showMaintenanceModal && selectedRoom"
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Déclarer un problème
                    </h3>
                    <p class="text-xs text-gray-500">
                        Chambre {{ selectedRoom.number }}
                    </p>
                </div>
                <button type="button" class="text-sm text-gray-500" @click="closeMaintenanceModal">
                    Fermer
                </button>
            </div>

            <div class="space-y-4 text-sm text-gray-700">
                <div>
                    <label class="text-xs font-semibold text-gray-500">Titre *</label>
                    <input
                        v-model="maintenanceForm.title"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    />
                    <p
                        v-if="maintenanceFormErrors.title"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ maintenanceFormErrors.title }}
                    </p>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Sévérité</label>
                    <select
                        v-model="maintenanceForm.severity"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    >
                        <option
                            v-for="option in maintenanceSeverityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p
                        v-if="maintenanceFormErrors.severity"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ maintenanceFormErrors.severity }}
                    </p>
                </div>
                <div v-if="canOverrideMaintenanceBlocks">
                    <label class="text-xs font-semibold text-gray-500">Blocage des ventes</label>
                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-700">
                        <input
                            id="maintenance-blocks-sale"
                            v-model="maintenanceForm.blocks_sale"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                        />
                        <label for="maintenance-blocks-sale">
                            Bloquer la vente pour ce ticket
                        </label>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Description</label>
                    <textarea
                        v-model="maintenanceForm.description"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    ></textarea>
                    <p
                        v-if="maintenanceFormErrors.description"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ maintenanceFormErrors.description }}
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <button type="button" class="text-sm text-gray-500" @click="closeMaintenanceModal">
                    Annuler
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="maintenanceSubmitting"
                    @click="submitMaintenanceTicket"
                >
                    Enregistrer
                </button>
            </div>
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
</template>

<script>
import Swal from 'sweetalert2';
import axios from 'axios';
import { router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, ShieldCheck, Wrench } from 'lucide-vue-next';
import Multiselect from 'vue-multiselect';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import FolioModal from '@/components/Frontdesk/FolioModal.vue';
import { dashboard as frontdeskDashboard } from '@/routes/frontdesk';
import { enqueue } from '@/offline/outbox';

export default {
    name: 'RoomBoard',
    components: {
        AlertTriangle,
        CheckCircle,
        ShieldCheck,
        Wrench,
        PrimaryButton,
        SecondaryButton,
        TextInput,
        FolioModal,
        Multiselect,
    },
    props: {
        date: {
            type: String,
            required: true,
        },
        roomsByFloor: {
            type: Array,
            required: true,
        },
        walkInRoom: {
            type: Object,
            default: null,
        },
        walkInRoomType: {
            type: Object,
            default: null,
        },
        walkInDefaultDates: {
            type: Object,
            default: null,
        },
        walkInOffers: {
            type: Array,
            default: () => [],
        },
        walkInSource: {
            type: String,
            default: 'walk_in',
        },
        offers: {
            type: Array,
            default: () => [],
        },
        offerRoomTypePrices: {
            type: Array,
            default: () => [],
        },
        paymentMethods: {
            type: Array,
            default: () => [],
        },
        guests: {
            type: Array,
            default: () => [],
        },
        canManageHousekeeping: {
            type: Boolean,
            default: false,
        },
        maintenancePermissions: {
            type: Object,
            default: () => ({
                canReport: false,
                canHandle: false,
                canProgress: false,
            }),
        },
        currentUser: {
            type: Object,
            default: () => ({
                id: null,
                name: null,
            }),
        },
    },
    data() {
        return {
            currentDate: this.date,
            isWalkInOpen: !!this.walkInRoom,
            selectedWalkInGuest: null,
            selectedWalkInOffer: null,
            selectedWalkInPaymentMethod: null,
            localGuests: [],
            guestSearchTimeout: null,
            form: null,
            selectedRoom: null,
            roomsByFloorLocal: this.roomsByFloor,
            showFolioModal: false,
            folioData: null,
            folioLoading: false,
            folioInitialTab: 'charges',
            loadingRoomId: null,
            loadingRoomTimeout: null,
            hkFilter: 'all',
            hkFilterOptions: [
                { value: 'all', label: 'Toutes' },
                { value: 'dirty', label: 'Sale' },
                { value: 'cleaning', label: 'En cours' },
                { value: 'awaiting_inspection', label: 'En attente d’inspection' },
                { value: 'inspected', label: 'Inspectée' },
                { value: 'redo', label: 'À refaire' },
            ],
            showReservationModal: false,
            showStayModal: false,
            stayModalMode: 'extend',
            stayModalDate: '',
            stayModalTime: '',
            stayModalOffer: null,
            stayModalSubmitting: false,
            showChangeRoomModal: false,
            changeRoomSelection: null,
            changeRoomSubmitting: false,
            showMaintenanceModal: false,
            maintenanceForm: {
                title: '',
                severity: 'medium',
                blocks_sale: false,
                description: '',
            },
            maintenanceFormErrors: {},
            maintenanceSeverityOptions: [
                { value: 'low', label: 'Gravité basse' },
                { value: 'medium', label: 'Gravité moyenne' },
                { value: 'high', label: 'Gravité haute' },
                { value: 'critical', label: 'Gravité critique' },
            ],
            maintenanceSubmitting: false,
            maintenanceStatusSubmitting: false,
            roomActivityLoading: false,
            roomActivity: [],
            statusSubmitting: false,
            pendingFeeOverrides: {
                early: null,
                late: null,
            },
            refreshTimer: null,
        };
    },
    mounted() {
        this.localGuests = [...(this.guests || [])];
        this.roomsByFloorLocal = this.roomsByFloor;
        this.startPolling();
    },
    beforeUnmount() {
        this.stopPolling();
    },
    computed: {
        filteredRoomsByFloor() {
            if (this.hkFilter === 'all') {
                return this.roomsByFloorLocal;
            }

            return this.roomsByFloorLocal
                .map((rooms) =>
                    rooms.filter((room) => room.hk_status === this.hkFilter),
                )
                .filter((rooms) => rooms.length);
        },
        stayModalTitle() {
            return this.stayModalMode === 'extend'
                ? 'Prolonger le séjour'
                : 'Raccourcir le séjour';
        },
        canOverrideTimes() {
            return this.permissionFlags.reservations_override_datetime ?? false;
        },
        canExtendStayAction() {
            return Boolean(
                (this.permissionFlags.reservations_extend_stay ?? false)
                || this.canOverrideTimes,
            );
        },
        canShortenStayAction() {
            return Boolean(
                (this.permissionFlags.reservations_shorten_stay ?? false)
                || this.canOverrideTimes,
            );
        },
        canChangeRoomAction() {
            if (!this.selectedRoom?.current_reservation) {
                return false;
            }

            const canChange = this.permissionFlags.reservations_change_room ?? false;
            const status = this.selectedRoom.current_reservation.status;

            return (canChange || this.canOverrideTimes) && ['confirmed', 'in_house'].includes(status);
        },
        canOverrideFees() {
            const roles = this.$page?.props?.auth?.user?.roles || [];
            const hasRole = roles.some((role) => ['owner', 'manager'].includes(role.name));

            return this.canOverrideTimes || hasRole;
        },
        stayModalMin() {
            if (!this.selectedRoom?.current_reservation) {
                return undefined;
            }

            if (this.stayModalMode === 'extend') {
                return this.toDateTimeLocal(
                    this.selectedRoom.current_reservation.check_out_at
                    || this.selectedRoom.current_reservation.check_out_date,
                );
            }

            return this.toDateTimeLocal(
                this.selectedRoom.current_reservation.check_in_at
                || this.selectedRoom.current_reservation.check_in_date,
            );
        },
        stayModalMax() {
            if (!this.selectedRoom?.current_reservation) {
                return undefined;
            }

            if (this.stayModalMode === 'shorten') {
                return this.toDateTimeLocal(
                    this.selectedRoom.current_reservation.check_out_at
                    || this.selectedRoom.current_reservation.check_out_date,
                );
            }

            return undefined;
        },
        stayModalSummary() {
            if (!this.selectedRoom?.current_reservation || !this.stayModalDate) {
                return {
                    nights: 0,
                    total: 0,
                };
            }

            const reservation = this.selectedRoom.current_reservation;
            const offer = this.stayModalOffer
                ?? this.offers.find((item) => item.id === reservation.offer_id)
                ?? null;
            const offerKind = (offer?.kind ?? reservation.offer_kind) || 'night';
            const unitPrice = this.stayModalOfferPrice?.price ?? Number(reservation.unit_price ?? 0);
            const checkIn = this.normalizeStayDateTime(
                reservation.check_in_at || reservation.check_in_date,
                this.stayModalTime,
            );
            const currentCheckOut = this.normalizeStayDateTime(
                reservation.check_out_at || reservation.check_out_date,
                this.stayModalTime,
            );
            const isExtend = this.stayModalMode === 'extend';
            const start = isExtend ? currentCheckOut : checkIn;
            const useBundleSummary = isExtend && ['weekend', 'package'].includes(offerKind);

            if (!start) {
                return {
                    nights: 0,
                    total: 0,
                };
            }

            const nights = this.calculateStaySummaryNights(
                offerKind,
                start,
                this.stayModalDate,
                offer,
                useBundleSummary,
            );
            let units = 0;
            const isSameOffer = offer?.id && reservation.offer_id && Number(offer.id) === Number(reservation.offer_id);
            const resolveUnits = (from, to) => (
                useBundleSummary
                    ? this.calculateStayUnitsByDate(offerKind, from, to, offer)
                    : this.calculateStayUnits(offerKind, from, to, offer)
            );

            if (isExtend && isSameOffer && checkIn && currentCheckOut) {
                const previousUnits = resolveUnits(checkIn, currentCheckOut);
                const nextUnits = resolveUnits(checkIn, this.stayModalDate);

                units = Math.max(0, nextUnits - previousUnits);
            } else {
                units = resolveUnits(start, this.stayModalDate);
            }

            return {
                nights,
                total: units * unitPrice,
            };
        },
        stayOfferOptions() {
            const roomTypeId = this.selectedRoom?.current_reservation?.room_type_id;
            if (!roomTypeId) {
                return [];
            }

            return this.offerRoomTypePrices
                .filter((price) => price.room_type_id === roomTypeId)
                .map((price) => {
                    const offer = this.offers.find((item) => item.id === price.offer_id);
                    if (!offer) {
                        return null;
                    }

                    return {
                        id: offer.id,
                        name: offer.name,
                        kind: offer.kind,
                        time_rule: offer.time_rule,
                        time_config: offer.time_config,
                        fixed_duration_hours: offer.fixed_duration_hours,
                        price: price.price,
                        currency: price.currency,
                        label: `${offer.name} · ${this.formatAmount(price.price)}`,
                    };
                })
                .filter(Boolean);
        },
        stayModalOfferPrice() {
            if (!this.stayModalOffer?.id) {
                return null;
            }

            const roomTypeId = this.selectedRoom?.current_reservation?.room_type_id;
            if (!roomTypeId) {
                return null;
            }

            return this.offerRoomTypePrices.find(
                (price) => price.room_type_id === roomTypeId && price.offer_id === this.stayModalOffer.id,
            ) ?? null;
        },
        permissionFlags() {
            return this.$page?.props?.auth?.can ?? {};
        },
        canMarkClean() {
            return this.permissionFlags.housekeeping_mark_clean ?? this.canManageHousekeeping;
        },
        canMarkDirty() {
            return this.permissionFlags.housekeeping_mark_dirty ?? this.canManageHousekeeping;
        },
        canMarkInspected() {
            return this.permissionFlags.housekeeping_mark_inspected ?? this.canManageHousekeeping;
        },
        canManageHousekeepingActions() {
            return this.canMarkClean || this.canMarkDirty || this.canMarkInspected;
        },
        canOverrideMaintenanceBlocks() {
            const roles = this.$page?.props?.auth?.user?.roles || [];

            return roles.some((role) => ['owner', 'manager'].includes(role.name));
        },
        canReportMaintenance() {
            return this.permissionFlags.maintenance_tickets_create ?? this.maintenancePermissions?.canReport ?? false;
        },
        canHandleMaintenance() {
            return this.permissionFlags.maintenance_tickets_close ?? this.maintenancePermissions?.canHandle ?? false;
        },
        canProgressMaintenance() {
            return this.permissionFlags.maintenance_tickets_update ?? this.maintenancePermissions?.canProgress ?? false;
        },
        changeRoomOptions() {
            if (!this.selectedRoom?.current_reservation) {
                return [];
            }

            const reservationId = this.selectedRoom.current_reservation.id;

            return this.roomsByFloorLocal
                .flatMap((rooms) => rooms)
                .filter((room) => {
                    if (!room) {
                        return false;
                    }

                    if (room.id === this.selectedRoom?.id) {
                        return false;
                    }

                    if (room.status !== 'active') {
                        return false;
                    }

                    if (!room.current_reservation) {
                        return true;
                    }

                    return room.current_reservation.id === reservationId;
                });
        },
        selectedRoomMaintenanceTickets() {
            if (!this.selectedRoom) {
                return [];
            }

            if (Array.isArray(this.selectedRoom.maintenance_tickets)) {
                return this.selectedRoom.maintenance_tickets;
            }

            return this.selectedRoom.maintenance_ticket ? [this.selectedRoom.maintenance_ticket] : [];
        },
        selectedRoomMaintenance() {
            return this.selectedRoomMaintenanceTickets[0] ?? null;
        },
        currentUserId() {
            return this.currentUser?.id ?? null;
        },
    },
    watch: {
        roomsByFloor: {
            immediate: true,
            handler(newValue) {
                this.roomsByFloorLocal = newValue || [];
                this.syncSelectedRoom();
            },
        },
        walkInRoom: {
            immediate: true,
            async handler(newRoom) {
                if (newRoom && this.form && this.form.room_id === newRoom.id) {
                    this.isWalkInOpen = true;

                    return;
                }

                if (newRoom && this.walkInDefaultDates && this.walkInOffers) {
                    const initialOffer = this.walkInOffers.length
                        ? this.walkInOffers[0]
                        : null;
                    const defaultPaymentId = this.defaultPaymentMethodId();
                    const defaultPaymentMethod = defaultPaymentId
                        ? this.paymentMethodOptions().find((method) => method.id === defaultPaymentId) ?? null
                        : null;

                    const start = new Date();
                    let end = await this.computeWalkInEndDate(start, initialOffer);

                    if (!(end instanceof Date) || Number.isNaN(end.getTime())) {
                        end = new Date(start.getTime() + 24 * 60 * 60 * 1000);
                    }

                    this.form = useForm({
                        guest_id: null,
                        room_id: newRoom.id,
                        room_type_id: newRoom.room_type_id,
                        offer_id: initialOffer ? initialOffer.id : null,
                        offer_price_id: initialOffer
                            ? initialOffer.offer_price_id
                            : null,
                        payment_method_id: defaultPaymentId ?? null,
                        check_in_at: this.toDateTimeLocal(start),
                        check_out_at: this.toDateTimeLocal(end),
                        amount_received: '',
                    });

                    this.selectedWalkInOffer = initialOffer;
                    this.selectedWalkInPaymentMethod = defaultPaymentMethod;
                    this.isWalkInOpen = true;
                } else {
                    this.isWalkInOpen = false;
                    this.form = null;
                    this.selectedWalkInOffer = null;
                    this.selectedWalkInPaymentMethod = null;
                }
            },
        },
        guests: {
            immediate: true,
            handler(newGuests) {
                this.localGuests = [...(newGuests || [])];
            },
        },
        selectedWalkInGuest(newGuest) {
            if (!this.form) {
                return;
            }

            if (!newGuest) {
                this.form.guest_id = null;

                return;
            }

            this.form.guest_id = newGuest.id ?? null;
        },
        selectedWalkInOffer(newOffer) {
            if (!this.form) {
                return;
            }

            this.form.offer_id = newOffer?.id ?? null;
            this.form.offer_price_id = newOffer?.offer_price_id ?? null;

            if (newOffer) {
                this.onOfferChange();
            }
        },
        selectedWalkInPaymentMethod(newMethod) {
            if (!this.form) {
                return;
            }

            this.form.payment_method_id = newMethod?.id ?? null;
        },
        stayModalOffer(newOffer) {
            if (!newOffer || !this.showStayModal || this.stayModalMode !== 'extend') {
                return;
            }

            this.updateStayModalDateFromOffer(newOffer);
        },
    },
    methods: {
        startPolling() {
            if (this.refreshTimer) {
                window.clearInterval(this.refreshTimer);
            }

            this.refreshTimer = window.setInterval(() => {
                if (navigator.onLine && !this.isWalkInOpen && !this.showStayModal && !this.showChangeRoomModal && !this.showMaintenanceModal) {
                    this.reloadRoomBoard();
                }
            }, 60000);
        },
        stopPolling() {
            if (this.refreshTimer) {
                window.clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },
        extractFirstError(errors, fallback = null) {
            if (!errors) {
                return fallback;
            }

            if (typeof errors === 'string') {
                return errors;
            }

            if (Array.isArray(errors)) {
                return errors[0] ?? fallback;
            }

            const firstKey = Object.keys(errors)[0] ?? null;

            if (!firstKey) {
                return fallback;
            }

            const value = errors[firstKey];

            if (Array.isArray(value)) {
                return value[0] ?? fallback;
            }

            if (typeof value === 'string') {
                return value;
            }

            return fallback;
        },
        toDateTimeLocal(date) {
            const d = date instanceof Date ? date : new Date(date);

            if (Number.isNaN(d.getTime())) {
                return '';
            }

            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        },
        paymentMethodOptions() {
            return Array.isArray(this.paymentMethods) ? this.paymentMethods : [];
        },
        defaultPaymentMethodId() {
            const methods = this.paymentMethodOptions();
            if (!methods.length) {
                return null;
            }

            return methods.find((method) => method.is_default)?.id ?? methods[0].id ?? null;
        },
        extractTime(value) {
            if (!value) {
                return '';
            }

            if (typeof value === 'string') {
                const match = value.match(/(\d{2}):(\d{2})/);

                return match ? `${match[1]}:${match[2]}` : '';
            }

            const date = value instanceof Date ? value : new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            return `${hours}:${minutes}`;
        },
        normalizeStayDateTime(value, fallbackTime = null) {
            if (!value) {
                return '';
            }

            const fallback = fallbackTime || '00:00';

            if (value instanceof Date) {
                return this.toDateTimeLocal(value);
            }

            if (typeof value !== 'string') {
                return '';
            }

            const trimmed = value.trim();

            if (!trimmed) {
                return '';
            }

            if (trimmed.includes('T')) {
                const [datePart, timePart = ''] = trimmed.split('T');
                const time = this.normalizeStayTime(timePart, fallback);

                return `${datePart}T${time}`;
            }

            if (trimmed.includes(' ')) {
                const [datePart, timePart = ''] = trimmed.split(' ');
                const time = this.normalizeStayTime(timePart, fallback);

                return `${datePart}T${time}`;
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
                return `${trimmed}T${fallback}`;
            }

            return '';
        },
        normalizeStayTime(value, fallback) {
            const cleaned = (value || '').replace('Z', '').split('.')[0];

            if (!cleaned) {
                return fallback;
            }

            const match = cleaned.match(/(\d{2}):(\d{2})/);

            return match ? `${match[1]}:${match[2]}` : fallback;
        },
        onStayModalDateChange() {
            if (!this.stayModalDate) {
                return;
            }

            const [datePart, timePart] = this.stayModalDate.split('T');
            const normalizedTime = timePart || this.stayModalTime;

            if (!normalizedTime) {
                return;
            }

            this.stayModalDate = `${datePart}T${normalizedTime}`;
            this.stayModalTime = normalizedTime;
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
        openCashSessionModal() {
            window.dispatchEvent(new CustomEvent('cash-session-open-request', {
                detail: { type: 'frontdesk' },
            }));
        },
        async ensureFrontdeskCashSession() {
            try {
                const response = await axios.get('/cash/status', { params: { type: 'frontdesk' } });

                if (response.data?.session) {
                    return true;
                }
            } catch {
                // ignore
            }

            await Swal.fire({
                icon: 'warning',
                title: 'Caisse fermée',
                text: 'Veuillez ouvrir la caisse réception avant de continuer.',
                confirmButtonText: 'Ouvrir la caisse',
            });

            this.openCashSessionModal();

            return false;
        },
        async computeWalkInEndDate(startDate, offer) {
            const start = startDate instanceof Date ? new Date(startDate) : new Date();

            if (Number.isNaN(start.getTime())) {
                return new Date();
            }

            const effectiveOffer = offer
                || this.walkInOffers.find((o) => o.id === this.form?.offer_id)
                || null;

            if (!effectiveOffer) {
                return new Date(start.getTime() + 24 * 60 * 60 * 1000);
            }

            try {
                const http = window.axios ?? axios;
                const response = await http.post(`/api/offers/${effectiveOffer.id}/time-preview`, {
                    arrival_at: this.toDateTimeLocal(start),
                });

                const departure = new Date(response.data?.departure_at);

                if (!Number.isNaN(departure.getTime())) {
                    return departure;
                }
            } catch (error) {
                console.error('Erreur lors du calcul de la date de départ pour le walk-in', error);
            }

            return new Date(start.getTime() + 24 * 60 * 60 * 1000);
        },
        async onWalkInGuestSearchChange(query) {
            if (this.guestSearchTimeout) {
                clearTimeout(this.guestSearchTimeout);
            }

            const term = (query || '').trim();

            if (term.length < 2) {
                this.localGuests = [...this.guests];

                return;
            }

            this.guestSearchTimeout = setTimeout(async () => {
                try {
                    const response = await axios.get('/resources/guests/search', {
                        params: { search: term },
                        headers: { Accept: 'application/json' },
                    });

                    const results = Array.isArray(response.data) ? response.data : [];

                    this.localGuests = results.map((g) => ({
                        ...g,
                        full_name:
                            g.full_name
                            || `${(g.last_name ?? '')} ${(g.first_name ?? '')}`.trim(),
                    }));

                    if (this.selectedWalkInGuest && !this.localGuests.find((g) => g.id === this.selectedWalkInGuest.id)) {
                        this.localGuests.unshift(this.selectedWalkInGuest);
                    }
                } catch {
                    this.localGuests = [...this.guests];
                }
            }, 250);
        },
        parseGuestName(input) {
            if (!input || typeof input !== 'string') {
                return {
                    last_name: '',
                    first_name: '',
                };
            }

            const parts = input.trim().split(/\s+/);

            if (parts.length === 0) {
                return {
                    last_name: '',
                    first_name: '',
                };
            }

            const last_name = parts[0];
            const first_name = parts.slice(1).join(' ');

            return {
                last_name,
                first_name,
            };
        },
        async onWalkInGuestTag(inputValue) {
            const parsed = this.parseGuestName(inputValue);
            const documentTypes = [
                'Passeport',
                "Carte d'identité",
                'Permis',
                'Carte de Séjour',
                'Carte Professionnelle',
                'Autre',
            ];

            const { value: formValues, isConfirmed } = await Swal.fire({
                title: 'Créer un nouveau client',
                html:
                    '<div class="text-left space-y-2">'
                    + '<label class="block text-xs font-semibold text-gray-600">Nom</label>'
                    + `<input id="swal-guest-last-name" type="text" class="swal2-input" value="${parsed.last_name ?? ''}">`
                    + '<label class="block text-xs font-semibold text-gray-600">Prénom</label>'
                    + `<input id="swal-guest-first-name" type="text" class="swal2-input" value="${parsed.first_name ?? ''}">`
                    + '<label class="block text-xs font-semibold text-gray-600">Téléphone</label>'
                    + '<input id="swal-guest-phone" type="text" class="swal2-input" value="">'
                    + '<label class="block text-xs font-semibold text-gray-600">Type de document</label>'
                    + `<select id="swal-guest-document-type" class="swal2-select">${documentTypes.map((type) => `<option value="${type}">${type}</option>`).join('')}</select>`
                    + '<div id="swal-guest-document-other" class="hidden">'
                    + '<label class="block text-xs font-semibold text-gray-600">Préciser le document</label>'
                    + '<input id="swal-guest-document-other-input" type="text" class="swal2-input" value="">'
                    + '</div>'
                    + '<label class="block text-xs font-semibold text-gray-600">Numéro de document</label>'
                    + '<input id="swal-guest-document-number" type="text" class="swal2-input" value="">'
                    + '</div>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Créer',
                cancelButtonText: 'Annuler',
                didOpen: () => {
                    const typeSelect = document.getElementById('swal-guest-document-type');
                    const otherWrapper = document.getElementById('swal-guest-document-other');

                    if (!typeSelect || !otherWrapper) {
                        return;
                    }

                    const toggleOther = () => {
                        const showOther = typeSelect.value === 'Autre';
                        otherWrapper.classList.toggle('hidden', !showOther);
                    };

                    toggleOther();
                    typeSelect.addEventListener('change', toggleOther);
                },
                preConfirm: () => {
                    const lastNameInput = document.getElementById('swal-guest-last-name');
                    const firstNameInput = document.getElementById('swal-guest-first-name');
                    const phoneInput = document.getElementById('swal-guest-phone');
                    const typeSelect = document.getElementById('swal-guest-document-type');
                    const otherInput = document.getElementById('swal-guest-document-other-input');
                    const numberInput = document.getElementById('swal-guest-document-number');

                    const last_name = (lastNameInput?.value ?? '').toString().trim();
                    const first_name = (firstNameInput?.value ?? '').toString().trim();
                    const phone = (phoneInput?.value ?? '').toString().trim();
                    const selectedType = (typeSelect?.value ?? '').toString().trim();
                    const otherType = (otherInput?.value ?? '').toString().trim();
                    const document_number = (numberInput?.value ?? '').toString().trim();

                    if (!last_name) {
                        Swal.showValidationMessage('Le nom est obligatoire.');

                        return false;
                    }

                    if (selectedType === 'Autre' && !otherType) {
                        Swal.showValidationMessage('Veuillez préciser le document.');

                        return false;
                    }

                    return {
                        last_name,
                        first_name,
                        phone,
                        document_type: selectedType === 'Autre' ? otherType : selectedType,
                        document_number,
                    };
                },
            });

            if (!isConfirmed || !formValues) {
                return;
            }

            try {
                const response = await axios.post(
                    '/resources/guests',
                    {
                        first_name: formValues.first_name,
                        last_name: formValues.last_name,
                        phone: formValues.phone || null,
                        document_type: formValues.document_type || null,
                        document_number: formValues.document_number || null,
                    },
                    {
                        headers: {
                            Accept: 'application/json',
                        },
                    },
                );

                const newGuest = response.data?.guest ?? response.data;

                if (!newGuest || !newGuest.id) {
                    return;
                }

                const guestWithName = {
                    ...newGuest,
                    full_name:
                        newGuest.full_name
                        || `${(newGuest.last_name ?? '')} ${(newGuest.first_name ?? '')}`.trim(),
                };

                this.localGuests.push(guestWithName);
                this.selectedWalkInGuest = guestWithName;
            } catch (error) {
                const message =
                    error.response?.data?.message
                    ?? 'Impossible de créer le client.';

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            }
        },
        syncSelectedRoom() {
            if (!this.selectedRoom) {
                return;
            }

            const updated = this.findRoomById(this.selectedRoom.id);

            if (updated) {
                this.selectedRoom = updated;
            }
        },
        findRoomById(roomId) {
            return this.roomsByFloorLocal
                .flatMap((rooms) => rooms)
                .find((room) => room.id === roomId);
        },
        applyRoomPatch(roomId, payload) {
            let patchedRoom = null;

            this.roomsByFloorLocal = this.roomsByFloorLocal.map((floorRooms) =>
                floorRooms.map((room) => {
                    if (room.id !== roomId) {
                        return room;
                    }

                    patchedRoom = {
                        ...room,
                        ...payload,
                    };

                    return patchedRoom;
                }),
            );

            if (patchedRoom && this.selectedRoom?.id === roomId) {
                this.selectedRoom = patchedRoom;
            }
        },
        openStayModal(mode) {
            const canManageStay = mode === 'extend' ? this.canExtendStayAction : this.canShortenStayAction;
            if (!canManageStay) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.selectedRoom?.current_reservation) {
                return;
            }

            this.showReservationModal = false;
            this.stayModalMode = mode;
            const currentDeparture = this.selectedRoom.current_reservation.check_out_at
                || this.selectedRoom.current_reservation.check_out_date;
            const currentOfferId = this.selectedRoom.current_reservation.offer_id;
            this.stayModalOffer = this.stayOfferOptions.find((offer) => offer.id === currentOfferId)
                ?? this.stayOfferOptions[0]
                ?? null;

            this.stayModalTime = this.extractTime(currentDeparture);
            const currentDepartureValue = this.normalizeStayDateTime(currentDeparture, this.stayModalTime);

            if (!currentDepartureValue) {
                return;
            }

            if (mode === 'extend') {
                this.stayModalDate = this.addDays(currentDepartureValue, 1);
            } else {
                this.stayModalDate = currentDepartureValue;
            }

            this.showStayModal = true;
            if (mode === 'extend' && this.stayModalOffer?.id) {
                this.$nextTick(() => {
                    this.updateStayModalDateFromOffer(this.stayModalOffer);
                });
            }
        },
        closeStayModal() {
            this.showStayModal = false;
            this.stayModalOffer = null;
            this.stayModalSubmitting = false;
            this.stayModalDate = '';
            this.stayModalTime = '';
        },
        closeReservationModal() {
            this.showReservationModal = false;
        },
        async submitStayModal() {
            const canManageStay = this.stayModalMode === 'extend'
                ? this.canExtendStayAction
                : this.canShortenStayAction;

            if (!canManageStay) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.selectedRoom?.current_reservation || !this.stayModalDate) {
                return;
            }

            this.stayModalSubmitting = true;

            try {
                await axios.patch(
                    `/reservations/${this.selectedRoom.current_reservation.id}/stay/dates`,
                    {
                        check_out_date: this.stayModalDate,
                        offer_id: this.stayModalOffer?.id ?? null,
                    },
                );

                Swal.fire({
                    icon: 'success',
                    title: 'Séjour mis à jour',
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.closeStayModal();
                this.reloadRoomBoard();
            } catch (error) {
                const message = error.response?.data?.message
                    ?? error.response?.data?.errors?.check_out_date?.[0]
                    ?? 'Impossible de mettre à jour le séjour.';

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            } finally {
                this.stayModalSubmitting = false;
            }
        },
        async updateStayModalDateFromOffer(offer) {
            if (!offer?.id || !this.selectedRoom?.current_reservation || this.stayModalMode !== 'extend') {
                return;
            }

            const arrivalValue = this.selectedRoom.current_reservation.check_out_at
                || this.selectedRoom.current_reservation.check_out_date;
            const arrivalAt = this.normalizeStayDateTime(arrivalValue, this.stayModalTime);

            if (!arrivalAt) {
                return;
            }

            try {
                const response = await axios.post(`/api/offers/${offer.id}/time-preview`, {
                    arrival_at: arrivalAt,
                });
                const departure = new Date(response.data?.departure_at);

                if (!Number.isNaN(departure.getTime())) {
                    this.stayModalDate = this.toDateTimeLocal(departure);
                    this.stayModalTime = this.extractTime(departure);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Offre invalide',
                    text: error.response?.data?.message ?? 'Impossible de calculer la date de départ.',
                });
            }
        },
        openChangeRoomModal() {
            if (!this.canChangeRoomAction) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.selectedRoom?.current_reservation) {
                return;
            }

            this.changeRoomSelection = null;
            this.showReservationModal = false;
            this.showChangeRoomModal = true;
        },
        closeChangeRoomModal() {
            this.showChangeRoomModal = false;
            this.changeRoomSubmitting = false;
            this.loadingRoomId = null;
        },
        async submitChangeRoom() {
            if (!this.canChangeRoomAction) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.selectedRoom?.current_reservation || !this.changeRoomSelection) {
                return;
            }

            this.changeRoomSubmitting = true;

            try {
                await axios.patch(
                    `/reservations/${this.selectedRoom.current_reservation.id}/stay/room`,
                    {
                        room_id: this.changeRoomSelection,
                    },
                );

                Swal.fire({
                    icon: 'success',
                    title: 'Chambre mise à jour',
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.closeChangeRoomModal();
                this.reloadRoomBoard();
            } catch (error) {
                const message = error.response?.data?.message
                    ?? error.response?.data?.errors?.room_id?.[0]
                    ?? 'Impossible de changer la chambre.';

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            } finally {
                this.changeRoomSubmitting = false;
            }
        },
        openMaintenanceModal(room) {
            if (!this.canReportMaintenance || !room) {
                this.showUnauthorizedAlert();
                return;
            }

            this.selectRoom(room);
            this.resetMaintenanceForm();
            this.showMaintenanceModal = true;
        },
        closeMaintenanceModal() {
            this.showMaintenanceModal = false;
            this.maintenanceFormErrors = {};
        },
        resetMaintenanceForm() {
            const defaultSeverity = 'medium';
            const defaultBlocksSale = ['high', 'critical'].includes(defaultSeverity);

            this.maintenanceForm = {
                title: '',
                severity: defaultSeverity,
                blocks_sale: defaultBlocksSale,
                description: '',
            };
            this.maintenanceFormErrors = {};
        },
        async submitMaintenanceTicket() {
            if (!this.selectedRoom) {
                return;
            }

            this.maintenanceSubmitting = true;
            this.maintenanceFormErrors = {};

            try {
                const payload = {
                    room_id: this.selectedRoom.id,
                    ...this.maintenanceForm,
                };

                if (!this.canOverrideMaintenanceBlocks) {
                    delete payload.blocks_sale;
                }

                const response = await axios.post('/maintenance-tickets', {
                    ...payload,
                });

                const ticket = response.data?.ticket ?? null;

                if (ticket) {
                    const tickets = [
                        ticket,
                        ...this.selectedRoomMaintenanceTickets.filter((item) => item.id !== ticket.id),
                    ];
                    const blockingCount = tickets.filter((item) => item.blocks_sale).length;

                    this.applyRoomPatch(this.selectedRoom.id, {
                        maintenance_ticket: ticket,
                        maintenance_tickets: tickets,
                        maintenance_open_count: tickets.length,
                        maintenance_blocking_count: blockingCount,
                        is_sellable: blockingCount === 0,
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket créé',
                    text: 'La chambre est marquée en maintenance.',
                    timer: 1800,
                    showConfirmButton: false,
                });

                this.showMaintenanceModal = false;
                this.resetMaintenanceForm();
                this.reloadRoomBoard();
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();
                    return;
                }
                if (error.response?.status === 422) {
                    this.maintenanceFormErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? 'Impossible de créer le ticket.',
                    });
                }
            } finally {
                this.maintenanceSubmitting = false;
            }
        },
        async assignMaintenanceToSelf(ticket) {
            if (!ticket || !this.currentUserId) {
                return;
            }

            await this.updateMaintenanceStatus(ticket, ticket.status, {
                assigned_to_user_id: this.currentUserId,
            });
        },
        async updateMaintenanceStatus(ticket, status, extra = {}) {
            if (!ticket) {
                return;
            }

            if (!this.canProgressMaintenance && ['open', 'in_progress'].includes(status)) {
                this.showUnauthorizedAlert();

                return;
            }

            if (['resolved', 'closed'].includes(status) && !this.canHandleMaintenance) {
                this.showUnauthorizedAlert();

                return;
            }

            const payload = {
                status,
                ...extra,
            };

            if (['resolved', 'closed'].includes(status) && this.shouldOfferRoomRestore()) {
                const result = await Swal.fire({
                    icon: 'question',
                    title: 'Remettre la chambre en service ?',
                    text: 'Souhaitez-vous la marquer comme disponible immédiatement ?',
                    showCancelButton: true,
                    confirmButtonText: 'Oui',
                    cancelButtonText: 'Non',
                });

                payload.restore_room_status = result.isConfirmed;
            }

            this.maintenanceStatusSubmitting = true;

            try {
                const response = await axios.patch(
                    `/maintenance-tickets/${ticket.id}`,
                    payload,
                );

                const updatedTicket = response.data?.ticket ?? null;

                if (updatedTicket) {
                    const tickets = this.selectedRoomMaintenanceTickets
                        .filter((item) => item.id !== updatedTicket.id)
                        .concat(['open', 'in_progress'].includes(updatedTicket.status) ? updatedTicket : []);
                    const blockingCount = tickets.filter((item) => item.blocks_sale).length;

                    const primaryTicket = tickets[0] ?? null;

                    this.applyRoomPatch(this.selectedRoom.id, {
                        maintenance_ticket: primaryTicket,
                        maintenance_tickets: tickets,
                        maintenance_open_count: tickets.length,
                        maintenance_blocking_count: blockingCount,
                        is_sellable: blockingCount === 0,
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket mis à jour',
                    timer: 1600,
                    showConfirmButton: false,
                });

                this.reloadRoomBoard();
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();

                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Impossible de mettre à jour le ticket.',
                });
            } finally {
                this.maintenanceStatusSubmitting = false;
            }
        },
        async toggleMaintenanceBlocksSale(ticket) {
            if (!ticket || !this.canOverrideMaintenanceBlocks) {
                return;
            }

            this.maintenanceStatusSubmitting = true;

            try {
                const response = await axios.patch(
                    `/maintenance-tickets/${ticket.id}`,
                    {
                        blocks_sale: !ticket.blocks_sale,
                    },
                );

                const updatedTicket = response.data?.ticket ?? null;

                if (updatedTicket) {
                    const tickets = this.selectedRoomMaintenanceTickets.map((item) => (
                        item.id === updatedTicket.id ? updatedTicket : item
                    ));
                    const blockingCount = tickets.filter((item) => item.blocks_sale).length;

                    this.applyRoomPatch(this.selectedRoom.id, {
                        maintenance_ticket: updatedTicket,
                        maintenance_tickets: tickets,
                        maintenance_open_count: tickets.length,
                        maintenance_blocking_count: blockingCount,
                        is_sellable: blockingCount === 0,
                    });
                }

                this.reloadRoomBoard();
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();

                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Impossible de modifier le blocage.',
                });
            } finally {
                this.maintenanceStatusSubmitting = false;
            }
        },
        shouldOfferRoomRestore() {
            if (!this.selectedRoom) {
                return false;
            }

            return this.selectedRoom.status === 'out_of_order' && !this.selectedRoom.is_occupied;
        },
        calculateStayNights(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);

            if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
                return 0;
            }

            const msPerDay = 1000 * 60 * 60 * 24;

            return Math.max(1, Math.ceil((endDate - startDate) / msPerDay));
        },
        calculateStayDateNights(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);

            if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
                return 0;
            }

            const startDay = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
            const endDay = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
            const msPerDay = 1000 * 60 * 60 * 24;

            return Math.max(1, Math.ceil((endDay - startDay) / msPerDay));
        },
        calculateStayUnitsByDate(kind, start, end, offer = null) {
            const nights = this.calculateStayDateNights(start, end);

            if (!nights) {
                return 0;
            }

            switch (kind) {
                case 'short_stay':
                    return 1;
                case 'weekend':
                case 'package':
                    return Math.max(1, Math.ceil(nights / this.resolveBundleNights(offer, kind)));
                default:
                    return nights;
            }
        },
        calculateStaySummaryNights(kind, start, end, offer = null, useBundleSummary = false) {
            if (!useBundleSummary) {
                return this.calculateStayNights(start, end);
            }

            const units = this.calculateStayUnitsByDate(kind, start, end, offer);

            if (!units) {
                return 0;
            }

            const bundleNights = this.resolveBundleNights(offer, kind);

            return units * bundleNights;
        },
        calculateStayUnits(kind, start, end, offer = null) {
            const nights = this.calculateStayNights(start, end);

            if (!nights) {
                return 0;
            }

            switch (kind) {
                case 'short_stay':
                    return 1;
                case 'weekend':
                case 'package':
                    return Math.max(1, Math.ceil(nights / this.resolveBundleNights(offer, kind)));
                default:
                    return nights;
            }
        },
        resolveBundleNights(offer, kind = null) {
            const resolvedKind = kind ?? offer?.kind ?? 'night';
            if (!['weekend', 'package'].includes(resolvedKind)) {
                return 1;
            }

            if (!offer) {
                return resolvedKind === 'weekend' ? 2 : 1;
            }

            let bundle = 0;

            if (offer.time_rule === 'weekend_window') {
                bundle = Number(offer.time_config?.checkout?.max_days_after_checkin ?? 0);
            } else if (offer.time_rule === 'fixed_checkout') {
                bundle = Number(offer.time_config?.day_offset ?? 0);
            } else if (offer.time_rule === 'rolling') {
                const minutes = Number(offer.time_config?.duration_minutes ?? 0);
                bundle = minutes > 0 ? Math.ceil(minutes / 1440) : 0;
            } else if (offer.time_rule === 'fixed_window') {
                const startTime = offer.time_config?.start_time ?? null;
                const endTime = offer.time_config?.end_time ?? null;
                if (typeof startTime === 'string' && typeof endTime === 'string') {
                    const [startHour, startMinute] = startTime.split(':').map(Number);
                    const [endHour, endMinute] = endTime.split(':').map(Number);
                    const startMinutes = (Number.isFinite(startHour) ? startHour : 0) * 60
                        + (Number.isFinite(startMinute) ? startMinute : 0);
                    let endMinutes = (Number.isFinite(endHour) ? endHour : 0) * 60
                        + (Number.isFinite(endMinute) ? endMinute : 0);
                    if (endMinutes <= startMinutes) {
                        endMinutes += 1440;
                    }
                    const duration = endMinutes - startMinutes;
                    bundle = duration > 0 ? Math.ceil(duration / 1440) : 0;
                }
            }

            if (bundle <= 0 && Number.isFinite(Number(offer.fixed_duration_hours))) {
                bundle = Math.ceil(Number(offer.fixed_duration_hours) / 24);
            }

            if (!Number.isFinite(bundle) || bundle <= 0) {
                return resolvedKind === 'weekend' ? 2 : 1;
            }

            return bundle;
        },
        formatAmount(value) {
            const amount = Number(value || 0);

            return `${amount.toFixed(0)} XAF`;
        },
        formatDateTime(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            const formatter = new Intl.DateTimeFormat('fr-FR', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
            const parts = formatter.formatToParts(date);
            const day = parts.find((part) => part.type === 'day')?.value ?? '';
            const year = parts.find((part) => part.type === 'year')?.value ?? '';
            let month = parts.find((part) => part.type === 'month')?.value ?? '';
            month = month.replace('.', '');
            if (month.length > 0) {
                month = `${month.charAt(0).toUpperCase()}${month.slice(1)}`;
            }

            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            return `${day} ${month} ${year} ${hours}h${minutes}`.trim();
        },
        addDays(dateStr, days) {
            const date = new Date(dateStr);
            if (Number.isNaN(date.getTime())) {
                return dateStr;
            }

            date.setDate(date.getDate() + days);
            return this.toDateTimeLocal(date);
        },
        async openFolioFromRoom(tab = 'charges') {
            if (!this.selectedRoom || !this.selectedRoom.current_reservation) {
                return;
            }

            this.showReservationModal = false;
            this.folioLoading = true;
            this.folioInitialTab = tab || 'charges';

            try {
                const http = window.axios ?? axios;
                const reservationId = this.selectedRoom.current_reservation.id;
                const response = await http.get(`/reservations/${reservationId}/folio`);
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

            const http = window.axios ?? axios;
            const reservationId = this.folioData.reservation.id;
            const response = await http.get(`/reservations/${reservationId}/folio`);
            this.folioData = response.data;
            this.$inertia.reload({ only: ['roomsByFloor'] });
        },
        closeFolioModal() {
            this.showFolioModal = false;
            this.folioInitialTab = 'charges';
        },
        onDateChange() {
            this.reloadRoomBoard();
        },
        goToday() {
            const today = new Date().toISOString().slice(0, 10);
            this.currentDate = today;
            this.reloadRoomBoard();
        },
        selectRoom(room) {
            if (!room) {
                this.selectedRoom = null;

                return;
            }

            if (this.loadingRoomTimeout) {
                clearTimeout(this.loadingRoomTimeout);
            }

            this.loadingRoomId = room.id;

            this.selectedRoom = this.findRoomById(room.id) ?? room;

            this.loadingRoomTimeout = setTimeout(() => {
                this.loadingRoomId = null;
            }, 500);
        },
        openWalkInForRoom(room) {
            if (!room || room.ui_status !== 'available') {
                return;
            }

            this.reloadRoomBoard({
                room_id: room.id,
                source: 'walk_in',
            });
        },
        viewCurrentReservation(room) {
            if (
                !room ||
                !room.current_reservation ||
                !room.current_reservation.id
            ) {
                return;
            }

            this.selectedRoom = this.findRoomById(room.id) ?? room;
            this.showReservationModal = true;
        },
        async onOfferChange() {
            if (!this.form) {
                return;
            }

            const selected = this.selectedWalkInOffer
                ?? this.walkInOffers.find((offer) => offer.id === this.form.offer_id);

            if (selected) {
                this.form.offer_price_id = selected.offer_price_id;
                const start = this.form.check_in_at
                    ? new Date(this.form.check_in_at)
                    : new Date();

                let end = await this.computeWalkInEndDate(start, selected);

                if (!(end instanceof Date) || Number.isNaN(end.getTime())) {
                    end = new Date(start.getTime() + 24 * 60 * 60 * 1000);
                }

                this.form.check_in_at = this.toDateTimeLocal(start);
                this.form.check_out_at = this.toDateTimeLocal(end);
            }
        },
        async onWalkInArrivalChange() {
            if (!this.form) {
                return;
            }

            const selected = this.walkInOffers.find(
                (offer) => offer.id === this.form.offer_id,
            );

            if (!selected) {
                return;
            }

            const start = this.form.check_in_at
                ? new Date(this.form.check_in_at)
                : null;

            if (!start || Number.isNaN(start.getTime())) {
                return;
            }

            let end = await this.computeWalkInEndDate(start, selected);

            if (!(end instanceof Date) || Number.isNaN(end.getTime())) {
                end = new Date(start.getTime() + 24 * 60 * 60 * 1000);
            }

            this.form.check_out_at = this.toDateTimeLocal(end);
        },
        submitWalkIn() {
            if (!this.form) {
                return;
            }

            this.syncWalkInFormState();
            const guestId = this.selectedWalkInGuest?.id ?? this.form.guest_id ?? null;
            if (!guestId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Le client est obligatoire.',
                });

                return;
            }

            this.form.guest_id = guestId;

            this.form.post('/frontdesk/room-board/walk-in', {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    this.isWalkInOpen = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Réservation walk-in enregistrée avec succès.',
                        timer: 2500,
                        showConfirmButton: false,
                    });
                    if (Number(this.form.amount_received || 0) > 0) {
                        window.dispatchEvent(new CustomEvent('cash-session-updated', {
                            detail: { type: 'frontdesk' },
                        }));
                    }
                    this.reloadRoomBoard();
                },
                onError: (errors) => {
                    this.syncWalkInFormState();
                    const handled = this.handleAvailabilityErrors(errors);
                    if (!handled) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: "Impossible d'enregistrer la réservation walk-in.",
                        });
                    }
                },
            });
        },
        syncWalkInFormState() {
            if (!this.form) {
                return;
            }

            if (this.selectedWalkInGuest?.id) {
                this.form.guest_id = this.selectedWalkInGuest.id;
            }

            if (this.selectedWalkInOffer?.id) {
                this.form.offer_id = this.selectedWalkInOffer.id;
                this.form.offer_price_id = this.selectedWalkInOffer.offer_price_id ?? null;
            }

            if (this.selectedWalkInPaymentMethod?.id) {
                this.form.payment_method_id = this.selectedWalkInPaymentMethod.id;
            }
        },
        walkInOfferLabel(offer) {
            if (!offer) {
                return '';
            }

            return `${offer.name} — ${this.formatAmount(offer.price)}`;
        },
        closeWalkIn() {
            this.isWalkInOpen = false;
            this.reloadRoomBoard();
        },
        async updateRoomHkStatus(roomId, hkStatus) {
            const permissionMap = {
                dirty: this.canMarkDirty,
                redo: this.canMarkDirty,
                cleaning: this.canMarkClean,
                awaiting_inspection: this.canMarkClean,
                inspected: this.canMarkInspected,
            };

            if (permissionMap[hkStatus] === false) {
                this.showUnauthorizedAlert();

                return;
            }

            const tenantId = this.$page?.props?.auth?.user?.tenant_id;
            const hotelId = this.$page?.props?.auth?.activeHotel?.id ?? this.$page?.props?.auth?.user?.active_hotel_id;

            if (!navigator.onLine) {
                this.applyRoomPatch(roomId, { hk_status: hkStatus, pending_sync: true });

                await enqueue({
                    type: 'hk.update',
                    endpoint: `/frontdesk/rooms/${roomId}/hk-status`,
                    method: 'patch',
                    payload: { hk_status: hkStatus },
                    tenant_id: tenantId,
                    hotel_id: hotelId,
                });

                Swal.fire({
                    icon: 'info',
                    title: 'Action en file',
                    text: 'Le statut sera synchronisé dès le retour en ligne.',
                    timer: 1800,
                    showConfirmButton: false,
                });

                return;
            }

            try {
                const http = window.axios ?? axios;
                const response = await http.patch(
                    `/frontdesk/rooms/${roomId}/hk-status`,
                    {
                        hk_status: hkStatus,
                    },
                );

                if (response.data && response.data.success) {
                    const newStatus = response.data.room.hk_status;

                    if (this.selectedRoom && this.selectedRoom.id === roomId) {
                        this.selectedRoom = {
                            ...this.selectedRoom,
                            hk_status: newStatus,
                        };
                    }

                    this.applyRoomPatch(roomId, { hk_status: newStatus });

                    Swal.fire({
                        icon: 'success',
                        title: 'Mise à jour effectuée',
                        text: `La chambre a été marquée comme ${this.hkStatusLabel(newStatus)}.`,
                        timer: 1500,
                        showConfirmButton: false,
                    });

                    this.reloadRoomBoard();
                }
            } catch (error) {
                if (error?.response?.status === 403) {
                    this.showUnauthorizedAlert();

                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: "Impossible de mettre à jour le statut ménage.",
                });
            }
        },
        handleAvailabilityErrors(errors) {
            if (!errors) {
                return false;
            }

            const message = errors.room_id ?? errors.room_type_id ?? null;

            if (!message) {
                const fallbackMessage = this.extractFirstError(errors, null);

                if (!fallbackMessage) {
                    return false;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de réservation',
                    text: fallbackMessage,
                });

                return true;
            }

            const text = Array.isArray(message) ? message[0] : message;

            Swal.fire({
                icon: 'warning',
                title: 'Indisponible',
                text,
            });

            return true;
        },
        async loadRoomActivity() {
            if (!this.selectedRoom?.id) {
                this.roomActivity = [];

                return;
            }

            this.roomActivityLoading = true;

            try {
                const response = await axios.get(`/rooms/${this.selectedRoom.id}/activity`, {
                    headers: { Accept: 'application/json' },
                });

                this.roomActivity = Array.isArray(response.data) ? response.data : [];
            } catch {
                this.roomActivity = [];
            } finally {
                this.roomActivityLoading = false;
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
                    return entry.description || 'Action';
            }
        },
        reloadRoomBoard(extra = {}) {
            router.get(
                frontdeskDashboard().url,
                {
                    date: this.currentDate,
                    ...extra,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['roomBoardData'],
                },
            );
        },
        roomClasses(room) {
            const base =
                'cursor-pointer rounded-xl border p-5 text-sm shadow-sm transition';
            const urgentHighlight = room.hk_priority === 'urgent'
                ? ' ring-2 ring-rose-300 animate-pulse'
                : '';

            if (
                room.status === 'out_of_order' ||
                room.ui_status === 'out_of_order'
            ) {
                return (
                    base +
                    urgentHighlight +
                    ' bg-black border-black text-white'
                );
            }

            if (room.ui_status === 'occupied') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-red-50 border-red-200 text-red-800'
                );
            }

            if (room.status === 'inactive') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-neutral-100 border-neutral-300 text-neutral-500'
                );
            }

            if (room.hk_status === 'dirty') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-gray-100 border-gray-300 text-gray-700'
                );
            }

            if (room.hk_status === 'cleaning') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-blue-50 border-blue-200 text-blue-800'
                );
            }

            if (room.hk_status === 'awaiting_inspection') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-teal-50 border-teal-200 text-teal-800'
                );
            }

            if (room.hk_status === 'redo') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-rose-50 border-rose-200 text-rose-800'
                );
            }

            if (room.status === 'active') {
                return (
                    base +
                    urgentHighlight +
                    ' bg-emerald-50 border-emerald-200 text-emerald-800'
                );
            }

            return (
                base +
                urgentHighlight +
                ' bg-emerald-50 border-emerald-200 text-emerald-800'
            );
        },
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
        priorityBadge(priority) {
            switch (priority) {
                case 'urgent':
                    return {
                        label: 'Urgent',
                        classes: 'bg-rose-100 text-rose-700 border-rose-300',
                        icon: AlertTriangle,
                        iconClass: 'text-rose-600',
                    };
                case 'high':
                    return {
                        label: 'Haute',
                        classes: 'bg-amber-100 text-amber-700 border-amber-300',
                        icon: AlertTriangle,
                        iconClass: 'text-amber-600',
                    };
                case 'normal':
                    return {
                        label: 'Normale',
                        classes: 'bg-indigo-50 text-indigo-700 border-indigo-200',
                        icon: CheckCircle,
                        iconClass: 'text-indigo-600',
                    };
                case 'low':
                    return {
                        label: 'Basse',
                        classes: 'bg-gray-100 text-gray-600 border-gray-300',
                        icon: ShieldCheck,
                        iconClass: 'text-gray-500',
                    };
                default:
                    return {
                        label: priority ?? '—',
                        classes: 'bg-gray-100 text-gray-600 border-gray-300',
                        icon: null,
                        iconClass: '',
                    };
            }
        },
        statusLabel(status) {
            switch (status) {
                case 'pending':
                    return 'En attente';
                case 'confirmed':
                    return 'Confirmée';
                case 'in_house':
                    return 'En séjour';
                case 'checked_out':
                    return 'Terminée';
                case 'cancelled':
                    return 'Annulée';
                case 'no_show':
                    return 'No-show';
                default:
                    return status ?? '';
            }
        },
        statusClass(status) {
            switch (status) {
                case 'pending':
                    return 'bg-amber-100 text-amber-700';
                case 'confirmed':
                    return 'bg-blue-100 text-blue-700';
                case 'in_house':
                    return 'bg-emerald-100 text-emerald-700';
                case 'checked_out':
                    return 'bg-slate-100 text-slate-700';
                case 'cancelled':
                    return 'bg-rose-100 text-rose-700';
                case 'no_show':
                    return 'bg-orange-100 text-orange-700';
                default:
                    return 'bg-slate-100 text-slate-700';
            }
        },
        maintenanceSeverityLabel(severity) {
            switch (severity) {
                case 'critical':
                    return 'Gravité critique';
                case 'high':
                    return 'Gravité haute';
                case 'low':
                    return 'Gravité basse';
                case 'medium':
                default:
                    return 'Gravité moyenne';
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
        maintenanceStatusLabel(status) {
            switch (status) {
                case 'in_progress':
                    return 'En cours';
                case 'resolved':
                    return 'Résolu';
                case 'closed':
                    return 'Clôturé';
                case 'open':
                default:
                    return 'Ouvert';
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
        getReservationHkStatus(reservationId) {
            if (this.selectedRoom?.current_reservation?.id?.toString() === reservationId?.toString()) {
                return this.selectedRoom.hk_status ?? null;
            }

            const room = (this.roomsByFloorLocal || []).flat().find(
                (r) => r.current_reservation?.id?.toString() === reservationId?.toString(),
            );

            return room?.hk_status ?? null;
        },
        async changeStatus(action) {
            const reservation = this.selectedRoom?.current_reservation;

            if (!reservation || this.statusSubmitting) {
                return;
            }

            if (action === 'check_in') {
                const hkStatus = this.getReservationHkStatus(reservation.id);

                if (hkStatus && hkStatus !== 'inspected') {
                    const warning = await Swal.fire({
                        title: 'Chambre non prête',
                        text: 'Cette chambre n’est pas inspectée. Voulez-vous continuer le check-in ?',
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

            if (['cancel', 'no_show'].includes(action)) {
                this.promptPenalty(action, reservation.id);

                return;
            }

            if (['check_in', 'check_out'].includes(action)) {
                await this.promptActualDateTime(action, reservation.id);

                return;
            }

            this.simpleStatusConfirm(action, reservation.id);
        },
        simpleStatusConfirm(action, reservationId) {
            Swal.fire({
                title: 'Confirmer cette action ?',
                text: this.getActionLabel(action),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                this.sendStatusRequest(action, reservationId, {});
            });
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

            const preview = await this.confirmStayAdjustments(action, reservationId, value);

            if (!preview.continue) {
                return;
            }

            const payload = isCheckIn
                ? { actual_check_in_at: value }
                : { actual_check_out_at: value };

            const combinedOverrides = preview.overrides || {};
            this.pendingFeeOverrides = {
                early: combinedOverrides.early_fee_override ?? null,
                late: combinedOverrides.late_fee_override ?? null,
            };

            this.sendStatusRequest(action, reservationId, { ...payload, ...combinedOverrides }, combinedOverrides);
        },
        async confirmStayAdjustments(action, reservationId, actualDatetime) {
            if (!['check_in', 'check_out'].includes(action)) {
                return {
                    continue: true,
                    overrides: {},
                };
            }

            if (!navigator.onLine) {
                return {
                    continue: true,
                    overrides: {},
                };
            }

            try {
                const http = window.axios ?? axios;
                const response = await http.post(
                    `/reservations/${reservationId}/stay-adjustments/preview`,
                    {
                        action,
                        actual_datetime: actualDatetime,
                    },
                );

                const early = response.data?.early || {};
                const late = response.data?.late || {};
                const currency = response.data?.currency || this.selectedRoom?.current_reservation?.currency || 'XAF';
                const overrides = {};

                if (action === 'check_in' && early.blocked && !this.canOverrideFees) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Arrivée anticipée non autorisée',
                        text: early.reason || 'Arrivée anticipée refusée.',
                    });

                    return { continue: false, overrides: {} };
                }

                if (action === 'check_in' && early.blocked && this.canOverrideFees) {
                    const confirmOverride = await Swal.fire({
                        icon: 'warning',
                        title: 'Arrivée anticipée',
                        text: early.reason || 'Arrivée avant l’heure standard.',
                        showCancelButton: true,
                        confirmButtonText: 'Continuer',
                        cancelButtonText: 'Annuler',
                    });

                    if (!confirmOverride.isConfirmed) {
                        return { continue: false, overrides: {} };
                    }
                }

                if (action === 'check_in' && early.is_early_checkin && (early.fee ?? 0) > 0) {
                    const methods = this.paymentMethodOptions();
                    if (!methods.length) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Mode de paiement manquant',
                            text: 'Aucun mode de paiement actif n’est disponible.',
                        });

                        return { continue: false, overrides: {} };
                    }

                    const hasSession = await this.ensureFrontdeskCashSession();
                    if (!hasSession) {
                        return { continue: false, overrides: {} };
                    }

                    const optionsHtml = methods
                        .map((method) => `<option value="${method.id}">${method.name}</option>`)
                        .join('');
                    const defaultMethodId = this.defaultPaymentMethodId();
                    const message = early.reason
                        || `Un supplément sera appliqué (${this.formatFeeAmount(early.fee, currency)}).`;
                    const feePrompt = await Swal.fire({
                        title: 'Arrivée anticipée détectée',
                        html:
                            '<div class="text-left">'
                            + `<p class="text-sm text-gray-700">${message}</p>`
                            + (this.canOverrideFees
                                ? `<label class="mt-3 block text-xs font-semibold text-gray-600">Supplément (${currency})</label>`
                                    + `<input id="swal-early-fee" type="number" min="0" step="0.01" class="swal2-input" value="${early.fee ?? 0}">`
                                : '')
                            + '<label class="mt-3 block text-xs font-semibold text-gray-600">Mode de paiement</label>'
                            + `<select id="swal-early-payment" class="swal2-select">${optionsHtml}</select>`
                            + '</div>',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Valider',
                        cancelButtonText: 'Annuler',
                        focusConfirm: false,
                        didOpen: () => {
                            if (defaultMethodId) {
                                const select = document.getElementById('swal-early-payment');
                                if (select) {
                                    select.value = defaultMethodId.toString();
                                }
                            }
                        },
                        preConfirm: () => {
                            const paymentSelect = document.getElementById('swal-early-payment');
                            const paymentMethodId = paymentSelect?.value ? Number(paymentSelect.value) : null;
                            const amountInput = document.getElementById('swal-early-fee');
                            const rawAmount = amountInput?.value ?? '';
                            const overrideAmount = this.canOverrideFees ? Number(rawAmount) : null;

                            if (!paymentMethodId) {
                                Swal.showValidationMessage('Veuillez choisir un mode de paiement.');

                                return false;
                            }

                            if (this.canOverrideFees && (!Number.isFinite(overrideAmount) || overrideAmount < 0)) {
                                Swal.showValidationMessage('Le supplément doit être un montant valide.');

                                return false;
                            }

                            return {
                                payment_method_id: paymentMethodId,
                                fee_override: this.canOverrideFees ? overrideAmount : null,
                            };
                        },
                    });

                    if (!feePrompt.isConfirmed) {
                        return { continue: false, overrides: {} };
                    }

                    const paymentMethodId = feePrompt.value?.payment_method_id ?? null;
                    if (paymentMethodId) {
                        overrides.early_payment_method_id = paymentMethodId;
                    }

                    if (this.canOverrideFees) {
                        const overrideValue = Number(feePrompt.value?.fee_override ?? early.fee ?? 0);
                        overrides.early_fee_override = Number.isFinite(overrideValue) ? overrideValue : early.fee;
                    }
                } else if (action === 'check_in' && early.is_early_checkin && early.reason) {
                    await Swal.fire({
                        icon: 'info',
                        title: 'Arrivée anticipée',
                        text: early.reason,
                        confirmButtonText: 'OK',
                    });
                }

                if (action === 'check_out') {
                    if (late.blocked && !this.canOverrideFees) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Départ tardif non autorisé',
                            text: late.reason || 'Départ tardif refusé.',
                        });

                        return { continue: false, overrides: {} };
                    }

                    if (late.blocked && this.canOverrideFees) {
                        const confirmLate = await Swal.fire({
                            icon: 'warning',
                            title: 'Départ tardif',
                            text: late.reason || 'Départ au-delà de l’heure prévue.',
                            showCancelButton: true,
                            confirmButtonText: 'Continuer',
                            cancelButtonText: 'Annuler',
                        });

                        if (!confirmLate.isConfirmed) {
                            return { continue: false, overrides: {} };
                        }
                    }

                    if (late.is_late_checkout && (late.fee ?? 0) > 0) {
                        const methods = this.paymentMethodOptions();
                        if (!methods.length) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Mode de paiement manquant',
                                text: 'Aucun mode de paiement actif n’est disponible.',
                            });

                            return { continue: false, overrides: {} };
                        }

                        const hasSession = await this.ensureFrontdeskCashSession();
                        if (!hasSession) {
                            return { continue: false, overrides: {} };
                        }

                        const optionsHtml = methods
                            .map((method) => `<option value="${method.id}">${method.name}</option>`)
                            .join('');
                        const defaultMethodId = this.defaultPaymentMethodId();
                        const latePrompt = await Swal.fire({
                            title: 'Départ tardif détecté',
                            html:
                                `<div class="text-left">${this.buildLateCheckoutHtml(late, currency)}</div>`
                                + '<div class="mt-3 text-left">'
                                + '<label class="block text-xs font-semibold text-gray-600">Mode de paiement</label>'
                                + `<select id="swal-late-payment" class="swal2-select">${optionsHtml}</select>`
                                + '</div>'
                                + (this.canOverrideFees
                                    ? '<div class="mt-3 text-left">'
                                        + `<label class="block text-xs font-semibold text-gray-600">Supplément (${currency})</label>`
                                        + `<input id="swal-late-fee" type="number" min="0" step="0.01" class="swal2-input" value="${late.fee ?? 0}">`
                                        + '</div>'
                                    : ''),
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Valider',
                            cancelButtonText: 'Annuler',
                            focusConfirm: false,
                            didOpen: () => {
                                if (defaultMethodId) {
                                    const select = document.getElementById('swal-late-payment');
                                    if (select) {
                                        select.value = defaultMethodId.toString();
                                    }
                                }
                            },
                            preConfirm: () => {
                                const paymentSelect = document.getElementById('swal-late-payment');
                                const paymentMethodId = paymentSelect?.value ? Number(paymentSelect.value) : null;
                                const amountInput = document.getElementById('swal-late-fee');
                                const rawAmount = amountInput?.value ?? '';
                                const overrideAmount = this.canOverrideFees ? Number(rawAmount) : null;

                                if (!paymentMethodId) {
                                    Swal.showValidationMessage('Veuillez choisir un mode de paiement.');

                                    return false;
                                }

                                if (this.canOverrideFees && (!Number.isFinite(overrideAmount) || overrideAmount < 0)) {
                                    Swal.showValidationMessage('Le supplément doit être un montant valide.');

                                    return false;
                                }

                                return {
                                    payment_method_id: paymentMethodId,
                                    fee_override: this.canOverrideFees ? overrideAmount : null,
                                };
                            },
                        });

                        if (!latePrompt.isConfirmed) {
                            return { continue: false, overrides: {} };
                        }

                        const paymentMethodId = latePrompt.value?.payment_method_id ?? null;
                        if (paymentMethodId) {
                            overrides.late_payment_method_id = paymentMethodId;
                        }

                        if (this.canOverrideFees) {
                            const overrideLate = Number(latePrompt.value?.fee_override ?? late.fee ?? 0);
                            overrides.late_fee_override = Number.isFinite(overrideLate) ? overrideLate : late.fee;
                        }
                    }
                }

                return {
                    continue: true,
                    overrides,
                };
            } catch (error) {
                const message =
                    this.extractFirstError(error.response?.data?.errors, null)
                    ?? error.response?.data?.message
                    ?? 'Impossible de calculer le supplément.';

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });

                return {
                    continue: false,
                    overrides: {},
                };
            }
        },
        formatFeeAmount(value, currency) {
            const amount = Number(value || 0);
            const cur = currency || this.selectedRoom?.current_reservation?.currency || this.defaults?.currency || 'XAF';

            return `${amount.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            })} ${cur}`;
        },
        buildLateCheckoutHtml(late, currency) {
            const reason = late?.reason || 'Départ au-delà de l’heure prévue.';
            const fee = Number(late?.fee ?? 0);
            const feeType = late?.fee_type || 'flat';
            const feeValue = Number(late?.fee_value ?? 0);
            const minutes = Number(late?.minutes ?? 0);
            const expected = late?.expected_checkout_at ? this.formatDateTime(late.expected_checkout_at) : '';
            const actual = late?.actual_checkout_at ? this.formatDateTime(late.actual_checkout_at) : '';
            const graceMinutes = Number(late?.grace_minutes ?? 0);
            const formattedFee = this.formatFeeAmount(fee, currency);
            let calculation = '';

            if (fee > 0) {
                if (feeType === 'per_hour') {
                    const hours = Math.max(1, Math.ceil(minutes / 60));
                    calculation = `Calcul : ${hours} h × ${this.formatFeeAmount(feeValue, currency)} = ${formattedFee}`;
                } else if (feeType === 'per_day') {
                    const days = Math.max(1, Math.ceil(minutes / 1440));
                    calculation = `Calcul : ${days} j × ${this.formatFeeAmount(feeValue, currency)} = ${formattedFee}`;
                } else if (feeType === 'percent') {
                    calculation = `Calcul : ${feeValue}% = ${formattedFee}`;
                } else {
                    calculation = `Montant fixe : ${formattedFee}`;
                }
            }

            const toleranceDate = late?.expected_checkout_at && graceMinutes > 0
                ? new Date(new Date(late.expected_checkout_at).getTime() + graceMinutes * 60000)
                : null;
            const detailLines = [
                expected ? `<div>Heure prévue : ${expected}</div>` : '',
                toleranceDate
                    ? `<div>Heure avec tolérance : ${this.formatDateTime(toleranceDate)}</div>`
                    : '',
                actual ? `<div>Check-out saisi : ${actual}</div>` : '',
                minutes > 0 ? `<div>Différence avec tolérance : ${minutes} min</div>` : '',
            ].filter(Boolean).join('');

            const lines = `${detailLines ? `<div class="mt-1 text-xs text-gray-600">${detailLines}</div>` : ''}${calculation ? `<div class="mt-1 text-xs text-gray-600">${calculation}</div>` : ''}`;

            return `<div class="text-left text-sm"><div>${reason}</div>${lines}</div>`;
        },
        getActionLabel(action) {
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
        sendStatusRequest(action, reservationId, payload, overrides = {}) {
            this.statusSubmitting = true;

            const url = `/reservations/${reservationId}/status`;
            const data = {
                action,
                ...(payload || {}),
                ...(overrides || {}),
            };

            if (!navigator.onLine) {
                const tenantId = this.$page?.props?.auth?.user?.tenant_id;
                const hotelId = this.$page?.props?.auth?.activeHotel?.id ?? this.$page?.props?.auth?.user?.active_hotel_id;
                this.updateLocalRoomReservationStatus(reservationId, action, true);
                enqueue({
                    type: 'reservation.transition',
                    endpoint: url,
                    method: 'patch',
                    payload: data,
                    tenant_id: tenantId,
                    hotel_id: hotelId,
                });
                Swal.fire({
                    icon: 'info',
                    title: 'Action en file',
                    text: 'La transition sera synchronisée dès le retour en ligne.',
                    timer: 2000,
                    showConfirmButton: false,
                });
                this.statusSubmitting = false;

                return;
            }

            router.patch(
                url,
                data,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        this.reloadRoomBoard();
                        if (overrides?.early_payment_method_id || overrides?.late_payment_method_id) {
                            window.dispatchEvent(new CustomEvent('cash-session-updated', {
                                detail: { type: 'frontdesk' },
                            }));
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Statut mis à jour.',
                            timer: 1500,
                            showConfirmButton: false,
                        });
                    },
                    onError: (errors) => {
                        const firstError =
                            (typeof errors === 'string'
                                ? errors
                                : Object.values(errors || {})[0])
                            ?? 'Erreur lors de la mise à jour.';

                        if (errors?.cash_session) {
                            this.openCashSessionModal();
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: firstError,
                        });
                    },
                    onFinish: () => {
                        this.statusSubmitting = false;
                    },
                },
            );
        },
        updateLocalRoomReservationStatus(reservationId, action, pending = false) {
            const map = {
                confirm: 'confirmed',
                check_in: 'in_house',
                check_out: 'checked_out',
                cancel: 'cancelled',
                no_show: 'no_show',
            };
            const newStatus = map[action] ?? null;
            if (!newStatus) return;

            this.roomsByFloorLocal = this.roomsByFloorLocal.map((floorRooms) =>
                floorRooms.map((room) => {
                    if (room.current_reservation?.id === reservationId) {
                        return {
                            ...room,
                            current_reservation: {
                                ...room.current_reservation,
                                status: newStatus,
                                pending_sync: pending,
                            },
                        };
                    }

                    return room;
                }),
            );

            if (this.selectedRoom?.current_reservation?.id === reservationId) {
                this.selectedRoom = {
                    ...this.selectedRoom,
                    current_reservation: {
                        ...this.selectedRoom.current_reservation,
                        status: newStatus,
                        pending_sync: pending,
                    },
                };
            }
        },
    },
};
</script>
