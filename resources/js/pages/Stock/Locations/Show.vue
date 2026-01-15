<template>
    <AppLayout title="Détail de l'emplacement">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">{{ location.name }}</h1>
                    <p class="text-sm text-serena-text-muted">{{ location.category }}</p>
                </div>
                <Link
                    href="/stock/locations"
                    class="text-xs font-semibold uppercase tracking-wide text-serena-primary hover:underline"
                >
                    ← Retour aux emplacements
                </Link>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Article</th>
                                <th class="px-4 py-3">Quantité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="record in records" :key="record.id">
                                <td class="px-4 py-3">{{ record.stock_item?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatQuantity(record.quantity_on_hand) }}</td>
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
    name: 'StockLocationShow',
    components: {
        AppLayout,
        Link,
    },
    props: {
        location: {
            type: Object,
            required: true,
        },
        records: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        formatQuantity(value) {
            const quantity = Number(value || 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
    },
};
</script>
