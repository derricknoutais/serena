<template>
    <AppLayout title="Housekeeping">
        <div class="mx-auto flex max-w-md flex-col gap-4 p-4">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h1 class="text-lg font-semibold text-gray-900">Housekeeping</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Scannez une chambre pour gérer le ménage et signaler un problème.
                </p>
                <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    @click="startScan"
                    :disabled="scanning"
                >
                    {{ scanning ? 'Scanner en cours…' : 'Scanner une chambre' }}
                </button>
            </div>

            <div
                v-if="currentRoom"
                class="rounded-xl bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Chambre</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            {{ currentRoom.number }}
                        </p>
                        <p class="text-sm text-gray-500">
                            {{ currentRoom.room_type || 'Type inconnu' }}
                            <span v-if="currentRoom.floor"> · Étage {{ currentRoom.floor }}</span>
                        </p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusBadgeClasses(currentRoom.hk_status)"
                    >
                        {{ currentRoom.hk_status_label }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span
                        class="rounded-full px-3 py-1 font-semibold"
                        :class="priorityBadgeClasses(currentRoom.hk_priority)"
                    >
                        {{ priorityLabel(currentRoom.hk_priority) }}
                    </span>
                    <span
                        v-if="currentRoom.arrival_today"
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-semibold text-amber-700"
                    >
                        Arrivée aujourd’hui
                    </span>
                </div>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                    <p class="font-semibold text-gray-700">
                        Occupation : {{ currentRoom.occupancy?.state || 'Libre' }}
                    </p>
                    <div
                        v-if="currentRoom.occupancy?.reservation"
                        class="mt-2 text-xs text-gray-500"
                    >
                        <p>Code : {{ currentRoom.occupancy.reservation.code || '—' }}</p>
                        <p>Client : {{ currentRoom.occupancy.reservation.guest_name || '—' }}</p>
                        <p>
                            {{ currentRoom.occupancy.reservation.check_in_date }}
                            →
                            {{ currentRoom.occupancy.reservation.check_out_date }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="currentRoom.hk_priority === 'urgent'"
                    class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"
                >
                    Chambre prioritaire.
                </div>

                <div
                    v-if="currentRoom.hk_status === 'redo' && inspectionRemarks.length"
                    class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
                >
                    <p class="text-xs font-semibold uppercase text-rose-700">Remarques d’inspection</p>
                    <ul class="mt-2 space-y-1 text-xs">
                        <li v-for="(remark, index) in inspectionRemarks" :key="index">
                            <span v-if="remark.label" class="font-semibold">{{ remark.label }} :</span>
                            <span>{{ remark.note }}</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase text-gray-500">Tâche ménage</p>
                        <span
                            v-if="currentTask"
                            class="rounded-full px-2 py-1 text-[10px] font-semibold"
                            :class="taskStatusClasses(currentTask.status)"
                        >
                            {{ taskStatusLabel(currentTask.status) }}
                        </span>
                    </div>

                    <div v-if="currentTask" class="mt-3 flex flex-col gap-2 text-sm text-gray-600">
                        <div class="flex items-center justify-between">
                            <span>{{ taskTypeLabel(currentTask.type) }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="priorityBadgeClasses(currentTask.priority)"
                            >
                                {{ priorityLabel(currentTask.priority) }}
                            </span>
                        </div>
                        <p v-if="currentTask.started_at" class="text-xs text-gray-500">
                            Démarré : {{ formatDateTime(currentTask.started_at) }}
                        </p>
                        <p v-if="currentTask.ended_at" class="text-xs text-gray-500">
                            Terminé : {{ formatDateTime(currentTask.ended_at) }}
                        </p>
                        <p v-if="isParticipant" class="text-xs text-gray-500">
                            Vous participez à ce ménage.
                        </p>
                    </div>
                    <p v-else class="mt-2 text-sm text-gray-500">
                        Aucune tâche en cours.
                    </p>

                    <div v-if="currentTaskParticipants.length" class="mt-3">
                        <p class="text-xs uppercase text-gray-500">Participants</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="participant in currentTaskParticipants"
                                :key="participant.id"
                                class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200"
                            >
                                {{ participant.name }}
                            </span>
                        </div>
                    </div>

                    <p v-if="lastTask" class="mt-3 text-xs text-gray-500">
                        Dernière tâche terminée :
                        {{ formatDateTime(lastTask.ended_at || lastTask.started_at || lastTask.created_at) }}
                    </p>
                    <p v-if="taskElapsedLabel" class="mt-2 text-xs text-gray-500">
                        Timer en cours : {{ taskElapsedLabel }}
                    </p>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <div
                        v-if="currentRoom.hk_status === 'awaiting_inspection' && !canMarkInspected"
                        class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-700"
                    >
                        En attente d’inspection.
                    </div>
                    <button
                        v-if="canStartCleaning"
                        type="button"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                        @click="startCleaning"
                        :disabled="loading"
                    >
                        Commencer le ménage
                    </button>
                    <button
                        v-if="canJoinCleaning"
                        type="button"
                        class="w-full rounded-lg bg-indigo-100 px-4 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-200"
                        @click="joinCleaning"
                        :disabled="loading"
                    >
                        Rejoindre le ménage
                    </button>
                    <button
                        v-if="canFinishCleaning"
                        type="button"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        @click="finishCleaning"
                        :disabled="loading"
                    >
                        Terminer le ménage
                    </button>
                    <button
                        v-if="canStartInspection"
                        type="button"
                        class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                        @click="startInspection"
                        :disabled="loading"
                    >
                        Démarrer l’inspection
                    </button>
                    <button
                        v-if="canReportIssue"
                        type="button"
                        class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"
                        @click="openIncidentModal"
                        :disabled="loading"
                    >
                        Signaler un problème
                    </button>
                </div>

                <div
                    v-if="isInspectionTask && currentTask?.status === 'in_progress'"
                    class="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase text-gray-500">Checklist inspection</p>
                            <p class="text-sm text-gray-600">
                                {{ inspectionChecklist?.name || 'Checklist par défaut' }}
                            </p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-[10px] font-semibold text-blue-700">
                            Inspection
                        </span>
                    </div>

                    <div v-if="inspectionItems.length" class="mt-4 flex flex-col gap-4">
                        <div
                            v-for="item in inspectionItems"
                            :key="item.id"
                            class="rounded-lg border border-gray-200 bg-white p-3"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ item.label }}
                                    <span v-if="item.is_required" class="text-xs text-red-500">*</span>
                                </p>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="item.is_ok !== false ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                        @click="setInspectionStatus(item.id, true)"
                                    >
                                        OK
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="item.is_ok === false ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-500'"
                                        @click="setInspectionStatus(item.id, false)"
                                    >
                                        NOK
                                    </button>
                                </div>
                            </div>

                            <div v-if="item.is_ok === false" class="mt-3">
                                <textarea
                                    v-model="item.note"
                                    rows="2"
                                    placeholder="Décrire le problème (obligatoire)"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">
                        Aucune checklist active. Vous pouvez valider l’inspection directement.
                    </p>

                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <PrimaryButton
                            type="button"
                            class="flex-1"
                            :disabled="loading"
                            @click="finishInspection(false)"
                        >
                            Valider inspection
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            class="flex-1 border border-rose-200 text-rose-700 hover:bg-rose-50"
                            :disabled="loading"
                            @click="finishInspection(true)"
                        >
                            À refaire
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-if="canManageAnyHousekeeping"
                    class="mt-4 grid gap-2 sm:grid-cols-3"
                >
                    <!-- <button
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700"
                        @click="updateStatus('dirty')"
                        :disabled="loading || !canMarkDirty"
                    >
                        Marquer sale
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700"
                        @click="updateStatus('cleaning')"
                        :disabled="loading || !canMarkClean"
                    >
                        Marquer en cours
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700"
                        @click="updateStatus('inspected')"
                        :disabled="loading || !canMarkInspected"
                    >
                        Inspectée
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700"
                        @click="updateStatus('awaiting_inspection')"
                        :disabled="loading || !canMarkClean"
                    >
                        En attente d’inspection
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700"
                        @click="updateStatus('redo')"
                        :disabled="loading || !canMarkDirty"
                    >
                        À refaire
                    </button> -->
                </div>
                <p
                    v-else
                    class="mt-4 text-sm text-gray-500"
                >
                    Vous n’avez pas les droits pour modifier le statut de cette chambre.
                </p>
            </div>

            <div
                v-else
                class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm"
            >
                Aucune chambre sélectionnée. Scannez un QR code pour commencer.
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Tâches en cours</p>
                        <p class="text-sm text-gray-600">Ménages en attente ou en cours.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="taskFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700'"
                            @click="taskFilter = 'all'"
                        >
                            Toutes
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="taskFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700'"
                            @click="taskFilter = 'pending'"
                        >
                            En attente
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="taskFilter === 'in_progress' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700'"
                            @click="taskFilter = 'in_progress'"
                        >
                            En cours
                        </button>
                    </div>
                </div>

                <div v-if="filteredTasks.length" class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="task in filteredTasks"
                        :key="task.id"
                        class="rounded-lg border border-gray-100 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    Chambre {{ task.room?.number || '—' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ taskTypeLabel(task.type) }}
                                    <span v-if="task.room?.room_type">
                                        · {{ task.room.room_type }}
                                    </span>
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="taskStatusClasses(task.status)"
                            >
                                {{ taskStatusLabel(task.status) }}
                            </span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="priorityBadgeClasses(task.priority)"
                            >
                                {{ priorityLabel(task.priority) }}
                            </span>
                            <span v-if="task.arrival_today" class="text-amber-600">
                                Arrivée aujourd’hui
                            </span>
                            <span v-if="task.created_at">Créée : {{ formatDateTime(task.created_at) }}</span>
                            <span v-if="task.started_at">Démarrée : {{ formatDateTime(task.started_at) }}</span>
                        </div>
                        <div v-if="task.participants?.length" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="participant in task.participants"
                                :key="participant.id"
                                class="rounded-full bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200"
                            >
                                {{ participant.name }}
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500">
                    Aucune tâche en attente ou en cours.
                </p>
            </div>
        </div>

        <div
            v-if="scanning"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        >
            <QrScanner @close="handleScannerClose" @detected="handleQrDetected" />
        </div>

        <Dialog :open="incidentModalOpen" @update:open="incidentModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Signaler un problème</DialogTitle>
                    <DialogDescription>
                        Décrivez le problème détecté dans la chambre.
                    </DialogDescription>
                </DialogHeader>

                <form class="mt-4 flex flex-col gap-4" @submit.prevent="submitIncident">
                    <div>
                        <Label for="incident_severity">Gravité</Label>
                        <select
                            id="incident_severity"
                            v-model="incidentForm.severity"
                            class="mt-1 block w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-serena-bg-soft disabled:text-serena-text-muted"
                            required
                        >
                            <option value="low">Faible</option>
                            <option value="medium">Moyenne</option>
                            <option value="high">Élevée</option>
                            <option value="critical">Critique</option>
                        </select>
                    </div>

                    <div>
                        <Label for="incident_description">Description</Label>
                        <textarea
                            id="incident_description"
                            v-model="incidentForm.description"
                            class="mt-1 block w-full rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main shadow-sm transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-serena-bg-soft disabled:text-serena-text-muted"
                            rows="4"
                            required
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <SecondaryButton type="button" @click="incidentModalOpen = false">
                            Annuler
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :class="{ 'opacity-25': incidentLoading }"
                            :disabled="incidentLoading"
                        >
                            Signaler
                        </PrimaryButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script>
    import axios from 'axios';
    import Swal from 'sweetalert2';
    import AppLayout from '@/layouts/AppLayout.vue';
    import QrScanner from '@/components/Housekeeping/QrScanner.vue';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import Label from '@/components/ui/label/Label.vue';
    import PrimaryButton from '@/components/PrimaryButton.vue';
    import SecondaryButton from '@/components/SecondaryButton.vue';

    export default {
        name: 'HousekeepingIndex',
        components: {
            AppLayout,
            QrScanner,
            Dialog,
            DialogContent,
            DialogDescription,
            DialogHeader,
            DialogTitle,
            Label,
            PrimaryButton,
            SecondaryButton,
        },
        props: {
            room: {
                type: Object,
                default: null,
            },
            tasks: {
                type: Array,
                default: () => [],
            },
            canManageHousekeeping: {
                type: Boolean,
                default: false,
            },
        },
        data() {
            return {
                scanning: false,
                currentRoom: this.room,
                loading: false,
                now: Date.now(),
                timerInterval: null,
                incidentModalOpen: false,
                incidentLoading: false,
                incidentForm: {
                    severity: 'low',
                    description: '',
                },
                taskFilter: 'all',
                inspectionItems: [],
            };
        },
        computed: {
            permissionFlags() {
                return this.$page?.props?.auth?.can ?? {};
            },
            currentTask() {
                return this.currentRoom?.housekeeping_task ?? null;
            },
            lastTask() {
                return this.currentRoom?.last_housekeeping_task ?? null;
            },
            currentTaskParticipants() {
                return this.currentTask?.participants ?? [];
            },
            currentUserId() {
                return this.$page?.props?.auth?.user?.id ?? null;
            },
            isParticipant() {
                if (!this.currentTask || !this.currentUserId) {
                    return false;
                }

                return this.currentTaskParticipants.some((participant) => participant.id === this.currentUserId);
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
            canManageAnyHousekeeping() {
                return this.canMarkClean || this.canMarkDirty || this.canMarkInspected;
            },
            canReportIssue() {
                return this.permissionFlags.maintenance_tickets_create ?? false;
            },
            filteredTasks() {
                if (this.taskFilter === 'all') {
                    return this.tasks;
                }

                return this.tasks.filter((task) => task.status === this.taskFilter);
            },
            isCleaningTask() {
                return this.currentTask?.type === 'cleaning';
            },
            isInspectionTask() {
                return this.currentTask?.type === 'inspection';
            },
            canStartCleaning() {
                return this.isCleaningTask
                    && this.currentTask?.status === 'pending'
                    && ['dirty', 'redo'].includes(this.currentRoom?.hk_status);
            },
            canJoinCleaning() {
                return this.isCleaningTask
                    && this.currentTask?.status === 'in_progress'
                    && this.currentRoom?.hk_status === 'cleaning'
                    && !this.isParticipant;
            },
            canFinishCleaning() {
                return this.isCleaningTask
                    && this.currentTask?.status === 'in_progress'
                    && this.currentRoom?.hk_status === 'cleaning';
            },
            canStartInspection() {
                return this.isInspectionTask
                    && this.currentTask?.status === 'pending'
                    && this.currentRoom?.hk_status === 'awaiting_inspection'
                    && this.canMarkInspected;
            },
            canFinishInspection() {
                return this.isInspectionTask
                    && this.currentTask?.status === 'in_progress'
                    && this.canMarkInspected;
            },
            inspectionChecklist() {
                return this.currentTask?.checklist ?? null;
            },
            inspectionRemarks() {
                if (!this.currentRoom?.last_inspection?.remarks?.length) {
                    return [];
                }

                return this.currentRoom.last_inspection.remarks;
            },
            taskElapsedSeconds() {
                if (!this.currentTask?.started_at || this.currentTask?.status !== 'in_progress') {
                    return null;
                }

                const started = new Date(this.currentTask.started_at).getTime();
                if (Number.isNaN(started)) {
                    return null;
                }

                return Math.max(0, Math.floor((this.now - started) / 1000));
            },
            taskElapsedLabel() {
                if (this.taskElapsedSeconds === null) {
                    return null;
                }

                return this.formatDuration(this.taskElapsedSeconds);
            },
        },
        watch: {
            currentTask: {
                immediate: true,
                handler(newTask) {
                    this.syncInspectionForm(newTask);
                },
            },
        },
        mounted() {
            this.timerInterval = setInterval(() => {
                this.now = Date.now();
            }, 1000);
        },
        beforeUnmount() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },
        methods: {
            startScan() {
                this.scanning = true;
            },
            handleScannerClose() {
                this.scanning = false;
            },
            handleQrDetected(value) {
                this.scanning = false;
                const roomId = this.extractRoomId(value);

                if (!roomId) {
                    this.notifyError('QR code invalide.');

                    return;
                }

                this.fetchRoom(roomId);
            },
            extractRoomId(value) {
                if (!value) {
                    return null;
                }

                const trimmed = value.toString().trim();

                try {
                    const url = new URL(trimmed);
                    const segments = url.pathname.split('/').filter(Boolean);

                    return segments.pop() || null;
                } catch (error) {
                    const segments = trimmed.split('/').filter(Boolean);

                    return segments.pop() || null;
                }
            },
            async fetchRoom(roomId) {
                this.loading = true;

                try {
                    const response = await axios.get(`/hk/rooms/${roomId}`);
                    this.currentRoom = response.data.room;
                } catch (error) {
                    this.notifyError('Impossible de charger cette chambre.');
                } finally {
                    this.loading = false;
                }
            },
            async updateStatus(status) {
                const permissionMap = {
                    dirty: this.canMarkDirty,
                    redo: this.canMarkDirty,
                    cleaning: this.canMarkClean,
                    awaiting_inspection: this.canMarkClean,
                    inspected: this.canMarkInspected,
                };

                if (permissionMap[status] === false) {
                    this.notifyError('Vous n’avez pas les droits nécessaires.');

                    return;
                }

                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.patch(`/hk/rooms/${this.currentRoom.id}/status`, {
                        hk_status: status,
                    });

                    this.currentRoom = response.data.room;
                    this.notifySuccess('Statut mis à jour.');
                } catch (error) {
                    if (error?.response?.status === 403) {
                        this.notifyError('Action non autorisée.');
                    } else {
                        this.notifyError("Impossible de mettre à jour le statut.");
                    }
                } finally {
                    this.loading = false;
                }
            },
            async startCleaning() {
                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.post(`/hk/rooms/${this.currentRoom.id}/tasks/start`);
                    this.currentRoom = response.data.room;
                    this.notifySuccess('Ménage démarré.');
                } catch (error) {
                    const message = error?.response?.data?.message || 'Impossible de démarrer le ménage.';
                    this.notifyError(message);
                } finally {
                    this.loading = false;
                }
            },
            async joinCleaning() {
                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.post(`/hk/rooms/${this.currentRoom.id}/tasks/join`);
                    this.currentRoom = response.data.room;
                    this.notifySuccess('Ménage rejoint.');
                } catch (error) {
                    const message = error?.response?.data?.message || 'Impossible de rejoindre le ménage.';
                    this.notifyError(message);
                } finally {
                    this.loading = false;
                }
            },
            async finishCleaning() {
                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.post(`/hk/rooms/${this.currentRoom.id}/tasks/finish`);
                    this.currentRoom = response.data.room;
                    this.notifySuccess('Ménage terminé.');
                } catch (error) {
                    const message = error?.response?.data?.message || 'Impossible de terminer le ménage.';
                    this.notifyError(message);
                } finally {
                    this.loading = false;
                }
            },
            async startInspection() {
                if (!this.currentRoom || this.loading) {
                    return;
                }

                this.loading = true;

                try {
                    const response = await axios.post(`/hk/rooms/${this.currentRoom.id}/inspections/start`);
                    this.currentRoom = response.data.room;
                    this.notifySuccess('Inspection démarrée.');
                } catch (error) {
                    const message = error?.response?.data?.message || "Impossible de démarrer l'inspection.";
                    this.notifyError(message);
                } finally {
                    this.loading = false;
                }
            },
            async finishInspection(isRejected = false) {
                if (!this.currentRoom || this.loading) {
                    return;
                }

                if (this.inspectionChecklist?.items?.length) {
                    const invalid = this.inspectionItems.some(
                        (item) => item.is_ok === false && !item.note?.trim(),
                    );

                    if (invalid) {
                        this.notifyError('Ajoutez une note pour chaque point en NOK.');

                        return;
                    }

                    if (!isRejected && this.inspectionItems.some((item) => item.is_ok === false)) {
                        this.notifyError('Utilisez "À refaire" pour une inspection NOK.');

                        return;
                    }

                    if (isRejected && !this.inspectionItems.some((item) => item.is_ok === false)) {
                        this.notifyError('Sélectionnez au moins un point en NOK.');

                        return;
                    }
                }

                if (isRejected) {
                    const confirmation = await Swal.fire({
                        icon: 'warning',
                        title: 'Inspection NOK',
                        text: 'La chambre passera en “À refaire” et un nouveau ménage sera créé.',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmer',
                        cancelButtonText: 'Annuler',
                    });

                    if (!confirmation.isConfirmed) {
                        return;
                    }
                }

                this.loading = true;

                try {
                    const payload = {
                        items: this.inspectionItems.map((item) => ({
                            checklist_item_id: item.id,
                            is_ok: item.is_ok !== false,
                            note: item.note || null,
                        })),
                    };

                    const response = await axios.post(`/hk/rooms/${this.currentRoom.id}/inspections/finish`, payload);
                    this.currentRoom = response.data.room;
                    this.notifySuccess(isRejected ? 'Inspection rejetée.' : 'Inspection validée.');
                } catch (error) {
                    const message = error?.response?.data?.message || "Impossible de terminer l'inspection.";
                    this.notifyError(message);
                } finally {
                    this.loading = false;
                }
            },
            openIncidentModal() {
                if (!this.currentRoom) {
                    return;
                }

                this.incidentModalOpen = true;
            },
            async submitIncident() {
                if (!this.currentRoom || this.incidentLoading) {
                    return;
                }

                this.incidentLoading = true;

                try {
                    await axios.post('/maintenance-tickets', {
                        room_id: this.currentRoom.id,
                        title: `Signalement HK - Chambre ${this.currentRoom.number}`,
                        severity: this.incidentForm.severity,
                        description: this.incidentForm.description,
                    });

                    this.notifySuccess('Problème signalé.');
                    this.incidentModalOpen = false;
                    this.incidentForm = {
                        severity: 'low',
                        description: '',
                    };
                } catch (error) {
                    this.notifyError("Impossible de signaler le problème.");
                } finally {
                    this.incidentLoading = false;
                }
            },
            syncInspectionForm(task) {
                if (!task || task.type !== 'inspection') {
                    this.inspectionItems = [];

                    return;
                }

                const items = task.checklist?.items ?? [];

                this.inspectionItems = items.map((item) => ({
                    id: item.id,
                    label: item.label,
                    is_required: item.is_required,
                    is_ok: item.is_ok ?? true,
                    note: item.note ?? '',
                }));
            },
            setInspectionStatus(itemId, isOk) {
                const item = this.inspectionItems.find((entry) => entry.id === itemId);

                if (!item) {
                    return;
                }

                item.is_ok = isOk;
                if (isOk) {
                    item.note = '';
                }
            },
            statusBadgeClasses(status) {
                switch (status) {
                    case 'dirty':
                        return 'bg-gray-100 text-gray-700 border border-gray-200';
                    case 'cleaning':
                        return 'bg-blue-50 text-blue-700 border border-blue-200';
                    case 'awaiting_inspection':
                        return 'bg-teal-50 text-teal-700 border border-teal-200';
                    case 'redo':
                        return 'bg-rose-50 text-rose-700 border border-rose-200';
                    case 'inspected':
                        return 'bg-green-50 text-green-700 border border-green-200';
                    default:
                        return 'bg-gray-50 text-gray-600 border border-gray-200';
                }
            },
            taskStatusLabel(status) {
                switch (status) {
                    case 'pending':
                        return 'En attente';
                    case 'in_progress':
                        return 'En cours';
                    case 'done':
                        return 'Terminée';
                    default:
                        return status;
                }
            },
            taskStatusClasses(status) {
                switch (status) {
                    case 'pending':
                        return 'bg-amber-100 text-amber-700';
                    case 'in_progress':
                        return 'bg-indigo-100 text-indigo-700';
                    case 'done':
                        return 'bg-emerald-100 text-emerald-700';
                    default:
                        return 'bg-gray-100 text-gray-600';
                }
            },
            priorityLabel(priority) {
                switch (priority) {
                    case 'urgent':
                        return 'Priorité urgente';
                    case 'high':
                        return 'Priorité haute';
                    case 'normal':
                        return 'Priorité normale';
                    case 'low':
                        return 'Priorité basse';
                    default:
                        return priority || '—';
                }
            },
            priorityBadgeClasses(priority) {
                switch (priority) {
                    case 'urgent':
                        return 'bg-rose-100 text-rose-700';
                    case 'high':
                        return 'bg-amber-100 text-amber-700';
                    case 'normal':
                        return 'bg-indigo-50 text-indigo-700';
                    case 'low':
                        return 'bg-gray-100 text-gray-600';
                    default:
                        return 'bg-gray-100 text-gray-600';
                }
            },
            taskTypeLabel(type) {
                switch (type) {
                    case 'cleaning':
                        return 'Ménage';
                    case 'inspection':
                        return 'Inspection';
                    default:
                        return type;
                }
            },
            formatDuration(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;

                if (minutes < 60) {
                    return `${minutes}m ${remainingSeconds.toString().padStart(2, '0')}s`;
                }

                const hours = Math.floor(minutes / 60);
                const remainingMinutes = minutes % 60;

                return `${hours}h ${remainingMinutes.toString().padStart(2, '0')}m`;
            },
            formatDateTime(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat('fr-FR', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(date);
            },
            notifySuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: message,
                    timer: 1500,
                    showConfirmButton: false,
                });
            },
            notifyError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            },
        },
    };
</script>
