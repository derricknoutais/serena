<template>
    <AppLayout title="Inventaires">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Inventaires</h1>
                    <p class="text-sm text-serena-text-muted">Contrôles de stocks enregistrés.</p>
                </div>
                <Link
                    v-if="permissions.can_create_inventory"
                    href="/stock"
                    class="rounded-full bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                >
                    Nouvel inventaire
                </Link>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div v-if="!inventories.length" class="text-sm text-serena-text-muted">
                    Aucun inventaire enregistré.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Emplacement</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Lignes</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="inventory in inventories" :key="inventory.id">
                                <td class="px-4 py-3">{{ inventory.storage_location?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold text-serena-text-main" :class="statusClasses(inventory.status)">
                                        {{ statusLabel(inventory.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ inventory.lines }} ligne(s)</td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="`/stock/inventories/${inventory.id}`"
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
    name: 'StockInventoryIndex',
    components: {
        AppLayout,
        Link,
    },
    props: {
        inventories: {
            type: Array,
            default: () => [],
        },
        permissions: {
            type: Object,
            default: () => ({
                can_create_inventory: false,
                can_post_inventory: false,
            }),
        },
    },
    methods: {
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
