<template>
    <AppLayout title="Gestion des Caisses">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Supervision des Caisses</h1>
                <p class="text-sm text-gray-500">Historique et validation des sessions de caisse.</p>
            </div>
            <!-- Filters or Actions could go here -->
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type / Hôtel</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Ouvert par</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Période</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Fond Initial</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Total Encaissé</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Théorique Fin</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Réel Fin</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Ecart</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">Statut</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="session in sessions.data" :key="session.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                <span class="capitalize">{{ session.type }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ session.opened_by?.name || '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="flex flex-col">
                                    <span>{{ formatDate(session.started_at) }}</span>
                                    <span v-if="session.ended_at" class="text-xs text-gray-400">à {{ formatDate(session.ended_at) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-800">
                                {{ formatCurrency(session.starting_amount, session.currency) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600">
                                <span v-if="session.total_received !== undefined">{{ formatCurrency(session.total_received, session.currency) }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600">
                                <span v-if="session.expected_closing_amount">{{ formatCurrency(session.expected_closing_amount, session.currency) }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-800">
                                <span v-if="session.closing_amount">{{ formatCurrency(session.closing_amount, session.currency) }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm">
                                <span v-if="session.difference_amount" :class="getDifferenceClass(session.difference_amount)">
                                    {{ session.difference_amount > 0 ? '+' : ''}}{{ formatCurrency(session.difference_amount, session.currency) }}
                                </span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <SessionStatusBadge :status="session.status" />
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-gray-600">
                                <button
                                    v-if="session.status === 'closed_pending_validation'"
                                    @click="confirmValidation(session)"
                                    class="text-indigo-600 hover:text-indigo-900 font-medium text-xs border border-indigo-200 bg-indigo-50 px-2 py-1 rounded"
                                >
                                    Valider
                                </button>
                            </td>
                        </tr>
                        <tr v-if="sessions.data.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500 text-sm">
                                Aucune session trouvée.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="sessions.links.length > 3" class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                 <!-- Pagination placeholder or implementation -->
                 Pagination à ajouter selon vos besoins.
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { h } from 'vue';
import Swal from 'sweetalert2';

// Simple Badge Component inline definition for Options API usage if needed, or just standard component
const SessionStatusBadge = {
    props: ['status'],
    render() {
        const map = {
            open: { class: 'bg-green-100 text-green-800', label: 'Ouverte' },
            closed_pending_validation: { class: 'bg-orange-100 text-orange-800', label: 'À Valider' },
            validated: { class: 'bg-blue-100 text-blue-800', label: 'Validée' },
        };
        const conf = map[this.status] || { class: 'bg-gray-100 text-gray-800', label: this.status };
        return h('span', { class: `inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${conf.class}` }, conf.label);
    }
};

export default {
    name: 'CashSessionsIndex',
    components: {
        AppLayout,
        SessionStatusBadge,
    },
    props: {
        sessions: {
            type: Object,
            required: true,
        },
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute:'2-digit'
            });
        },
        formatCurrency(amount, currency = 'XAF') {
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: currency }).format(amount);
        },
        getDifferenceClass(amount) {
            if (Math.abs(amount) < 0.01) return 'text-gray-400';
            return amount < 0 ? 'text-red-600 font-bold' : 'text-green-600 font-bold';
        },
        confirmValidation(session) {
            Swal.fire({
                title: 'Valider cette session ?',
                text: `Ecart constaté: ${this.formatCurrency(session.difference_amount, session.currency)}. Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, valider',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    router.post('/cash/' + session.id + '/validate', {}, {
                        onSuccess: () => Swal.fire('Validé', 'La session a été validée.', 'success')
                    });
                }
            });
        },
    },
};
</script>
