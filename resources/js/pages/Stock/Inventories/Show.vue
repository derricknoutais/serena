<template>
    <AppLayout title="Détail de l'inventaire">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Inventaire</h1>
                    <p class="text-sm text-serena-text-muted">{{ inventory.storage_location?.name ?? '—' }}</p>
                </div>
                <Link
                    href="/stock/inventories"
                    class="text-xs font-semibold uppercase tracking-wide text-serena-primary hover:underline"
                >
                    ← Retour aux inventaires
                </Link>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Emplacement</p>
                        <p class="text-sm text-serena-text-main">{{ inventory.storage_location?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Statut</p>
                        <span class="rounded-full px-3 py-1 text-[11px] font-semibold text-serena-text-main" :class="statusClasses(inventory.status)">
                            {{ statusLabel(inventory.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Créé le</p>
                        <p class="text-sm text-serena-text-main">{{ inventory.created_at }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-serena-text-main">Lignes</h2>
                    <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">{{ inventory.lines.length }} ligne(s)</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Article</th>
                                <th class="px-4 py-3">Quantité comptée</th>
                                <th class="px-4 py-3">Quantité système</th>
                                <th class="px-4 py-3">Variance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="line in inventory.lines" :key="line.id">
                                <td class="px-4 py-3">{{ line.stock_item?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatQuantity(line.counted_quantity) }}</td>
                                <td class="px-4 py-3">{{ formatQuantity(line.system_quantity) }}</td>
                                <td class="px-4 py-3">{{ formatQuantity(line.variance_quantity) }}</td>
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
    name: 'StockInventoryShow',
    components: {
        AppLayout,
        Link,
    },
    props: {
        inventory: {
            type: Object,
            required: true,
        },
    },
    methods: {
        formatQuantity(value) {
            const quantity = Number(value || 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        statusLabel(status) {
            const map = {
                draft: 'Brouillon',
                posted: 'Posté',
                void: 'Annulé',
            };

            return map[status] ?? status;
        },
        statusClasses(status) {
            const styles = {
                draft: 'bg-gray-100 text-gray-600 border border-gray-200',
                posted: 'bg-blue-50 text-blue-700 border border-blue-200',
                void: 'bg-rose-50 text-rose-700 border border-rose-200',
            };

            return styles[status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
        },
    },
};
</script>
