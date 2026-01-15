<template>
    <AppLayout title="Transferts">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Transferts</h1>
                    <p class="text-sm text-serena-text-muted">Liste des transferts entre emplacements.</p>
                </div>
                <Link
                    v-if="permissions.can_create_transfer"
                    href="/stock/transfers/create"
                    class="rounded-full bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                >
                    Nouveau transfert
                </Link>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div v-if="!transfers.length" class="text-sm text-serena-text-muted">
                    Aucun transfert enregistré.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">De → À</th>
                                <th class="px-4 py-3 text-right">Montant estimé</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="transfer in transfers" :key="transfer.id">
                                <td class="px-4 py-3">
                                    {{ transfer.from_location?.name ?? '—' }} → {{ transfer.to_location?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatAmount(transfer.lines.reduce((sum, line) => sum + line.total_cost, 0), transfer.currency) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold text-serena-text-main" :class="statusClasses(transfer.status)">
                                        {{ statusLabel(transfer.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="`/stock/transfers/${transfer.id}`"
                                        class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                    >
                                        Détails
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
    name: 'StockTransferIndex',
    components: {
        AppLayout,
        Link,
    },
    props: {
        transfers: {
            type: Array,
            default: () => [],
        },
        permissions: {
            type: Object,
            default: () => ({
                can_create_transfer: false,
                can_complete_transfer: false,
            }),
        },
    },
    methods: {
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        statusLabel(status) {
            const map = {
                draft: 'Brouillon',
                completed: 'Finalisé',
                void: 'Annulé',
            };

            return map[status] ?? status;
        },
        statusClasses(status) {
            const styles = {
                draft: 'bg-gray-100 text-gray-600 border border-gray-200',
                completed: 'bg-amber-50 text-amber-700 border border-amber-200',
                void: 'bg-rose-50 text-rose-700 border border-rose-200',
            };

            return styles[status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
        },
    },
};
</script>
