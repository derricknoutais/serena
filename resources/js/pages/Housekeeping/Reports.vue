<template>
    <AppLayout title="Housekeeping Reports">
        <div class="space-y-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-serena-primary">Housekeeping</p>
                    <h1 class="text-2xl font-bold text-serena-text-main">Rapports Housekeeping</h1>
                    <p class="text-sm text-serena-text-muted">
                        Suivi des ménages, inspections et performances par équipe.
                    </p>
                </div>
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="filters.preset === 'today' ? 'bg-serena-primary text-white' : 'bg-serena-bg-soft text-serena-text-muted'"
                            @click="applyPreset('today')"
                        >
                            Aujourd’hui
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="filters.preset === 'yesterday' ? 'bg-serena-primary text-white' : 'bg-serena-bg-soft text-serena-text-muted'"
                            @click="applyPreset('yesterday')"
                        >
                            Hier
                        </button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="text-xs font-semibold text-serena-text-main">
                            Du
                            <input
                                v-model="from"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-serena-border px-3 py-2 text-xs focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                        </label>
                        <label class="text-xs font-semibold text-serena-text-main">
                            Au
                            <input
                                v-model="to"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-serena-border px-3 py-2 text-xs focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                        </label>
                        <PrimaryButton type="button" class="px-4 py-2" @click="applyCustom">
                            Appliquer
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs text-serena-text-muted">Chambres nettoyées</p>
                    <p class="text-2xl font-bold text-serena-text-main">{{ summary.rooms_cleaned ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs text-serena-text-muted">Chambres inspectées</p>
                    <p class="text-2xl font-bold text-serena-text-main">{{ summary.rooms_inspected ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs text-serena-text-muted">Chambres à refaire</p>
                    <p class="text-2xl font-bold text-serena-text-main">{{ summary.rooms_redone ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs text-serena-text-muted">Durée moyenne ménage</p>
                    <p class="text-2xl font-bold text-serena-text-main">{{ formatDuration(summary.avg_cleaning_seconds) }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-serena-text-main">Par chambre</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">Chambre</th>
                                <th class="py-2">Statut</th>
                                <th class="py-2">Dernier ménage</th>
                                <th class="py-2">Dernière inspection</th>
                                <th class="py-2 text-right">Redos (30j)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="room in rooms" :key="room.id" class="border-t text-serena-text-main">
                                <td class="py-2">
                                    {{ room.number }}
                                    <span v-if="room.floor" class="text-xs text-serena-text-muted">· Étage {{ room.floor }}</span>
                                </td>
                                <td class="py-2 text-xs text-serena-text-muted">
                                    {{ hkStatusLabel(room.hk_status) }}
                                </td>
                                <td class="py-2 text-xs text-serena-text-muted">
                                    {{ room.last_cleaning_at || '—' }}
                                </td>
                                <td class="py-2 text-xs text-serena-text-muted">
                                    <span v-if="room.last_inspection_at">
                                        {{ room.last_inspection_at }}
                                        <span class="ml-1 text-[10px] font-semibold">
                                            ({{ inspectionOutcomeLabel(room.last_inspection_outcome) }})
                                        </span>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="py-2 text-right text-xs font-semibold text-serena-text-main">
                                    {{ room.redos_last_30_days ?? 0 }}
                                </td>
                            </tr>
                            <tr v-if="!rooms.length">
                                <td colspan="5" class="py-3 text-sm text-serena-text-muted">
                                    Aucune donnée chambre pour cette période.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-serena-text-main">Par personnel</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">Agent</th>
                                <th class="py-2 text-right">Tâches</th>
                                <th class="py-2 text-right">Temps ménage</th>
                                <th class="py-2 text-right">Inspections</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in staff" :key="member.id" class="border-t text-serena-text-main">
                                <td class="py-2">{{ member.name }}</td>
                                <td class="py-2 text-right">{{ member.tasks_participated }}</td>
                                <td class="py-2 text-right">
                                    {{ formatDuration(member.cleaning_seconds) }}
                                </td>
                                <td class="py-2 text-right">{{ member.inspections_performed }}</td>
                            </tr>
                            <tr v-if="!staff.length">
                                <td colspan="4" class="py-3 text-sm text-serena-text-muted">
                                    Aucun participant enregistré pour cette période.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-serena-text-main">Historique des tâches</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">Chambre</th>
                                <th class="py-2">Type</th>
                                <th class="py-2">Statut</th>
                                <th class="py-2">Priorité</th>
                                <th class="py-2">Créée</th>
                                <th class="py-2">Démarrée</th>
                                <th class="py-2">Terminée</th>
                                <th class="py-2">Durée</th>
                                <th class="py-2">Résultat</th>
                                <th class="py-2">Participants</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="task in tasks" :key="task.id" class="border-t text-serena-text-main">
                                <td class="py-2">{{ task.room?.number || '—' }}</td>
                                <td class="py-2">{{ taskTypeLabel(task.type) }}</td>
                                <td class="py-2">{{ taskStatusLabel(task.status) }}</td>
                                <td class="py-2">{{ priorityLabel(task.priority) }}</td>
                                <td class="py-2 text-serena-text-muted">{{ task.created_at || '—' }}</td>
                                <td class="py-2 text-serena-text-muted">{{ task.started_at || '—' }}</td>
                                <td class="py-2 text-serena-text-muted">{{ task.ended_at || '—' }}</td>
                                <td class="py-2">{{ formatDuration(task.duration_seconds) }}</td>
                                <td class="py-2">{{ inspectionOutcomeLabel(task.outcome) }}</td>
                                <td class="py-2">
                                    <span v-if="task.participants?.length">
                                        {{ task.participants.map((p) => p.name).join(', ') }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                            <tr v-if="!tasks.length">
                                <td colspan="10" class="py-3 text-sm text-serena-text-muted">
                                    Aucune tâche trouvée pour cette période.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="text-lg font-semibold text-serena-text-main">Transitions HK</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">Date</th>
                                <th class="py-2">Chambre</th>
                                <th class="py-2">De</th>
                                <th class="py-2">À</th>
                                <th class="py-2">Remarques</th>
                                <th class="py-2">Utilisateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in statusHistory" :key="entry.id" class="border-t text-serena-text-main">
                                <td class="py-2 text-serena-text-muted">{{ entry.created_at || '—' }}</td>
                                <td class="py-2">{{ entry.room_number || '—' }}</td>
                                <td class="py-2">{{ hkStatusLabel(entry.from) }}</td>
                                <td class="py-2">{{ hkStatusLabel(entry.to) }}</td>
                                <td class="py-2 text-serena-text-muted">{{ entry.remarks || '—' }}</td>
                                <td class="py-2">{{ entry.user || '—' }}</td>
                            </tr>
                            <tr v-if="!statusHistory.length">
                                <td colspan="6" class="py-3 text-sm text-serena-text-muted">
                                    Aucune transition pour cette période.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Filters = {
    preset: string;
    from: string;
    to: string;
    timezone?: string;
};

type Summary = {
    rooms_cleaned: number;
    rooms_inspected: number;
    rooms_redone: number;
    avg_cleaning_seconds: number;
};

type RoomRow = {
    id: string;
    number: string;
    floor?: string | null;
    hk_status?: string | null;
    last_cleaning_at?: string | null;
    last_inspection_at?: string | null;
    last_inspection_outcome?: string | null;
    redos_last_30_days?: number;
};

type StaffRow = {
    id: number;
    name: string;
    tasks_participated: number;
    cleaning_seconds: number;
    inspections_performed: number;
};

type TaskRow = {
    id: number;
    type: string;
    status: string;
    priority: string;
    created_at?: string | null;
    started_at?: string | null;
    ended_at?: string | null;
    duration_seconds?: number | null;
    outcome?: string | null;
    room?: { id: string; number: string; floor?: string | null } | null;
    participants?: Array<{ id: number; name: string }>;
};

type StatusHistoryRow = {
    id: number;
    room_number?: string | null;
    from?: string | null;
    to?: string | null;
    remarks?: string | null;
    user?: string | null;
    created_at?: string | null;
};

const props = defineProps<{
    filters: Filters;
    summary: Summary;
    rooms: RoomRow[];
    staff: StaffRow[];
    tasks: TaskRow[];
    statusHistory: StatusHistoryRow[];
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const filters = computed(() => props.filters);
const summary = computed(() => props.summary);
const rooms = computed(() => props.rooms ?? []);
const staff = computed(() => props.staff ?? []);
const tasks = computed(() => props.tasks ?? []);
const statusHistory = computed(() => props.statusHistory ?? []);

const applyPreset = (preset: string) => {
    router.get(
        '/housekeeping/reports',
        { preset },
        { preserveState: true, replace: true },
    );
};

const applyCustom = () => {
    if (!from.value || !to.value) {
        return;
    }

    router.get(
        '/housekeeping/reports',
        { preset: 'custom', from: from.value, to: to.value },
        { preserveState: true, replace: true },
    );
};

const formatDuration = (seconds?: number | null) => {
    if (!seconds || seconds <= 0) {
        return '—';
    }

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    if (minutes < 60) {
        return `${minutes}m ${remainingSeconds.toString().padStart(2, '0')}s`;
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return `${hours}h ${remainingMinutes.toString().padStart(2, '0')}m`;
};

const hkStatusLabel = (status?: string | null) => {
        switch (status) {
            case 'dirty':
                return 'Sale';
            case 'cleaning':
                return 'En cours';
            case 'awaiting_inspection':
                return 'En attente d’inspection';
            case 'redo':
                return 'À refaire';
            case 'inspected':
                return 'Inspectée';
            case 'in_use':
                return 'En usage';
            default:
                return status || '—';
        }
};

const taskTypeLabel = (type: string) => {
    switch (type) {
        case 'cleaning':
            return 'Ménage';
        case 'inspection':
            return 'Inspection';
        default:
            return type || '—';
    }
};

const taskStatusLabel = (status: string) => {
    switch (status) {
        case 'pending':
            return 'En attente';
        case 'in_progress':
            return 'En cours';
        case 'done':
            return 'Terminée';
        default:
            return status || '—';
    }
};

const priorityLabel = (priority?: string | null) => {
    switch (priority) {
        case 'urgent':
            return 'Urgente';
        case 'high':
            return 'Haute';
        case 'normal':
            return 'Normale';
        case 'low':
            return 'Basse';
        default:
            return priority || '—';
    }
};

const inspectionOutcomeLabel = (outcome?: string | null) => {
    switch (outcome) {
        case 'passed':
            return 'Validée';
        case 'failed':
            return 'À refaire';
        default:
            return outcome || '—';
    }
};
</script>
