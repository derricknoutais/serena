<template>
    <div class="space-y-4">
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

            <div v-if="selectedEvent" class="mt-4 space-y-4 rounded-lg border border-gray-200 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800">{{ selectedEvent.title }}</span>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusClass(selectedEvent.status)"
                    >
                        {{ statusLabel(selectedEvent.status) }}
                    </span>
                    <span
                        v-if="selectedEvent?.pending_sync"
                        class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
                    >
                        Sync en attente
                    </span>
                </div>
                <p class="text-xs text-gray-600">
                    Arrivée :
                    <span class="font-medium text-gray-800">
                        {{ formatDateTime(selectedEvent.checkInDate || selectedEvent.check_in) }}
                    </span>
                </p>
                <p class="text-xs text-gray-600">
                    Départ :
                    <span class="font-medium text-gray-800">
                        {{ formatDateTime(selectedEvent.checkOutDate || selectedEvent.check_out) }}
                    </span>
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
                            @click="changeStatus('confirm')"
                        >
                            Confirmer
                        </button>
                        <button
                            v-if="selectedEvent?.status === 'pending'"
                            type="button"
                            class="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="statusSubmitting"
                            @click="changeStatus('cancel')"
                        >
                            Annuler
                        </button>
                        <button
                            v-if="selectedEvent?.status === 'confirmed'"
                            type="button"
                            class="rounded-lg bg-green-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="statusSubmitting"
                            @click="changeStatus('check_in')"
                        >
                            Check-in
                        </button>
                        <button
                            v-if="selectedEvent?.status === 'confirmed'"
                            type="button"
                            class="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="statusSubmitting"
                            @click="changeStatus('cancel')"
                        >
                            Annuler
                        </button>
                        <button
                            v-if="selectedEvent?.status === 'confirmed'"
                            type="button"
                            class="rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="statusSubmitting"
                            @click="changeStatus('no_show')"
                            >
                                No-show
                            </button>
                            <button
                                v-if="selectedEvent?.status === 'in_house'"
                            type="button"
                            class="rounded-lg bg-gray-800 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="statusSubmitting"
                            @click="changeStatus('check_out')"
                        >
                            Check-out
                            </button>
                    </div>
                    <button
                        type="button"
                        class="mt-2 w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="!selectedEvent?.id || folioLoading"
                        @click="openFolioForSelectedReservation('payments')"
                    >
                        {{ folioLoading ? 'Ouverture du folio...' : 'Encaisser / Folio' }}
                    </button>
                </div>

                <div
                    v-if="selectedEvent?.status === 'in_house' && canManageStayActions"
                    class="mt-3 space-y-2 rounded-lg border border-dashed border-gray-200 p-3"
                >
                    <label class="text-xs font-semibold text-gray-700">Gestion du séjour</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="canExtendStayAction"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                            @click="openStayModal('extend')"
                        >
                            Prolonger
                        </button>
                        <button
                            v-if="canShortenStayAction"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                            @click="openStayModal('shorten')"
                        >
                            Raccourcir
                        </button>
                        <button
                            v-if="canChangeRoomAction"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                            @click="openChangeRoomModal"
                        >
                            Changer de chambre
                        </button>
                    </div>
                </div>

                <div class="mt-3 border-t border-gray-100 pt-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700">Historique</span>
                        <button
                            type="button"
                            class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700"
                            @click="loadReservationActivity"
                        >
                            Actualiser
                        </button>
                    </div>
                    <div v-if="activityLoading" class="text-[11px] text-gray-500">
                        Chargement de l’historique…
                    </div>
                    <div v-else-if="reservationActivity.length === 0" class="text-[11px] text-gray-400">
                        Aucune activité récente.
                    </div>
                    <ul v-else class="max-h-40 space-y-1 overflow-y-auto text-[11px] text-gray-600">
                        <li
                            v-for="entry in reservationActivity"
                            :key="entry.id"
                            class="flex items-start justify-between gap-2"
                        >
                            <div>
                                <p class="font-medium text-gray-800">
                                    {{ activityLabel(entry) }}
                                </p>
                                <p
                                    v-if="entry.properties?.reservation_code"
                                    class="text-[10px] text-gray-500"
                                >
                                    Réservation {{ entry.properties.reservation_code }}
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
                                    :options="localGuests"
                                    track-by="id"
                                    label="name"
                                    :taggable="true"
                                    @search-change="onGuestSearchChange"
                                    @tag="onGuestTag"
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
                                    type="datetime-local"
                                    class="mt-1 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    :class="dateError ? 'border-red-400 focus:border-red-400 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500'"
                                    required
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Départ</label>
                                <input
                                    v-model="form.check_out_date"
                                    type="datetime-local"
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

                        <div v-if="!editingId">
                            <label class="text-sm font-medium text-gray-700">Statut</label>
                            <select
                                v-model="form.status"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            >
                                <option v-for="status in createStatusOptions" :key="status" :value="status">
                                    {{ statusLabel(status) }}
                                </option>
                            </select>
                        </div>
                        <div v-else>
                            <label class="text-sm font-medium text-gray-700">Statut</label>
                            <div class="mt-1 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ statusLabel(form.status) }}
                            </div>
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
            :initial-tab="folioInitialTab"
            :permissions="folioData.permissions || {}"
            @close="closeFolioModal"
            @updated="refreshFolioData"
        />

        <div
            v-if="showStayModal && selectedEvent"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ stayModalTitle }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            Réservation {{ selectedEvent.code || selectedEvent.title }}
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
                            <p>{{ formatDateTime(selectedEvent.checkInDate || selectedEvent.check_in) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500">Départ actuel</p>
                            <p>{{ formatDateTime(selectedEvent.checkOutDate || selectedEvent.check_out) }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Nouveau départ</label>
                        <input
                            v-model="stayModalDate"
                            type="datetime-local"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            :min="stayModalMin"
                            :max="stayModalMax"
                            step="900"
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
            v-if="showChangeRoomModal && selectedEvent"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Changer de chambre</h3>
                        <p class="text-xs text-gray-500">
                            Réservation {{ selectedEvent.code || selectedEvent.title }}
                        </p>
                    </div>
                    <button type="button" class="text-sm text-gray-500" @click="closeChangeRoomModal">
                        Fermer
                    </button>
                </div>

                <div class="space-y-3 text-sm text-gray-700">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Chambre actuelle</p>
                        <p v-if="selectedEvent.room_number">Chambre {{ selectedEvent.room_number }}</p>
                        <p v-else>Aucune chambre assignée</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500">Nouvelle chambre</label>
                        <select
                            v-model="changeRoomSelection"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        >
                            <option disabled :value="null">Sélectionner</option>
                            <option
                                v-for="room in plannerChangeRoomOptions"
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
    </div>
</template>

<script>
    import axios from 'axios';
    import { router } from '@inertiajs/vue3';
    import Swal from 'sweetalert2';
    import FullCalendar from '@fullcalendar/vue3';
    import dayGridPlugin from '@fullcalendar/daygrid';
    import FolioModal from '@/components/Frontdesk/FolioModal.vue';
    import Multiselect from 'vue-multiselect';
    import { enqueue } from '@/offline/outbox';

    export default {
        name: 'ReservationsPlanner',
        components: { FullCalendar, FolioModal, Multiselect },
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
            paymentMethods: {
                type: Array,
                default: () => [],
            },
            canManageTimes: {
                type: Boolean,
                required: true,
            },
            canExtendStay: {
                type: Boolean,
                required: true,
            },
            canShortenStay: {
                type: Boolean,
                required: true,
            },
            canChangeRoom: {
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
                folioInitialTab: 'charges',
                skipNextOfferDateApplication: false,
                showStayModal: false,
                stayModalMode: 'extend',
                stayModalDate: '',
                stayModalSubmitting: false,
                showChangeRoomModal: false,
                changeRoomSelection: null,
                changeRoomSubmitting: false,
                localGuests: [],
                guestSearchTimeout: null,
                activityLoading: false,
                activityRequestKey: null,
                reservationActivity: [],
                pendingFeeOverrides: {
                    early: null,
                    late: null,
                },
            };
        },
        created() {
            this.buildPriceMatrix();
            this.localGuests = [...this.guests];
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

                    if (this.selectedEvent?.id) {
                        const fresh = this.eventsLocal.find((e) => e.id == this.selectedEvent.id);
                        if (fresh) {
                            this.updateSelectedEventFromRaw(fresh);
                        }
                    }
                },
            },
            offerRoomTypePrices: {
                immediate: true,
                handler() {
                    this.buildPriceMatrix();
                },
            },
            guests: {
                immediate: true,
                handler(newGuests) {
                    this.localGuests = [...(newGuests || [])];
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
                        !this.isOfferCompatibleWithRoomType(newVal.id, this.selectedOffer.id)
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
            selectedEvent(newVal, oldVal) {
                const newId = newVal?.id ?? null;
                const oldId = oldVal?.id ?? null;

                if (!newId) {
                    this.reservationActivity = [];
                    this.activityLoading = false;
                    this.activityRequestKey = null;

                    return;
                }

                if (newId === oldId) {
                    return;
                }

                this.loadReservationActivity();
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
                if (newVal && this.skipNextOfferDateApplication) {
                    this.skipNextOfferDateApplication = false;
                } else {
                    this.applyOfferDates(newVal);
                }
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
            createStatusOptions() {
                return (this.statusOptions || []).filter((status) => ['pending', 'confirmed'].includes(status));
            },
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
            stayModalTitle() {
                return this.stayModalMode === 'extend'
                    ? 'Prolonger le séjour'
                    : 'Raccourcir le séjour';
            },
            permissionFlags() {
                return this.$page?.props?.auth?.can ?? {};
            },
            canOverrideTimes() {
                return this.permissionFlags.reservations_override_datetime ?? this.canManageTimes;
            },
            canExtendStayAction() {
                return Boolean(
                    (this.permissionFlags.reservations_extend_stay ?? this.canExtendStay)
                    || this.canOverrideTimes,
                );
            },
            canShortenStayAction() {
                return Boolean(
                    (this.permissionFlags.reservations_shorten_stay ?? this.canShortenStay)
                    || this.canOverrideTimes,
                );
            },
            canChangeRoomAction() {
                if (!this.selectedEvent) {
                    return false;
                }

                const hasPermission = this.permissionFlags.reservations_change_room ?? this.canChangeRoom;

                return (hasPermission || this.canOverrideTimes)
                    && ['confirmed', 'in_house'].includes(this.selectedEvent.status);
            },
            canManageStayActions() {
                return this.canExtendStayAction || this.canShortenStayAction || this.canChangeRoomAction;
            },
            canOverrideFees() {
                const roles = this.$page?.props?.auth?.user?.roles || [];
                const hasRole = roles.some((role) => ['owner', 'manager'].includes(role.name));

                return this.canOverrideTimes || hasRole;
            },
            stayModalMin() {
                if (!this.selectedEvent) {
                    return undefined;
                }

                const source = this.stayModalMode === 'extend'
                    ? this.selectedEvent.checkOutDate
                        ?? this.selectedEvent.check_out
                        ?? this.selectedEvent.end
                    : this.selectedEvent.checkInDate
                        ?? this.selectedEvent.check_in
                        ?? this.selectedEvent.start;

                const normalized = this.normalizeDateTimeLocal(source);

                return normalized || undefined;
            },
            stayModalMax() {
                if (!this.selectedEvent || this.stayModalMode !== 'shorten') {
                    return undefined;
                }

                const source = this.selectedEvent.checkOutDate
                    ?? this.selectedEvent.check_out
                    ?? this.selectedEvent.end;

                const normalized = this.normalizeDateTimeLocal(source);

                return normalized || undefined;
            },
        stayModalSummary() {
                if (!this.selectedEvent || !this.stayModalDate) {
                    return {
                        nights: 0,
                        total: 0,
                    };
                }

                const offer = this.currentEventOffer;
                const kind = offer?.kind || this.selectedEvent.offer_kind || this.selectedEvent.offerKind || 'night';
                const start = this.normalizeDateTimeLocal(
                    this.selectedEvent.checkInDate
                        ?? this.selectedEvent.check_in
                        ?? this.selectedEvent.start,
                );

                if (!start) {
                    return {
                        nights: 0,
                        total: 0,
                    };
                }

                const nights = this.calculateStayUnits(
                    kind,
                    start,
                    this.stayModalDate,
                    offer,
                );
                const unitPrice = Number(this.selectedEvent.unit_price || 0);

                return {
                    nights,
                    total: nights * unitPrice,
                };
            },
            currentEventOffer() {
                if (!this.selectedEvent?.offer_id) {
                    return null;
                }

                const offerId = Number(this.selectedEvent.offer_id);

                return this.offers.find((offer) => Number(offer.id) === offerId) ?? null;
            },
            plannerChangeRoomOptions() {
                if (!this.selectedEvent) {
                    return [];
                }

                const currentRoomId = this.selectedEvent.room_id ?? null;

                return this.rooms
                    .filter((room) => {
                        if (!room || room.status !== 'active') {
                            return false;
                        }

                        if (room.id === currentRoomId) {
                            return false;
                        }

                        return !room.current_reservation || room.current_reservation.id === this.selectedEvent.id;
                    });
            },
        },
        methods: {
            showUnauthorizedAlert() {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });
            },
            async loadReservationActivity() {
                if (!this.selectedEvent?.id) {
                    this.reservationActivity = [];
                    this.activityLoading = false;
                    this.activityRequestKey = null;

                    return;
                }

                const reservationId = this.selectedEvent.id;
                const requestKey = `${reservationId}-${Date.now()}`;

                this.activityRequestKey = requestKey;
                this.activityLoading = true;
                this.reservationActivity = [];

                try {
                    const response = await axios.get(`/reservations/${reservationId}/activity`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (this.activityRequestKey !== requestKey) {
                        return;
                    }

                    this.reservationActivity = Array.isArray(response.data) ? response.data : [];
                } catch {
                    if (this.activityRequestKey === requestKey) {
                        this.reservationActivity = [];
                    }
                } finally {
                    if (this.activityRequestKey === requestKey) {
                        this.activityLoading = false;
                    }
                }
            },
            activityLabel(entry) {
                const event = entry.event || entry.description || '';

                switch (event) {
                    case 'confirmed':
                        return 'Réservation confirmée';
                    case 'checked_in':
                        return 'Check-in effectué';
                    case 'checked_out':
                        return 'Check-out effectué';
                    case 'cancelled':
                        return 'Réservation annulée';
                    case 'no_show':
                        return 'Réservation marquée en no-show';
                    default:
                        return entry.description || 'Action';
                }
            },
            async onGuestSearchChange(query) {
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
                        const response = await axios.get('/settings/resources/guests/search', {
                            params: { search: term },
                            headers: { Accept: 'application/json' },
                        });

                        const results = Array.isArray(response.data) ? response.data : [];

                        this.localGuests = results.map((g) => ({
                            ...g,
                            name: g.full_name || `${g.first_name ?? ''} ${g.last_name ?? ''}`.trim(),
                        }));

                        if (this.selectedGuest && !this.localGuests.find((g) => g.id === this.selectedGuest.id)) {
                            this.localGuests.unshift(this.selectedGuest);
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
            async onGuestTag(inputValue) {
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
                        '/settings/resources/guests',
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
                        name:
                            newGuest.name
                            || `${newGuest.last_name ?? ''} ${newGuest.first_name ?? ''}`.trim(),
                    };

                    this.localGuests.push(guestWithName);
                    this.selectedGuest = guestWithName;
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
        refreshReservationsData() {
            router.reload({ only: ['reservationsData'] });
        },
        openStayModal(mode) {
            const canManageStay = mode === 'extend' ? this.canExtendStayAction : this.canShortenStayAction;

            if (!canManageStay) {
                this.showUnauthorizedAlert();

                return;
            }
            if (!this.selectedEvent?.id) {
                return;
            }

                const rawDeparture =
                    this.selectedEvent.checkOutDate
                        ?? this.selectedEvent.check_out
                        ?? this.selectedEvent.end;

                const departureDate = this.valueToDate(rawDeparture);

                if (!departureDate) {
                    return;
                }

                const targetDate = new Date(departureDate.getTime());

                this.stayModalMode = mode;
                this.stayModalDate = this.applyOfferCheckoutTime(targetDate) || this.toDateTimeLocal(targetDate);
                this.showStayModal = true;
            },
        closeStayModal() {
            this.showStayModal = false;
            this.stayModalSubmitting = false;
        },
        async submitStayModal() {
            const canManageStay = this.stayModalMode === 'extend'
                ? this.canExtendStayAction
                : this.canShortenStayAction;

            if (!canManageStay) {
                this.showUnauthorizedAlert();

                return;
            }
            if (!this.selectedEvent?.id || !this.stayModalDate) {
                return;
            }

                this.stayModalSubmitting = true;

                try {
                    await axios.patch(
                        `/reservations/${this.selectedEvent.id}/stay/dates`,
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
                    this.refreshReservationsData();
                } catch (error) {
                    const errors = error.response?.data?.errors ?? null;

                    if (error.response?.status === 403) {
                        this.showUnauthorizedAlert();

                        return;
                    }

                    if (this.handleAvailabilityErrors(errors)) {
                        return;
                    }

                    const message =
                        error.response?.data?.message
                        ?? this.extractFirstError(errors, null)
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
                if (!this.canChangeRoomAction || !this.selectedEvent?.id) {
                    if (this.selectedEvent?.id && !this.canChangeRoomAction) {
                        this.showUnauthorizedAlert();
                    }

                    return;
                }

                this.changeRoomSelection = null;
                this.showChangeRoomModal = true;
            },
            closeChangeRoomModal() {
                this.showChangeRoomModal = false;
                this.changeRoomSubmitting = false;
            },
            async submitChangeRoom() {
                if (!this.canChangeRoomAction) {
                    this.showUnauthorizedAlert();

                    return;
                }

                if (!this.selectedEvent?.id || !this.changeRoomSelection) {
                    return;
                }

                this.changeRoomSubmitting = true;

                try {
                    await axios.patch(
                        `/reservations/${this.selectedEvent.id}/stay/room`,
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
                    this.refreshReservationsData();
                } catch (error) {
                    const errors = error.response?.data?.errors ?? null;

                    if (this.handleAvailabilityErrors(errors)) {
                        return;
                    }

                    const message =
                        error.response?.data?.message
                        ?? this.extractFirstError(errors, null)
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
            calculateStayUnits(kind, start, end, offer = null) {
                const startDate = new Date(start);
                const endDate = new Date(end);

                if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
                    return 0;
                }

                const msPerDay = 1000 * 60 * 60 * 24;
                const nights = Math.max(1, Math.ceil((endDate - startDate) / msPerDay));

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
                const currency = this.selectedEvent?.currency
                    || this.defaults.currency
                    || 'XAF';

                return `${amount.toFixed(0)} ${currency}`;
            },
            formatFeeAmount(value, currency) {
                const amount = Number(value || 0);
                const cur = currency || this.defaults.currency || 'XAF';

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
            normalizeDateTimeLocal(value) {
                const date = this.valueToDate(value);

                if (!date) {
                    return '';
                }

                return this.toDateTimeLocal(date);
            },
            applyOfferCheckoutTime(value) {
                const date = this.valueToDate(value);

                if (!date) {
                    return '';
                }

                const checkoutTime = this.currentEventOffer?.check_out_until;

                if (typeof checkoutTime === 'string') {
                    const [hStr, mStr] = checkoutTime.split(':');
                    const h = Number(hStr);
                    const m = Number(mStr);

                    date.setHours(Number.isFinite(h) ? h : 0, Number.isFinite(m) ? m : 0, 0, 0);
                }

                return this.toDateTimeLocal(date);
            },
            valueToDate(value) {
                if (!value) {
                    return null;
                }

                if (value instanceof Date) {
                    return Number.isNaN(value.getTime()) ? null : new Date(value.getTime());
                }

                const date = new Date(value);

                return Number.isNaN(date.getTime()) ? null : date;
            },
            dateOnly(value) {
                if (!value) {
                    return '';
                }

                if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
                    return value.slice(0, 10);
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return '';
                }

                return date.toISOString().slice(0, 10);
            },
            async openFolioForSelectedReservation(tab = 'charges') {
                if (!this.selectedEvent?.id) {
                    return;
                }

                this.folioLoading = true;
                this.folioInitialTab = tab || 'charges';

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
                this.refreshReservationsData();
            },
            closeFolioModal() {
                this.showFolioModal = false;
                this.folioInitialTab = 'charges';
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
                let start = new Date(`${dateStr}T12:00:00`);
                if (Number.isNaN(start.getTime())) {
                    start = new Date();
                }
                const end = new Date(start.getTime() + 24 * 60 * 60 * 1000);
                this.form = {
                    ...this.form,
                    code: `RSV-${new Date().getTime()}`,
                    check_in_date: this.toDateTimeLocal(start),
                    check_out_date: this.toDateTimeLocal(end),
                    status: 'pending',
                    guest_id: '',
                    room_type_id: '',
                    room_id: null,
                    offer_id: null,
                    source: '',
                    expected_arrival_time: '',
                };
                this.skipNextOfferDateApplication = false;
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
            refreshEvents() {
                this.refreshReservationsData();
            },
            onEventClick(arg) {
                const raw = this.eventsLocal.find((e) => String(e.id) === String(arg.event.id));
                if (raw) {
                    this.updateSelectedEventFromRaw(raw);
                }
            },
            updateSelectedEventFromRaw(event) {
                const rawPlannedCheckIn = event.check_in_date ?? event.start;
                const rawActualCheckIn = event.actual_check_in_at ?? null;
                const checkIn = rawActualCheckIn || rawPlannedCheckIn;
                const checkOut = event.check_out_date ?? event.end;
                
                this.selectedEvent = {
                    id: event.id,
                    title: event.title,
                    code: event.code || event.title || '',
                    status: event.status || '',
                    start: event.start,
                    end: event.end,
                    check_in: checkIn,
                    check_out: checkOut,
                    checkInDate: checkIn,
                    checkOutDate: checkOut,
                    actualCheckInAt: rawActualCheckIn,
                    room_hk_status: event.room_hk_status ?? null,
                    room_id: event.room_id ?? null,
                    room_number: event.room_number ?? null,
                    room_type_id: event.room_type_id ?? null,
                    room_type_name: event.room_type_name ?? null,
                    unit_price: event.unit_price ?? 0,
                    currency: event.currency ?? this.defaults.currency ?? 'XAF',
                    offer_kind: event.offer_kind ?? event.kind ?? 'night',
                    offer_id: event.offer_id ?? null,
                };
            },
            onEventDidMount(arg) {
                arg.el.addEventListener('dblclick', () => {
                    const event = arg.event;
                    this.editingId = event.id ? event.id.toString() : null;
                    this.selectedEvent = {
                        id: event.id,
                        title: event.title,
                        code: event.extendedProps?.code ?? event.title ?? '',
                        status: event.extendedProps?.status ?? event.status ?? '',
                        start: event.startStr,
                        end: event.endStr,
                        check_in: event.extendedProps?.actual_check_in_at
                            ?? event.extendedProps?.check_in_date
                            ?? event.startStr,
                        check_out: event.extendedProps?.check_out_date ?? event.endStr,
                        checkInDate: event.extendedProps?.actual_check_in_at
                            ?? event.extendedProps?.check_in_date
                            ?? event.startStr,
                        checkOutDate: event.extendedProps?.check_out_date ?? event.endStr,
                        actualCheckInAt: event.extendedProps?.actual_check_in_at ?? null,
                        room_hk_status: event.extendedProps?.room_hk_status ?? null,
                        room_id: event.extendedProps?.room_id ?? null,
                        room_number: event.extendedProps?.room_number ?? null,
                        room_type_id: event.extendedProps?.room_type_id ?? null,
                        room_type_name: event.extendedProps?.room_type_name ?? null,
                        unit_price: event.extendedProps?.unit_price ?? 0,
                        currency: event.extendedProps?.currency ?? this.defaults.currency ?? 'XAF',
                        offer_kind: event.extendedProps?.offer_kind ?? event.extendedProps?.kind ?? 'night',
                    };
                    const rawCheckIn = event.extendedProps?.check_in_date;
                    const rawCheckOut = event.extendedProps?.check_out_date;
                    const resolvedCheckIn = rawCheckIn
                        ? new Date(rawCheckIn)
                        : event.start
                            ? new Date(event.start)
                            : null;
                    let resolvedCheckOut = rawCheckOut
                        ? new Date(rawCheckOut)
                        : null;
                    if (!resolvedCheckOut && event.end) {
                        resolvedCheckOut = new Date(event.end.getTime() - 24 * 60 * 60 * 1000);
                    }
                    if (!resolvedCheckOut && event.start) {
                        resolvedCheckOut = new Date(event.start);
                    }
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
                        check_in_date: resolvedCheckIn ? this.toDateTimeLocal(resolvedCheckIn) : '',
                        check_out_date: resolvedCheckOut ? this.toDateTimeLocal(resolvedCheckOut) : '',
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
                    this.skipNextOfferDateApplication = Boolean(offer);
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
            async changeStatus(action, reservationId = null) {
                const targetId = reservationId ?? this.selectedEvent?.id ?? null;

                if (!targetId || this.statusSubmitting) {
                    return;
                }

                if (action === 'check_in') {
                    const hkStatus = this.getReservationHkStatus(targetId);

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
                    this.promptPenalty(action, targetId);

                    return;
                }
                if (['check_in', 'check_out'].includes(action)) {
                    await this.promptActualDateTime(action, targetId);

                    return;
                }

                this.simpleStatusConfirm(action, targetId);
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
            eventClassNames(arg) {
                const status = arg.event.extendedProps?.status;
                switch (status) {
                    case 'confirmed':
                        return ['event-confirmed'];
                    case 'in_house':
                        return ['event-in-house'];
                    case 'checked_out':
                        return ['event-checked-out'];
                    case 'cancelled':
                        return ['event-cancelled'];
                    case 'no_show':
                        return ['event-no-show'];
                    default:
                        return ['event-pending'];
                }
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
                    const currency = response.data?.currency || this.defaults.currency || 'XAF';
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
                    this.updateLocalEventStatus(reservationId, action, true);
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
                            this.refreshEvents();
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
            updateLocalEventStatus(reservationId, action, pending = false) {
                const map = {
                    confirm: 'confirmed',
                    check_in: 'in_house',
                    check_out: 'checked_out',
                    cancel: 'cancelled',
                    no_show: 'no_show',
                };
                const newStatus = map[action] ?? null;
                if (!newStatus) return;

                this.eventsLocal = this.eventsLocal.map((event) => {
                    if (event.id === reservationId) {
                        return { ...event, status: newStatus, pending_sync: pending };
                    }
                    return event;
                });

                if (this.selectedEvent?.id === reservationId) {
                    this.selectedEvent = { ...this.selectedEvent, status: newStatus, pending_sync: pending };
                }
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
                const offer = this.selectedOffer;

                if (kind === 'short_stay') {
                    const diffMs = end.getTime() - start.getTime();
                    const diffHours = Math.max(1, Math.round(diffMs / (1000 * 60 * 60)));
                    units = diffHours;
                } else {
                    const diffMs = end.getTime() - start.getTime();
                    const diffDays = Math.max(1, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));

                    units = diffDays;

                    if (kind === 'weekend' || kind === 'package') {
                        units = Math.max(1, Math.ceil(diffDays / this.resolveBundleNights(offer, kind)));
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
            async applyOfferDates(offer) {
                if (!offer) {
                    return;
                }

                const now = new Date();
                let start = this.form.check_in_date
                    ? new Date(this.form.check_in_date)
                    : new Date();

                if (Number.isNaN(start.getTime())) {
                    start = new Date();
                }

                if (offer.check_in_from) {
                    const [hStr, mStr] = offer.check_in_from.split(':');
                    const h = Number(hStr) || 0;
                    const m = Number(mStr) || 0;
                    start.setHours(h, m, 0, 0);
                } else if (!this.form.check_in_date) {
                    start.setHours(now.getHours(), now.getMinutes(), 0, 0);
                }

                try {
                    const http = window.axios ?? axios;
                    const response = await http.post(`/api/offers/${offer.id}/time-preview`, {
                        arrival_at: this.toDateTimeLocal(start),
                    });

                    const arrival = new Date(response.data.arrival_at);
                    const departure = new Date(response.data.departure_at);

                    if (!Number.isNaN(arrival.getTime())) {
                        this.form.check_in_date = this.toDateTimeLocal(arrival);
                    }

                    if (!Number.isNaN(departure.getTime())) {
                        this.form.check_out_date = this.toDateTimeLocal(departure);
                    }
                } catch (error) {
                    console.error('Erreur lors du calcul des dates de l’offre', error);
                }
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

                if (this.editingId) {
                    const payload = {
                        ...this.form,
                    };
                    delete payload.status;

                    router.put(`/reservations/${this.editingId}`, payload, {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.refreshReservationsData();
                            this.closeModal();
                            this.notifyBookingSuccess('Réservation mise à jour avec succès.');
                        },
                        onError: (errors) => {
                            const handled = this.handleAvailabilityErrors(errors);

                            if (handled) {
                                return;
                            }

                            const message = this.extractFirstError(
                                errors,
                                'Impossible de mettre à jour la réservation.',
                            );

                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: message,
                            });
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    });
                } else {
                    const offerId = this.form.offer_id || this.selectedOffer?.id;
                    const roomId = this.form.room_id || this.selectedRoom?.id;
                    const guestId = this.form.guest_id || this.selectedGuest?.id;

                    if (!offerId || !roomId || !guestId) {
                        this.submitting = false;
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Veuillez sélectionner un client, une chambre et une offre.',
                        });

                        return;
                    }

                    const startAt = this.form.check_in_date;
                    const endAt = this.form.check_out_date;

                    axios
                        .post(
                            '/frontdesk/reservations/from-offer',
                            {
                                offer_id: offerId,
                                room_id: roomId,
                                guest_id: guestId,
                                start_at: startAt,
                                end_at: endAt,
                                status: this.form.status,
                                code: this.form.code,
                                notes: this.form.notes ?? null,
                            },
                            {
                                headers: {
                                    Accept: 'application/json',
                                },
                            },
                        )
                        .then((response) => {
                            const reservation = response.data?.reservation;

                            if (!reservation || !reservation.id) {
                                return;
                            }

                            const event = {
                                id: reservation.id,
                                title: this.form.code,
                                allDay: true,
                                start: reservation.check_in_date,
                                end: this.addOneDay(reservation.check_out_date),
                                status: reservation.status,
                                code: this.form.code,
                                guest_id: guestId,
                                room_id: roomId,
                                room_type_id: this.form.room_type_id,
                                offer_id: offerId,
                                currency: this.form.currency,
                                unit_price: this.form.unit_price,
                                base_amount: this.form.base_amount,
                                tax_amount: this.form.tax_amount,
                                total_amount: this.form.total_amount,
                                adults: this.form.adults,
                                children: this.form.children,
                                notes: this.form.notes,
                                source: this.form.source,
                                expected_arrival_time: this.form.expected_arrival_time,
                                check_in_date: this.form.check_in_date,
                                check_out_date: this.form.check_out_date,
                            };

                            this.eventsLocal = [...this.eventsLocal, event];
                            this.calendarOptions = {
                                ...this.calendarOptions,
                                events: this.eventsLocal,
                            };

                            this.closeModal();
                            this.notifyBookingSuccess('Réservation enregistrée avec succès.');
                        })
                        .catch((error) => {
                            const errors = error.response?.data?.errors || null;

                            if (errors) {
                                const handled = this.handleAvailabilityErrors(errors);
                                const offerError = errors.offer_id && (Array.isArray(errors.offer_id) ? errors.offer_id[0] : errors.offer_id);

                                if (handled) {
                                    return;
                                }

                                if (offerError) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Offre invalide',
                                        text: offerError,
                                    });
                                } else {
                                    const message = this.extractFirstError(
                                        errors,
                                        error.response?.data?.message ?? 'Impossible de créer la réservation.',
                                    );

                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Erreur',
                                        text: message,
                                    });
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: error.response?.data?.message ?? 'Impossible de créer la réservation.',
                                });
                            }
                        })
                        .finally(() => {
                            this.submitting = false;
                        });
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
            handleAvailabilityErrors(errors) {
                if (!errors) {
                    return false;
                }

                const message = errors.room_id
                    ?? errors.room_type_id
                    ?? errors.check_out_date
                    ?? errors.check_in_date
                    ?? errors;

                if (!message) {
                    return false;
                }

                const text = this.extractFirstError(message);

                Swal.fire({
                    icon: 'warning',
                    title: 'Indisponible',
                    text: text,
                });

                return true;
            },
            notifyBookingSuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: message,
                    timer: 2500,
                    showConfirmButton: false,
                });
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
            getReservationHkStatus(reservationId) {
                if (this.selectedEvent && this.selectedEvent.id?.toString() === reservationId?.toString()) {
                    return this.selectedEvent.room_hk_status ?? null;
                }

                const event = (this.eventsLocal || []).find(
                    (evt) => evt.id?.toString() === reservationId?.toString(),
                );

                if (!event) {
                    return null;
                }

                return event.room_hk_status ?? event.extendedProps?.room_hk_status ?? null;
            },
            formatDateTime(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString('fr-FR', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                });
            },
            isOfferCompatibleWithRoomType(roomTypeId, offerId) {
                if (!roomTypeId || !offerId) {
                    return false;
                }

                return Boolean(this.priceMatrix[`${roomTypeId}|${offerId}`]);
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
    .fc-event.event-pending {
        background-color: #fcebd9 !important; /* amber-100 */
        border-color: #f59e0b !important;
        color: #92400e !important;
    }

    .fc-event.event-confirmed {
        background-color: #d1fae5 !important; /* emerald-100 */
        border-color: #10b981 !important;
        color: #065f46 !important;
    }

    .fc-event.event-in-house {
        background-color: #dbeafe !important; /* blue-100 */
        border-color: #3b82f6 !important;
        color: #1e40af !important;
    }

    .fc-event.event-checked-out {
        background-color: #f3f4f6 !important; /* gray-100 */
        border-color: #9ca3af !important;
        color: #374151 !important;
    }

    .fc-event.event-cancelled {
        background-color: #fee2e2 !important; /* red-100 */
        border-color: #ef4444 !important;
        color: #991b1b !important;
        text-decoration: line-through;
        opacity: 0.7;
    }

    .fc-event.event-no-show {
        background-color: #581c87 !important; /* purple-900 */
        border-color: #a855f7 !important;
        color: #ffffff !important;
    }

    /* General Event Styling */
    .fc-event {
        border-radius: 4px;
        padding: 2px 4px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .fc-event:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
</style>
