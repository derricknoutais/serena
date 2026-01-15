<template>
    <AppLayout title="Emplacements">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Emplacements</h1>
                    <p class="text-sm text-serena-text-muted">Gérer les emplacements et leurs stocks.</p>
                </div>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div v-if="!locations.length" class="text-sm text-serena-text-muted">
                    Aucun emplacement disponible.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-3">
                    <div
                        v-for="location in locations"
                        :key="location.id"
                        class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm"
                        :class="{
                            'border-serena-primary bg-serena-primary-soft text-serena-primary': selectedLocationId === location.id,
                        }"
                        @click="selectLocation(location.id)"
                    >
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-serena-text-main">{{ location.name }}</h2>
                            <span class="text-xs text-serena-text-muted">{{ location.category }}</span>
                        </div>
                        <p class="text-sm text-serena-text-muted">{{ location.count }} article(s)</p>
                        <Link
                            :href="`/stock/locations/${location.id}`"
                            class="mt-3 inline-flex text-xs font-semibold text-serena-primary hover:underline"
                        >
                            Voir le détail
                        </Link>
                    </div>
                </div>
            </article>
            <section v-if="selectedLocation" class="space-y-3 rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-serena-text-main">Articles dans {{ selectedLocation.name }}</h2>
                    <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">{{ selectedRecords.length }} article(s)</span>
                </div>
                <div v-if="!selectedRecords.length" class="text-sm text-serena-text-muted">
                    Aucun stock enregistré pour cet emplacement.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Article</th>
                                <th class="px-4 py-3 text-right">Quantité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="record in selectedRecords" :key="record.id">
                                <td class="px-4 py-3">{{ record.stock_item?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ formatQuantity(record.quantity_on_hand) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

export default {
    name: 'StockLocationIndex',
    components: {
        AppLayout,
        Link,
    },
    props: {
        locations: {
            type: Array,
            default: () => [],
        },
        records: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            selectedLocationId: null,
        };
    },
    computed: {
        selectedLocation() {
            return this.locations.find((location) => location.id === this.selectedLocationId) ?? null;
        },
        selectedRecords() {
            if (!this.selectedLocationId) {
                return [];
            }

            return this.records[this.selectedLocationId] ?? [];
        },
    },
    methods: {
        formatQuantity(value) {
            const quantity = Number(value || 0);

            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        selectLocation(id) {
            this.selectedLocationId = this.selectedLocationId === id ? null : id;
        },
    },
};
</script>
