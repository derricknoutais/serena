<template>
    <AppLayout title="Détail de Caisse">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Session {{ sessionLabel }}</h1>
                <p class="text-sm text-gray-500">Détails de la session de caisse.</p>
            </div>
            <Link
                href="/cash"
                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
                Retour
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Statut</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass">
                            {{ statusLabel }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Hôtel</p>
                        <p class="text-sm text-gray-800">{{ session.hotel?.name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Ouvert par</p>
                        <p class="text-sm text-gray-800">{{ session.opened_by?.name || '—' }}</p>
                        <p class="text-xs text-gray-500">{{ formatDate(session.started_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Fermé par</p>
                        <p class="text-sm text-gray-800">{{ session.closed_by?.name || '—' }}</p>
                        <p class="text-xs text-gray-500">{{ formatDate(session.ended_at) }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                        <p class="text-xs font-semibold text-gray-500">Fond initial</p>
                        <p class="text-base font-semibold text-gray-900">
                            {{ formatCurrency(session.starting_amount) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                        <p class="text-xs font-semibold text-gray-500">Total encaissé</p>
                        <p class="text-base font-semibold text-gray-900">
                            {{ formatCurrency(session.total_received) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                        <p class="text-xs font-semibold text-gray-500">Théorique fin</p>
                        <p class="text-base font-semibold text-gray-900">
                            {{ formatCurrency(session.theoretical_balance) }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                        <p class="text-xs font-semibold text-gray-500">Réel fin</p>
                        <p class="text-base font-semibold text-gray-900">
                            {{ session.closing_amount !== null ? formatCurrency(session.closing_amount) : '—' }}
                        </p>
                        <p class="text-xs" :class="differenceClass">
                            Écart : {{ session.difference_amount !== null ? formatCurrency(session.difference_amount) : '—' }}
                        </p>
                    </div>
                </div>

                <div v-if="session.notes" class="mt-4 rounded-lg border border-gray-100 bg-white p-3 text-xs text-gray-600">
                    {{ session.notes }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-800">Encaissements</h3>
                <p class="text-xs text-gray-500">Total paiements: {{ formatCurrency(paymentsTotal) }}</p>

                <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50/60 p-3">
                    <p class="text-xs font-semibold text-gray-600">Par mode de paiement</p>
                    <div v-if="paymentBreakdown.length" class="mt-2 space-y-1 text-xs text-gray-700">
                        <div v-for="item in paymentBreakdown" :key="item.method" class="flex items-center justify-between">
                            <span>{{ item.method }}</span>
                            <span class="font-semibold">{{ formatCurrency(item.amount) }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-gray-400">Aucun paiement.</p>
                </div>

                <div class="mt-3 space-y-3">
                    <div v-for="payment in payments" :key="payment.id" class="rounded-lg border border-gray-100 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">
                                {{ formatCurrency(payment.amount) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ formatDate(payment.paid_at) }}
                            </p>
                        </div>
                        <p class="text-xs text-gray-600">
                            {{ payment.method || '—' }} · {{ payment.created_by?.name || '—' }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ payment.reservation_code || payment.folio_code || '—' }}
                            <span v-if="payment.guest_name">· {{ payment.guest_name }}</span>
                        </p>
                        <p v-if="payment.notes" class="text-[11px] text-gray-400">
                            {{ payment.notes }}
                        </p>
                    </div>
                    <p v-if="payments.length === 0" class="text-xs text-gray-400">
                        Aucun paiement enregistré.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800">Mouvements de caisse</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Type</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Description</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="transaction in transactions" :key="transaction.id">
                            <td class="px-3 py-2 text-xs text-gray-500">
                                {{ formatDate(transaction.created_at) }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700">
                                {{ transaction.type === 'withdrawal' ? 'Sortie' : 'Entrée' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                {{ transaction.description }}
                            </td>
                            <td class="px-3 py-2 text-right text-xs font-semibold" :class="transaction.amount < 0 ? 'text-red-600' : 'text-green-600'">
                                {{ formatCurrency(transaction.amount) }}
                            </td>
                        </tr>
                        <tr v-if="transactions.length === 0">
                            <td colspan="4" class="px-3 py-4 text-center text-xs text-gray-400">
                                Aucun mouvement enregistré.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
    name: 'CashSessionShow',
    components: {
        AppLayout,
        Link,
    },
    props: {
        session: {
            type: Object,
            required: true,
        },
        transactions: {
            type: Array,
            default: () => [],
        },
        payments: {
            type: Array,
            default: () => [],
        },
        paymentBreakdown: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        sessionLabel() {
            return `${this.session.type} #${this.session.id}`;
        },
        statusLabel() {
            const map = {
                open: 'Ouverte',
                closed_pending_validation: 'À valider',
                validated: 'Validée',
                closed: 'Fermée',
            };

            return map[this.session.status] || this.session.status;
        },
        statusClass() {
            const map = {
                open: 'bg-green-100 text-green-700',
                closed_pending_validation: 'bg-amber-100 text-amber-700',
                validated: 'bg-blue-100 text-blue-700',
                closed: 'bg-gray-100 text-gray-600',
            };

            return map[this.session.status] || 'bg-gray-100 text-gray-600';
        },
        differenceClass() {
            if (this.session.difference_amount === null || this.session.difference_amount === undefined) {
                return 'text-gray-400';
            }

            if (Math.abs(this.session.difference_amount) < 0.01) {
                return 'text-gray-400';
            }

            return this.session.difference_amount < 0 ? 'text-red-600' : 'text-green-600';
        },
        paymentsTotal() {
            return this.payments.reduce((total, payment) => total + Number(payment.amount || 0), 0);
        },
    },
    methods: {
        formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: this.session.currency || 'XAF',
            }).format(Number(amount || 0));
        },
        formatDate(value) {
            if (!value) {
                return '—';
            }

            return new Date(value).toLocaleString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>
