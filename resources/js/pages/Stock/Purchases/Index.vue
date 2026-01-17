<template>
    <AppLayout title="Bons d'achat">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Bons d'achat</h1>
                    <p class="text-sm text-serena-text-muted">Consultez et suivez les entrées stockées.</p>
                </div>
                <Link
                    v-if="permissions.can_create_purchase"
                    href="/stock/purchases/create"
                    class="rounded-full bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                >
                    Nouveau bon d'achat
                </Link>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div v-if="!purchases.length" class="text-sm text-serena-text-muted">
                    Aucun bon d'achat enregistré.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Réf.</th>
                                <th class="px-4 py-3">Emplacement</th>
                                <th class="px-4 py-3">Fournisseur</th>
                                <th class="px-4 py-3 text-right">Montant</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr
                                v-for="purchase in purchases"
                                :key="purchase.id"
                                class="cursor-pointer transition hover:bg-serena-bg-soft/60"
                                @click="goToPurchase(purchase)"
                            >
                                <td class="px-4 py-3 font-semibold">{{ purchase.reference_no || `#${purchase.id}` }}</td>
                                <td class="px-4 py-3">{{ purchase.storage_location?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ purchase.supplier_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ formatAmount(purchase.total_amount, purchase.currency) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-[11px] font-semibold text-serena-text-main" :class="statusClasses(purchase.status)">
                                        {{ statusLabel(purchase.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button
                                            v-if="permissions.can_receive_purchase && purchase.status === 'draft'"
                                            type="button"
                                            class="text-xs font-semibold text-emerald-700 hover:text-emerald-900"
                                            @click.stop="receivePurchase(purchase)"
                                        >
                                            Recevoir
                                        </button>
                                        <Link
                                            :href="`/stock/purchases/${purchase.id}`"
                                            class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                        >
                                            Détails
                                        </Link>
                                    </div>
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
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

export default {
    name: 'StockPurchaseIndex',
    components: {
        AppLayout,
        Link,
    },
    props: {
        purchases: {
            type: Array,
            default: () => [],
        },
        permissions: {
            type: Object,
            default: () => ({
                can_create_purchase: false,
                can_receive_purchase: false,
            }),
        },
    },
    methods: {
        goToPurchase(purchase) {
            this.$inertia.visit(`/stock/purchases/${purchase.id}`);
        },
        async receivePurchase(purchase) {
            if (!this.permissions.can_receive_purchase || purchase.status !== 'draft') {
                return;
            }

            const result = await Swal.fire({
                title: 'Recevoir ce bon d\'achat ?',
                text: 'Cette action mettra le stock à jour.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, recevoir',
                cancelButtonText: 'Annuler',
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                await axios.post(`/stock/purchases/${purchase.id}/receive`, null, {
                    headers: { Accept: 'application/json' },
                });

                await Swal.fire({
                    icon: 'success',
                    title: 'Bon d\'achat réceptionné',
                    timer: 1500,
                    showConfirmButton: false,
                });

                router.reload({ only: ['purchases'] });
            } catch (error) {
                const message = error.response?.data?.message ?? 'Impossible de réceptionner le bon d\'achat.';
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            }
        },
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        statusLabel(status) {
            const map = {
                draft: 'Brouillon',
                received: 'Reçu',
                void: 'Annulé',
            };

            return map[status] ?? status;
        },
        statusClasses(status) {
            const styles = {
                draft: 'bg-gray-100 text-gray-600 border border-gray-200',
                received: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                void: 'bg-rose-50 text-rose-700 border border-rose-200',
            };

            return styles[status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
        },
    },
};
</script>
