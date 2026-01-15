<template>
    <AppLayout title="Mouvement de stock">
        <div class="space-y-6">
            <div class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ movement.movement_type }}
                        </p>
                        <h1 class="text-2xl font-semibold text-serena-text-main">
                            Mouvement #{{ movement.id }}
                        </h1>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-serena-text-main">
                            {{ formatAmount(movement.lines?.reduce((sum, line) => sum + (line.total_cost ?? 0), 0), 'XAF') }}
                        </p>
                        <p class="text-xs text-serena-text-muted">
                            {{ formatDateTime(movement.occurred_at) }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3 text-sm text-serena-text-main">
                    <div>
                        <p class="text-xs font-semibold text-serena-text-muted">De</p>
                        <p>{{ movement.from_location?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-serena-text-muted">Vers</p>
                        <p>{{ movement.to_location?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-serena-text-muted">Référence</p>
                        <p class="text-serena-text-main">
                            {{ movement.reference?.label ?? '—' }}
                            <Link
                                v-if="movement.reference?.url"
                                :href="movement.reference.url"
                                class="ml-2 text-[11px] font-semibold text-serena-primary hover:underline"
                            >
                                Voir
                            </Link>
                        </p>
                    </div>
                </div>
                <div v-if="movement.notes" class="mt-3 rounded-xl border border-dashed border-serena-border bg-serena-bg-soft/80 px-3 py-2 text-sm text-serena-text-main">
                    {{ movement.notes }}
                </div>
            </div>

            <div class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-serena-text-main">Lignes</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Article</th>
                                <th class="px-4 py-3 text-right">Quantité</th>
                                <th class="px-4 py-3 text-right">Prix unitaire</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="line in movement.lines" :key="line.id">
                                <td class="px-4 py-3">
                                    {{ line.stock_item?.name ?? 'Article supprimé' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatQuantity(line.quantity) }} {{ line.stock_item?.unit ?? '' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatAmount(line.unit_cost, line.currency) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatAmount(line.total_cost, line.currency) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
    name: 'StockMovementDetail',
    components: {
        AppLayout,
        Link,
    },
    props: {
        movement: {
            type: Object,
            default: () => ({
                lines: [],
            }),
        },
    },
    methods: {
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        formatQuantity(value) {
            const quantity = Number(value || 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
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
    },
};
</script>
