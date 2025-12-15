<template>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-serena-primary">Night Audit</p>
                    <h1 class="text-2xl font-bold text-serena-text-main">Rapport de fin de journée</h1>
                    <p class="text-sm text-serena-text-muted">
                        Résumé quotidien par hôtel et export PDF.
                    </p>
                </div>
                <div class="flex flex-col gap-2 md:flex-row md:items-center">
                    <label class="text-sm font-semibold text-serena-text-main">
                        Date d’affaires
                        <input
                            v-model="localDate"
                            type="date"
                            class="mt-1 w-full rounded-lg border border-serena-border px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-main">
                        Hôtel
                        <select
                            v-model="localHotelId"
                            class="mt-1 w-full rounded-lg border border-serena-border px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        >
                            <option v-for="hotel in hotels" :key="hotel.id" :value="hotel.id">
                                {{ hotel.name }}
                            </option>
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <PrimaryButton type="button" class="px-4 py-2" @click="reloadReport">
                            Générer
                        </PrimaryButton>
                        <SecondaryButton type="button" class="px-4 py-2" @click="exportPdf">
                            Exporter PDF
                        </SecondaryButton>
                    </div>
                </div>
            </div>

            <div v-if="!report" class="rounded-2xl border border-dashed border-serena-border bg-white p-6 text-center text-sm text-serena-text-muted">
                Sélectionnez une date puis cliquez sur Générer.
            </div>

            <div v-else class="space-y-6">
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="text-lg font-semibold text-serena-text-main">Occupation</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Total chambres</p>
                            <p class="text-xl font-bold">{{ report.occupancy.total_rooms ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Occupées</p>
                            <p class="text-xl font-bold">{{ report.occupancy.occupied_rooms ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Disponibles</p>
                            <p class="text-xl font-bold">{{ report.occupancy.available_rooms ?? 0 }}</p>
                        </div>
                            <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                                <p class="text-xs text-serena-text-muted">Taux d’occupation</p>
                                <p class="text-xl font-bold">{{ report.occupancy.occupancy_rate ?? 0 }}%</p>
                            </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <h3 class="text-lg font-semibold text-serena-text-main">Arrivées</h3>
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-serena-text-muted">
                                    <th class="py-2">Code</th>
                                    <th class="py-2">Chambre</th>
                                    <th class="py-2">Client</th>
                                    <th class="py-2">Check-in</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in report.movements.arrivals" :key="item.code" class="border-t text-serena-text-main">
                                    <td class="py-2">{{ item.code }}</td>
                                    <td class="py-2">{{ item.room || '—' }}</td>
                                    <td class="py-2">{{ item.guest || '—' }}</td>
                                    <td class="py-2 text-xs text-serena-text-muted">{{ item.check_in_at || '—' }}</td>
                                </tr>
                                <tr v-if="!report.movements.arrivals?.length">
                                    <td colspan="4" class="py-2 text-sm text-serena-text-muted">Aucune arrivée.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <h3 class="text-lg font-semibold text-serena-text-main">Départs</h3>
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-serena-text-muted">
                                    <th class="py-2">Code</th>
                                    <th class="py-2">Chambre</th>
                                    <th class="py-2">Client</th>
                                    <th class="py-2">Check-out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in report.movements.departures" :key="item.code" class="border-t text-serena-text-main">
                                    <td class="py-2">{{ item.code }}</td>
                                    <td class="py-2">{{ item.room || '—' }}</td>
                                    <td class="py-2">{{ item.guest || '—' }}</td>
                                    <td class="py-2 text-xs text-serena-text-muted">{{ item.check_out_at || '—' }}</td>
                                </tr>
                                <tr v-if="!report.movements.departures?.length">
                                    <td colspan="4" class="py-2 text-sm text-serena-text-muted">Aucun départ.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <h3 class="text-lg font-semibold text-serena-text-main">Revenus</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Chambres</p>
                            <p class="text-xl font-bold">{{ formatAmount(report.revenue.room_revenue) }}</p>
                        </div>
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">POS / Bar</p>
                            <p class="text-xl font-bold">{{ formatAmount(report.revenue.pos_revenue) }}</p>
                        </div>
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Taxes</p>
                            <p class="text-xl font-bold">{{ formatAmount(report.revenue.tax_total) }}</p>
                        </div>
                        <div class="rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                            <p class="text-xs text-serena-text-muted">Total</p>
                            <p class="text-xl font-bold">{{ formatAmount(report.revenue.total_revenue) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <h3 class="text-lg font-semibold text-serena-text-main">Paiements par méthode</h3>
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">Méthode</th>
                                <th class="py-2 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(amount, method) in report.payments_by_method" :key="method" class="border-t text-serena-text-main">
                                <td class="py-2">{{ method }}</td>
                                <td class="py-2 text-right">{{ formatAmount(amount) }}</td>
                            </tr>
                            <tr v-if="!Object.keys(report.payments_by_method || {}).length">
                                <td colspan="2" class="py-2 text-sm text-serena-text-muted">Aucun paiement.</td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="py-2">Total</td>
                                <td class="py-2 text-right">{{ formatAmount(report.total_payments) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <h3 class="text-lg font-semibold text-serena-text-main">Réconciliation des caisses</h3>
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-serena-text-muted">
                                <th class="py-2">POS</th>
                                <th class="py-2">Ouverture</th>
                                <th class="py-2">Clôture</th>
                                <th class="py-2">Fond initial</th>
                                <th class="py-2">Encaissements</th>
                                <th class="py-2">Attendu</th>
                                <th class="py-2">Compté</th>
                                <th class="py-2">Écart</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(session, idx) in report.cash_reconciliation.sessions" :key="idx" class="border-t text-serena-text-main">
                                <td class="py-2">{{ session.type || '—' }}</td>
                                <td class="py-2 text-xs text-serena-text-muted">{{ session.opened_at || '—' }}</td>
                                <td class="py-2 text-xs text-serena-text-muted">{{ session.closed_at || '—' }}</td>
                                <td class="py-2">{{ formatAmount(session.opening_amount) }}</td>
                                <td class="py-2">{{ formatAmount(session.cash_in) }}</td>
                                <td class="py-2">{{ formatAmount(session.expected_close) }}</td>
                                <td class="py-2">{{ formatAmount(session.actual_close) }}</td>
                                <td class="py-2">{{ formatAmount(session.difference) }}</td>
                            </tr>
                            <tr v-if="!report.cash_reconciliation.sessions?.length">
                                <td colspan="8" class="py-2 text-sm text-serena-text-muted">Aucune session clôturée.</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-4 rounded-xl border border-serena-border bg-serena-bg-soft p-3">
                        <h4 class="text-sm font-semibold text-serena-text-main">Totaux</h4>
                        <table class="mt-2 w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-serena-text-muted">
                                    <th class="py-1">POS</th>
                                    <th class="py-1">Fond initial</th>
                                    <th class="py-1">Encaissements</th>
                                    <th class="py-1">Attendu</th>
                                    <th class="py-1">Compté</th>
                                    <th class="py-1">Écart</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(totals, pos) in report.cash_reconciliation.totals" :key="pos" class="border-t">
                                    <td class="py-1 capitalize">{{ pos }}</td>
                                    <td class="py-1">{{ formatAmount(totals.opening_amount) }}</td>
                                    <td class="py-1">{{ formatAmount(totals.cash_in) }}</td>
                                    <td class="py-1">{{ formatAmount(totals.expected_close) }}</td>
                                    <td class="py-1">{{ formatAmount(totals.actual_close) }}</td>
                                    <td class="py-1">{{ formatAmount(totals.difference) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'NightAuditIndex',
    components: { AppLayout, PrimaryButton, SecondaryButton },
    props: {
        report: {
            type: Object,
            default: null,
        },
        businessDate: {
            type: String,
            required: true,
        },
        hotelId: {
            type: Number,
            required: true,
        },
    },
    data() {
        return {
            localDate: this.businessDate,
            localHotelId: this.hotelId,
        };
    },
    computed: {
        hotels() {
            return this.$page?.props?.auth?.hotels ?? [];
        },
        currency() {
            return this.report?.hotel?.currency || 'XAF';
        },
        canExport() {
            return this.$page?.props?.auth?.can?.night_audit_export ?? false;
        },
    },
    methods: {
        reloadReport() {
            router.get('/night-audit', {
                date: this.localDate,
                hotel_id: this.localHotelId,
                refresh: true,
            }, {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de charger le rapport.',
                    });
                },
            });
        },
        exportPdf() {
            if (!this.canExport) {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });
                return;
            }

            const params = new URLSearchParams({
                date: this.localDate,
                hotel_id: this.localHotelId,
            });
            window.open(`/night-audit/pdf?${params.toString()}`, '_blank');
        },
        formatAmount(value) {
            const amount = Number(value || 0);
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: this.currency,
                minimumFractionDigits: 0,
            }).format(amount);
        },
    },
};
</script>
