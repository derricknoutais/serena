<template>
    <AppLayout title="Maintenance">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-serena-text-main">
                    Maintenance
                </h1>
                <p class="text-sm text-serena-text-muted">
                    Suivi des tickets de maintenance des chambres.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in statusOptions"
                    :key="option"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-sm font-semibold transition"
                    :class="statusButtonClasses(option)"
                    @click="changeStatusFilter(option)"
                >
                    {{ statusFilterLabel(option) }}
                </button>
            </div>

            <div v-if="!hasTickets" class="rounded-2xl border border-dashed border-serena-border bg-white/70 p-8 text-center text-sm text-serena-text-muted">
                Aucun ticket de maintenance pour le moment.
            </div>

            <div v-else class="space-y-4">
                <div class="space-y-4 sm:hidden">
                    <article
                        v-for="ticket in tickets.data"
                        :key="ticket.id"
                        class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-serena-text-main">
                                    {{ ticket.title }}
                                </h3>
                                <p class="text-xs text-serena-text-muted">
                                    {{ ticket.room ? `Chambre ${ticket.room.number}` : 'Chambre supprimée' }}
                                    <span v-if="ticket.room?.room_type_name" class="text-serena-text-muted/80">
                                        · {{ ticket.room.room_type_name }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="statusClasses(ticket.status)">
                                    {{ statusLabel(ticket.status) }}
                                </span>
                                <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="severityClasses(ticket.severity)">
                                    {{ severityLabel(ticket.severity) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1 text-xs text-serena-text-muted">
                            <p>
                                Ouvert le
                                <span class="font-semibold text-serena-text-main">
                                    {{ formatDateTime(ticket.opened_at) }}
                                </span>
                            </p>
                            <p>
                                Assigné à :
                                <span class="font-semibold text-serena-text-main">
                                    {{ ticket.assigned_to?.name ?? 'Non assigné' }}
                                </span>
                            </p>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                class="text-sm font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                @click="openTicket(ticket)"
                            >
                                Voir
                            </button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-2xl border border-serena-border bg-white shadow-sm sm:block">
                    <table class="w-full min-w-[720px] divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Ticket</th>
                                <th class="px-4 py-3">Chambre</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Sévérité</th>
                                <th class="px-4 py-3">Ouvert le</th>
                                <th class="px-4 py-3">Assigné à</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                            <tr v-for="ticket in tickets.data" :key="ticket.id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">
                                        {{ ticket.title }}
                                    </p>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ ticket.description || 'Pas de description.' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <p class="font-semibold">
                                        {{ ticket.room ? `#${ticket.room.number}` : '—' }}
                                    </p>
                                    <p class="text-serena-text-muted">
                                        {{ ticket.room?.room_type_name || 'Type inconnu' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="statusClasses(ticket.status)">
                                        {{ statusLabel(ticket.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-0.5 text-[11px] font-semibold" :class="severityClasses(ticket.severity)">
                                        {{ severityLabel(ticket.severity) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    {{ formatDateTime(ticket.opened_at) }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    {{ ticket.assigned_to?.name ?? 'Non assigné' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                        @click="openTicket(ticket)"
                                    >
                                        Voir
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="tickets.links?.length" class="flex flex-wrap gap-2 text-sm">
                    <button
                        v-for="link in tickets.links"
                        :key="link.label"
                        type="button"
                        class="rounded-full px-3 py-1 transition"
                        :class="linkClasses(link)"
                        :disabled="!link.url"
                        @click.prevent="visitLink(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="showTicketModal && selectedTicket"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-serena-text-main">
                            {{ selectedTicket.title }}
                        </h3>
                        <p class="text-xs text-serena-text-muted">
                            {{ selectedTicket.room ? `Chambre ${selectedTicket.room.number}` : 'Chambre inconnue' }}
                            <span v-if="selectedTicket.room?.room_type_name">
                                · {{ selectedTicket.room.room_type_name }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeTicketModal"
                    >
                        Fermer
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-serena-text-muted">
                            Description
                        </label>
                        <textarea
                            v-model="modalForm.description"
                            rows="3"
                            class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        ></textarea>
                        <p v-if="modalErrors.description" class="mt-1 text-xs text-serena-danger">
                            {{ modalErrors.description }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Statut
                            </label>
                            <select
                                v-model="modalForm.status"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                :disabled="!permissions.canUpdateStatus"
                            >
                                <option value="open">Ouvert</option>
                                <option value="in_progress">En cours</option>
                                <option value="resolved">Résolu</option>
                                <option value="closed">Clôturé</option>
                            </select>
                            <p v-if="modalErrors.status" class="mt-1 text-xs text-serena-danger">
                                {{ modalErrors.status }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-serena-text-muted">
                                Assigné à
                            </label>
                            <select
                                v-model="modalForm.assigned_to_user_id"
                                class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft disabled:bg-serena-bg-soft"
                                :disabled="!permissions.canAssign"
                            >
                                <option :value="null">Non assigné</option>
                                <option
                                    v-for="user in assignableUsers"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }}
                                </option>
                            </select>
                            <p v-if="modalErrors.assigned_to_user_id" class="mt-1 text-xs text-serena-danger">
                                {{ modalErrors.assigned_to_user_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="text-sm text-serena-text-muted transition hover:text-serena-text-main"
                        @click="closeTicketModal"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:opacity-60"
                        :disabled="updating || !permissions.canUpdateStatus"
                        @click="submitTicketUpdate"
                    >
                        {{ updating ? 'Mise à jour...' : 'Mettre à jour' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
    name: 'MaintenanceIndex',
    components: {
        AppLayout,
    },
    props: {
        tickets: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
        statusOptions: {
            type: Array,
            required: true,
        },
        assignableUsers: {
            type: Array,
            default: () => [],
        },
        permissions: {
            type: Object,
            default: () => ({
                canUpdateStatus: false,
                canAssign: false,
            }),
        },
    },
    data() {
        return {
            selectedStatus: this.filters?.status ?? 'open',
            selectedTicket: null,
            showTicketModal: false,
            modalForm: {
                status: 'open',
                assigned_to_user_id: null,
                description: '',
            },
            modalErrors: {},
            updating: false,
        };
    },
    computed: {
        hasTickets() {
            return Array.isArray(this.tickets?.data) && this.tickets.data.length > 0;
        },
    },
    watch: {
        filters: {
            deep: true,
            handler(newFilters) {
                this.selectedStatus = newFilters?.status ?? 'open';
            },
        },
    },
    methods: {
        statusFilterLabel(option) {
            switch (option) {
                case 'in_progress':
                    return 'En cours';
                case 'resolved':
                    return 'Résolus';
                case 'closed':
                    return 'Clôturés';
                case 'all':
                    return 'Tous';
                case 'open':
                default:
                    return 'Ouverts';
            }
        },
        statusLabel(status) {
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
        severityLabel(severity) {
            switch (severity) {
                case 'high':
                    return 'Critique';
                case 'medium':
                    return 'Moyen';
                case 'low':
                default:
                    return 'Mineur';
            }
        },
        statusButtonClasses(option) {
            const isActive = this.selectedStatus === option;

            return isActive
                ? 'bg-serena-primary text-white'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary';
        },
        statusClasses(status) {
            switch (status) {
                case 'in_progress':
                    return 'bg-blue-100 text-blue-700 border border-blue-200';
                case 'resolved':
                    return 'bg-green-100 text-green-700 border border-green-200';
                case 'closed':
                    return 'bg-gray-200 text-gray-700 border border-gray-300';
                case 'open':
                default:
                    return 'bg-amber-100 text-amber-800 border border-amber-200';
            }
        },
        severityClasses(severity) {
            switch (severity) {
                case 'high':
                    return 'bg-red-100 text-red-700 border border-red-200';
                case 'medium':
                    return 'bg-orange-100 text-orange-700 border border-orange-200';
                case 'low':
                default:
                    return 'bg-gray-100 text-gray-700 border border-gray-200';
            }
        },
        changeStatusFilter(option) {
            if (this.selectedStatus === option && this.$page.url.includes(`status=${option}`)) {
                return;
            }

            this.selectedStatus = option;

            const params = option ? { status: option } : {};

            this.$inertia.get(
                '/maintenance',
                params,
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        },
        formatDateTime(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        },
        openTicket(ticket) {
            this.selectedTicket = ticket;
            this.modalForm = {
                status: ticket.status,
                assigned_to_user_id: ticket.assigned_to?.id ?? null,
                description: ticket.description ?? '',
            };
            this.modalErrors = {};
            this.showTicketModal = true;
        },
        closeTicketModal() {
            this.showTicketModal = false;
            this.selectedTicket = null;
            this.modalErrors = {};
        },
        async submitTicketUpdate() {
            if (!this.selectedTicket) {
                return;
            }

            this.updating = true;
            this.modalErrors = {};

            const payload = {
                description: this.modalForm.description || null,
            };

            if (this.permissions.canUpdateStatus) {
                payload.status = this.modalForm.status;
            }

            if (this.permissions.canAssign) {
                payload.assigned_to_user_id = this.modalForm.assigned_to_user_id;
            }

            try {
                await axios.patch(`/maintenance-tickets/${this.selectedTicket.id}`, payload);

                this.closeTicketModal();

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket mis à jour',
                    timer: 1600,
                    showConfirmButton: false,
                });

                this.$inertia.reload({
                    preserveScroll: true,
                    only: ['tickets'],
                });
            } catch (error) {
                if (error.response?.status === 422) {
                    this.modalErrors = Object.fromEntries(
                        Object.entries(error.response.data.errors || {}).map(([key, value]) => [
                            key,
                            Array.isArray(value) ? value[0] : value,
                        ]),
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.response?.data?.message ?? 'Impossible de mettre à jour le ticket.',
                    });
                }
            } finally {
                this.updating = false;
            }
        },
        linkClasses(link) {
            return link.active
                ? 'bg-serena-primary text-white font-semibold'
                : 'bg-white text-serena-text-muted border border-serena-border hover:text-serena-primary transition disabled:opacity-60';
        },
        visitLink(url) {
            if (!url) {
                return;
            }

            this.$inertia.visit(url, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
    },
};
</script>
