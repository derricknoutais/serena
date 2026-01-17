<template>
    <AppLayout title="Détail du bon d'achat">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Bon d'achat</h1>
                    <p class="text-sm text-serena-text-muted">{{ purchase.reference_no ? `Réf. ${purchase.reference_no}` : `#${purchase.id}` }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/stock/purchases"
                        class="text-xs font-semibold uppercase tracking-wide text-serena-primary hover:underline"
                    >
                        ← Retour aux bons
                    </Link>
                    <button
                        v-if="permissions.can_receive_purchase && purchase.status === 'draft'"
                        type="button"
                        class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                        @click="receivePurchase"
                    >
                        Recevoir
                    </button>
                    <Link
                        v-if="permissions.can_update_purchase && purchase.status === 'draft'"
                        :href="`/stock/purchases/${purchase.id}/edit`"
                        class="rounded-full bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                    >
                        Modifier
                    </Link>
                    <button
                        v-if="permissions.can_update_purchase && purchase.status === 'draft'"
                        type="button"
                        class="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                        @click="voidPurchase"
                    >
                        Annuler
                    </button>
                </div>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Emplacement</p>
                        <p class="text-sm text-serena-text-main">{{ purchase.storage_location?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Fournisseur</p>
                        <p class="text-sm text-serena-text-main">{{ purchase.supplier_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Montant</p>
                        <p class="text-sm font-semibold text-serena-text-main">{{ formatAmount(purchase.total_amount, purchase.currency) }}</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Statut</p>
                        <span class="rounded-full px-3 py-1 text-[11px] font-semibold text-serena-text-main" :class="statusClasses(purchase.status)">
                            {{ statusLabel(purchase.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Reçu le</p>
                        <p class="text-sm text-serena-text-main">{{ purchase.purchased_at ?? '—' }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-serena-text-main">Lignes</h2>
                    <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">{{ purchase.lines.length }} ligne(s)</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-serena-border text-sm">
                        <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                            <tr>
                                <th class="px-4 py-3">Article</th>
                                <th class="px-4 py-3">Quantité</th>
                                <th class="px-4 py-3 text-right">Prix uni.</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-serena-border text-serena-text-main">
                            <tr v-for="line in purchase.lines" :key="line.id">
                                <td class="px-4 py-3">{{ line.stock_item?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatQuantity(line.quantity) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatAmount(line.unit_cost, line.currency) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatAmount(line.total_cost, line.currency) }}</td>
                                <td class="px-4 py-3">{{ line.notes ?? '—' }}</td>
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
    name: 'StockPurchaseShow',
    components: {
        AppLayout,
        Link,
    },
    props: {
        purchase: {
            type: Object,
            required: true,
        },
        permissions: {
            type: Object,
            default: () => ({
                can_update_purchase: false,
                can_receive_purchase: false,
            }),
        },
    },
    methods: {
        async receivePurchase() {
            if (!this.permissions.can_receive_purchase || this.purchase.status !== 'draft') {
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
                await axios.post(`/stock/purchases/${this.purchase.id}/receive`, null, {
                    headers: { Accept: 'application/json' },
                });

                await Swal.fire({
                    icon: 'success',
                    title: 'Bon d\'achat réceptionné',
                    timer: 1500,
                    showConfirmButton: false,
                });

                router.reload({ only: ['purchase'] });
            } catch (error) {
                const message = error.response?.data?.message ?? 'Impossible de réceptionner le bon d\'achat.';
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: message,
                });
            }
        },
        async voidPurchase() {
            if (!this.permissions.can_update_purchase || this.purchase.status !== 'draft') {
                return;
            }

            const result = await Swal.fire({
                title: 'Annuler ce bon d\'achat ?',
                text: 'Cette action est irreversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, annuler',
                cancelButtonText: 'Retour',
                confirmButtonColor: '#e11d48',
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                await axios.post(`/stock/purchases/${this.purchase.id}/void`, null, {
                    headers: { Accept: 'application/json' },
                });

                await Swal.fire({
                    icon: 'success',
                    title: 'Bon d\'achat annulé',
                    timer: 1500,
                    showConfirmButton: false,
                });

                router.reload({ only: ['purchase'] });
            } catch (error) {
                const message = error.response?.data?.message ?? 'Impossible d\'annuler le bon d\'achat.';
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
        formatQuantity(value) {
            const quantity = Number(value || 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
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
