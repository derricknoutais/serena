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

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
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
                                    class="rounded-full border px-2 py-0.5 font-medium"
                                    :class="hkBadge(room).classes"
                                >
                                    {{ hkBadge(room).label }}
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
                                v-if="canManageHousekeepingActions"
                                class="mt-2 flex flex-wrap gap-1 text-[10px]"
                            >
                                <button
                                    v-if="canMarkDirty"
                                    type="button"
                                    class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-600 shadow"
                                    @click.stop="updateRoomHkStatus(room.id, 'dirty')"
                                >
                                    Marquer sale
                                </button>
                                <button
                                    v-if="canMarkClean"
                                    type="button"
                                    class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-600 shadow"
                                    @click.stop="updateRoomHkStatus(room.id, 'clean')"
                                >
                                    Marquer propre
                                </button>
                                <button
                                    v-if="canMarkInspected"
                                    type="button"
                                    class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-600 shadow"
                                    @click.stop="updateRoomHkStatus(room.id, 'inspected')"
                                >
                                    Marquer inspectée
                                </button>
                            </div>

                            <div
                                v-if="room.current_reservation"
                                class="mt-3 space-y-1 text-xs text-serena-text-muted"
                            >
                                <p class="font-medium text-serena-text-main">
                                    {{ room.current_reservation.guest_name || 'Réservation' }}
                                </p>
                                <p>
                                    {{ room.current_reservation.check_in_date }} →
                                    {{ room.current_reservation.check_out_date }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:w-1/3">
                <div class="relative h-full rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
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
                                    v-if="hkBadge(selectedRoom)"
                                    class="rounded-full border px-2 py-0.5 font-semibold"
                                    :class="hkBadge(selectedRoom).classes"
                                >
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
                                <span class="ml-1">{{ selectedRoom.hk_status }}</span>
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
                                        {{ selectedRoom.current_reservation.check_in_date }} →
                                        {{ selectedRoom.current_reservation.check_out_date }}
                                    </span>
                                </div>
                            </div>
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
                                    v-if="selectedRoom.current_reservation"
                                    type="button"
                                    class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                    @click="viewCurrentReservation(selectedRoom)"
                                >
                                    Voir la réservation
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
                                <button
                                    v-if="canMarkInspected"
                                    type="button"
                                    class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'inspected')"
                                >
                                    Marquer comme inspectée
                                </button>

                                <button
                                    v-if="canMarkDirty"
                                    type="button"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                    @click="updateRoomHkStatus(selectedRoom.id, 'dirty')"
                                >
                                    Marquer comme sale
                                </button>
                            </div>

                        </div>

                        <div class="space-y-3 rounded-lg border border-amber-100 bg-amber-50/40 p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-xs font-semibold text-amber-900">
                                        Maintenance
                                    </h4>
                                    <p
                                        v-if="selectedRoomMaintenance?.opened_at"
                                        class="text-[11px] text-amber-700"
                                    >
                                        Ticket ouvert le {{ formatDateTime(selectedRoomMaintenance.opened_at) }}
                                    </p>
                                </div>
                                <button
                                    v-if="canReportMaintenance && !selectedRoomMaintenance"
                                    type="button"
                                    class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50"
                                    @click="openMaintenanceModal(selectedRoom)"
                                >
                                    Déclarer un problème
                                </button>
                            </div>

                            <div
                                v-if="selectedRoomMaintenance"
                                class="space-y-2 text-xs text-amber-900"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">Statut :</span>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-900">
                                        {{ maintenanceStatusLabel(selectedRoomMaintenance.status) }}
                                    </span>
                                    <span class="font-semibold">Sévérité :</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="maintenanceBadge(selectedRoomMaintenance).classes"
                                    >
                                        {{ maintenanceSeverityLabel(selectedRoomMaintenance.severity) }}
                                    </span>
                                </div>
                                <div class="text-[11px]">
                                    <span class="font-semibold">Titre :</span>
                                    <span class="ml-1">{{ selectedRoomMaintenance.title }}</span>
                                </div>
                                <div
                                    v-if="selectedRoomMaintenance.description"
                                    class="text-[11px]"
                                >
                                    <span class="font-semibold">Description :</span>
                                    <span class="ml-1">{{ selectedRoomMaintenance.description }}</span>
                                </div>
                                <div class="text-[11px]">
                                    <span class="font-semibold">Déclaré par :</span>
                                    <span class="ml-1">{{ selectedRoomMaintenance.reported_by?.name || 'N/A' }}</span>
                                </div>
                                <div class="text-[11px]">
                                    <span class="font-semibold">Assigné à :</span>
                                    <span class="ml-1">
                                        {{ selectedRoomMaintenance.assigned_to?.name || 'Non assigné' }}
                                    </span>
                                </div>

                                <div
                                    v-if="selectedRoomMaintenance && (canProgressMaintenance || canHandleMaintenance)"
                                    class="flex flex-wrap gap-2 pt-2"
                                >
                                    <button
                                        v-if="canHandleMaintenance && selectedRoomMaintenance && (!selectedRoomMaintenance.assigned_to || selectedRoomMaintenance.assigned_to.id !== currentUserId)"
                                        type="button"
                                        class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-60"
                                        :disabled="maintenanceStatusSubmitting"
                                        @click="assignMaintenanceToSelf"
                                    >
                                        Me l'assigner
                                    </button>
                                    <button
                                        v-if="canProgressMaintenance && selectedRoomMaintenance.status === 'open'"
                                        type="button"
                                        class="rounded-lg border border-amber-300 bg-white px-3 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-50 disabled:opacity-60"
                                        :disabled="maintenanceStatusSubmitting"
                                        @click="updateMaintenanceStatus('in_progress')"
                                    >
                                        Mettre en cours
                                    </button>
                                    <button
                                        v-if="canHandleMaintenance && ['open', 'in_progress'].includes(selectedRoomMaintenance.status)"
                                        type="button"
                                        class="rounded-lg border border-green-300 bg-green-50 px-3 py-1 text-[11px] font-semibold text-green-700 hover:bg-green-100 disabled:opacity-60"
                                        :disabled="maintenanceStatusSubmitting"
                                        @click="updateMaintenanceStatus('resolved')"
                                    >
                                        Résoudre
                                    </button>
                                    <button
                                        v-if="canHandleMaintenance && selectedRoomMaintenance.status !== 'closed'"
                                        type="button"
                                        class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                                        :disabled="maintenanceStatusSubmitting"
                                        @click="updateMaintenanceStatus('closed')"
                                    >
                                        Clôturer
                                    </button>
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
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    @click="openStayModal('extend')"
                                >
                                    Prolonger
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    @click="openStayModal('shorten')"
                                >
                                    Raccourcir
                                </button>
                                <button
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
                                <select
                                    id="offer_id"
                                    v-model.number="form.offer_id"
                                    @change="onOfferChange"
                                    class="w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                >
                                    <option
                                        v-for="offer in walkInOffers"
                                        :key="offer.id"
                                        :value="offer.id"
                                    >
                                        {{ offer.name }} — {{ offer.price }}
                                    </option>
                                </select>
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
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <TextInput
                                        id="adults"
                                        v-model="form.adults"
                                        type="number"
                                        min="1"
                                        label="Adultes"
                                    />
                                    <p
                                        v-if="form.errors.adults"
                                        class="mt-1 text-xs text-serena-danger"
                                    >
                                        {{ form.errors.adults }}
                                    </p>
                                </div>
                                <div>
                                    <TextInput
                                        id="children"
                                        v-model="form.children"
                                        type="number"
                                        min="0"
                                        label="Enfants"
                                    />
                                    <p
                                        v-if="form.errors.children"
                                        class="mt-1 text-xs text-serena-danger"
                                    >
                                        {{ form.errors.children }}
                                    </p>
                                </div>
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
                        <p>{{ selectedRoom.current_reservation.check_in_date }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Départ actuel</p>
                        <p>{{ selectedRoom.current_reservation.check_out_date }}</p>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500">Nouveau départ</label>
                    <input
                        v-model="stayModalDate"
                        type="date"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        :min="stayModalMin"
                        :max="stayModalMax"
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
                { value: 'clean', label: 'Propre' },
                { value: 'dirty', label: 'Sale' },
                { value: 'inspected', label: 'Inspectée' },
            ],
            showStayModal: false,
            stayModalMode: 'extend',
            stayModalDate: '',
            stayModalSubmitting: false,
            showChangeRoomModal: false,
            changeRoomSelection: null,
            changeRoomSubmitting: false,
            showMaintenanceModal: false,
            maintenanceForm: {
                title: '',
                severity: 'medium',
                description: '',
            },
            maintenanceFormErrors: {},
            maintenanceSeverityOptions: [
                { value: 'low', label: 'Gravité basse' },
                { value: 'medium', label: 'Gravité moyenne' },
                { value: 'high', label: 'Gravité haute' },
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
            const permissions = this.$page?.props?.auth?.can ?? {};

            return permissions.reservations_override_datetime ?? false;
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
                return this.selectedRoom.current_reservation.check_out_date;
            }

            return this.selectedRoom.current_reservation.check_in_date;
        },
        stayModalMax() {
            if (!this.selectedRoom?.current_reservation) {
                return undefined;
            }

            if (this.stayModalMode === 'shorten') {
                return this.selectedRoom.current_reservation.check_out_date;
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
            const nights = this.calculateStayUnits(
                reservation.offer_kind || 'night',
                reservation.check_in_date,
                this.stayModalDate,
            );
            const unitPrice = Number(reservation.unit_price || 0);

            return {
                nights,
                total: nights * unitPrice,
            };
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
        canReportMaintenance() {
            return (this.permissionFlags.maintenance_tickets_create ?? this.maintenancePermissions?.canReport) || false;
        },
        canHandleMaintenance() {
            return (this.permissionFlags.maintenance_tickets_close ?? this.maintenancePermissions?.canHandle) || false;
        },
        canProgressMaintenance() {
            return (this.permissionFlags.maintenance_tickets_update ?? this.maintenancePermissions?.canProgress) || false;
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
        selectedRoomMaintenance() {
            return this.selectedRoom?.maintenance_ticket ?? null;
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
                if (newRoom && this.walkInDefaultDates && this.walkInOffers) {
                    const initialOffer = this.walkInOffers.length
                        ? this.walkInOffers[0]
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
                        check_in_at: this.toDateTimeLocal(start),
                        check_out_at: this.toDateTimeLocal(end),
                        adults: 1,
                        children: 0,
                        amount_received: '',
                    });

                    this.isWalkInOpen = true;
                } else {
                    this.isWalkInOpen = false;
                    this.form = null;
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
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
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
                    arrival_at: start.toISOString(),
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
                    const response = await axios.get('/guests/search', {
                        params: { search: term },
                        headers: { Accept: 'application/json' },
                    });

                    const results = Array.isArray(response.data) ? response.data : [];

                    this.localGuests = results.map((g) => ({
                        ...g,
                        full_name:
                            g.full_name
                            || `${g.last_name ?? ''} ${g.first_name ?? ''}`.trim(),
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
                    + '</div>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Créer',
                cancelButtonText: 'Annuler',
                preConfirm: () => {
                    const lastNameInput = document.getElementById('swal-guest-last-name');
                    const firstNameInput = document.getElementById('swal-guest-first-name');
                    const phoneInput = document.getElementById('swal-guest-phone');

                    const last_name = (lastNameInput?.value ?? '').toString().trim();
                    const first_name = (firstNameInput?.value ?? '').toString().trim();
                    const phone = (phoneInput?.value ?? '').toString().trim();

                    if (!last_name) {
                        Swal.showValidationMessage('Le nom est obligatoire.');

                        return false;
                    }

                    return {
                        last_name,
                        first_name,
                        phone,
                    };
                },
            });

            if (!isConfirmed || !formValues) {
                return;
            }

            try {
                const response = await axios.post(
                    '/guests',
                    {
                        first_name: formValues.first_name,
                        last_name: formValues.last_name,
                        phone: formValues.phone || null,
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
                        || `${newGuest.last_name ?? ''} ${newGuest.first_name ?? ''}`.trim(),
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
            if (!this.selectedRoom?.current_reservation) {
                return;
            }

            this.stayModalMode = mode;
            const currentDeparture = this.selectedRoom.current_reservation.check_out_date;

            if (mode === 'extend') {
                this.stayModalDate = this.addDays(currentDeparture, 1);
            } else {
                this.stayModalDate = currentDeparture;
            }

            this.showStayModal = true;
        },
        closeStayModal() {
            this.showStayModal = false;
            this.stayModalSubmitting = false;
        },
        async submitStayModal() {
            if (!this.selectedRoom?.current_reservation || !this.stayModalDate) {
                return;
            }

            this.stayModalSubmitting = true;

            try {
                await axios.patch(
                    `/reservations/${this.selectedRoom.current_reservation.id}/stay/dates`,
                    {
                        check_out_date: this.stayModalDate,
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
        openChangeRoomModal() {
            if (!this.selectedRoom?.current_reservation) {
                return;
            }

            this.changeRoomSelection = null;
            this.showChangeRoomModal = true;
        },
        closeChangeRoomModal() {
            this.showChangeRoomModal = false;
            this.changeRoomSubmitting = false;
            this.loadingRoomId = null;
        },
        async submitChangeRoom() {
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
            this.maintenanceForm = {
                title: '',
                severity: 'medium',
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
                const response = await axios.post('/maintenance-tickets', {
                    room_id: this.selectedRoom.id,
                    ...this.maintenanceForm,
                });

                const ticket = response.data?.ticket ?? null;

                if (ticket) {
                    this.applyRoomPatch(this.selectedRoom.id, {
                        maintenance_ticket: ticket,
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
        async assignMaintenanceToSelf() {
            if (!this.selectedRoomMaintenance || !this.currentUserId) {
                return;
            }

            await this.updateMaintenanceStatus(this.selectedRoomMaintenance.status, {
                assigned_to_user_id: this.currentUserId,
            });
        },
        async updateMaintenanceStatus(status, extra = {}) {
            if (!this.selectedRoomMaintenance) {
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
                    `/maintenance-tickets/${this.selectedRoomMaintenance.id}`,
                    payload,
                );

                const ticket = response.data?.ticket ?? null;

                if (ticket) {
                    this.applyRoomPatch(this.selectedRoom.id, {
                        maintenance_ticket: ticket,
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
        shouldOfferRoomRestore() {
            if (!this.selectedRoom) {
                return false;
            }

            return this.selectedRoom.status === 'out_of_order' && !this.selectedRoom.is_occupied;
        },
        calculateStayUnits(kind, start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);

            if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
                return 0;
            }

            const msPerDay = 1000 * 60 * 60 * 24;
            const nights = Math.max(1, Math.round((endDate - startDate) / msPerDay));

            switch (kind) {
                case 'short_stay':
                    return 1;
                case 'weekend':
                    return Math.max(2, nights);
                default:
                    return nights;
            }
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

            return date.toLocaleString();
        },
        addDays(dateStr, days) {
            const date = new Date(dateStr);
            if (Number.isNaN(date.getTime())) {
                return dateStr;
            }

            date.setDate(date.getDate() + days);

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },
        async openFolioFromRoom(tab = 'charges') {
            if (!this.selectedRoom || !this.selectedRoom.current_reservation) {
                return;
            }

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
            }, 800);
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

            router.get(`/reservations/${room.current_reservation.id}`);
        },
        async onOfferChange() {
            if (!this.form) {
                return;
            }

            const selected = this.walkInOffers.find(
                (offer) => offer.id === this.form.offer_id,
            );

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
        submitWalkIn() {
            if (!this.form) {
                return;
            }

            this.form.post('/frontdesk/room-board/walk-in', {
                onSuccess: () => {
                    this.isWalkInOpen = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Réservation walk-in enregistrée avec succès.',
                        timer: 2500,
                        showConfirmButton: false,
                    });
                    this.reloadRoomBoard();
                },
                onError: (errors) => {
                    this.handleAvailabilityErrors(errors);
                },
            });
        },
        closeWalkIn() {
            this.isWalkInOpen = false;
            this.reloadRoomBoard();
        },
        async updateRoomHkStatus(roomId, hkStatus) {
            const permissionMap = {
                clean: this.canMarkClean,
                dirty: this.canMarkDirty,
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
                const response = await window.axios.patch(
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
                return;
            }

            const message = errors.room_id ?? errors.room_type_id ?? null;

            if (!message) {
                const fallbackMessage = this.extractFirstError(errors, null);

                if (!fallbackMessage) {
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de réservation',
                    text: fallbackMessage,
                });

                return;
            }

            const text = Array.isArray(message) ? message[0] : message;

            Swal.fire({
                icon: 'warning',
                title: 'Indisponible',
                text,
            });
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
                case 'hk_updated':
                    return 'Statut ménage mis à jour';
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
                'cursor-pointer rounded-xl border p-4 text-sm shadow-sm transition';

            if (
                room.status === 'out_of_order' ||
                room.ui_status === 'out_of_order'
            ) {
                return (
                    base +
                    ' bg-[#E5E7EB] border-[#6B7280] text-[#374151] opacity-80'
                );
            }

            if (room.ui_status === 'occupied') {
                return (
                    base +
                    ' bg-[#FEE2E2] border-[#EF4444] text-[#991B1B]'
                );
            }

            if (room.ui_status === 'available') {
                switch (room.hk_status) {
                    case 'inspected':
                        return (
                            base +
                            ' bg-[#D1FADF] border-[#4ADE80] text-[#166534]'
                        );
                    case 'dirty':
                        return (
                            base +
                            ' bg-[#FEF3C7] border-[#F59E0B] text-[#92400E]'
                        );
                    case 'clean':
                    default:
                        return (
                            base +
                            ' bg-[#E8F7FD] border-[#25B0EB] text-[#1E8FBE]'
                        );
                }
            }

            return (
                base +
                ' bg-[#E8F7FD] border-[#25B0EB] text-[#1E8FBE]'
            );
        },
        availabilityBadge(room) {
            switch (room.ui_status) {
                case 'occupied':
                    return {
                        label: 'Occupée',
                        classes:
                            'bg-red-100 text-red-700 border-red-300',
                    };
                case 'out_of_order':
                    return {
                        label: 'Hors service',
                        classes:
                            'bg-gray-200 text-gray-700 border-gray-300',
                    };
                default:
                    return {
                        label: 'Disponible',
                        classes:
                            'bg-[#E8F7FD] text-[#1E8FBE] border-[#25B0EB]',
                    };
            }
        },
        hkStatusLabel(status) {
            switch (status) {
                case 'dirty':
                    return 'Sale';
                case 'inspected':
                    return 'Inspectée';
                case 'clean':
                default:
                    return 'Propre';
            }
        },
        hkBadge(room) {
            switch (room.hk_status) {
                case 'inspected':
                    return {
                        label: 'Inspectée',
                        classes:
                            'bg-green-100 text-green-800 border-green-300',
                    };
                case 'dirty':
                    return {
                        label: 'Sale',
                        classes:
                            'bg-amber-100 text-amber-700 border-amber-300',
                    };
                case 'clean':
                default:
                    return {
                        label: 'Propre',
                        classes:
                            'bg-blue-100 text-blue-700 border-blue-300',
                    };
            }
        },
        maintenanceSeverityLabel(severity) {
            switch (severity) {
                case 'high':
                    return 'Gravité haute';
                case 'low':
                    return 'Gravité basse';
                case 'medium':
                default:
                    return 'Gravité moyenne';
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

                if (hkStatus && !['clean', 'inspected'].includes(hkStatus)) {
                    const warning = await Swal.fire({
                        title: 'Chambre non prête',
                        text: 'Cette chambre est signalée comme sale ou à inspecter. Voulez-vous continuer le check-in ?',
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

                if (early.blocked && !this.canOverrideFees) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Arrivée anticipée non autorisée',
                        text: early.reason || 'Arrivée anticipée refusée.',
                    });

                    return { continue: false, overrides: {} };
                }

                if (early.blocked && this.canOverrideFees) {
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

                if (early.is_early_checkin && (early.fee ?? 0) > 0) {
                    const message = early.reason
                        || `Un supplément sera appliqué (${this.formatFeeAmount(early.fee, currency)}).`;
                    const feePrompt = await Swal.fire({
                        title: 'Arrivée anticipée détectée',
                        text: message,
                        icon: 'info',
                        input: this.canOverrideFees ? 'number' : null,
                        inputValue: early.fee ?? 0,
                        inputLabel: `Supplément (${currency})`,
                        showCancelButton: true,
                        confirmButtonText: 'Valider',
                        cancelButtonText: 'Annuler',
                    });

                    if (!feePrompt.isConfirmed) {
                        return { continue: false, overrides: {} };
                    }

                    if (this.canOverrideFees) {
                        const overrideValue = Number(feePrompt.value ?? early.fee ?? 0);
                        overrides.early_fee_override = Number.isFinite(overrideValue) ? overrideValue : early.fee;
                    }
                } else if (early.is_early_checkin && early.reason) {
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
                        const latePrompt = await Swal.fire({
                            title: 'Départ tardif détecté',
                            text: late.reason
                                || `Supplément de ${this.formatFeeAmount(late.fee, currency)}.`,
                            icon: 'info',
                            input: this.canOverrideFees ? 'number' : null,
                            inputValue: late.fee ?? 0,
                            inputLabel: `Supplément (${currency})`,
                            showCancelButton: true,
                            confirmButtonText: 'Valider',
                            cancelButtonText: 'Annuler',
                        });

                        if (!latePrompt.isConfirmed) {
                            return { continue: false, overrides: {} };
                        }

                        if (this.canOverrideFees) {
                            const overrideLate = Number(latePrompt.value ?? late.fee ?? 0);
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
