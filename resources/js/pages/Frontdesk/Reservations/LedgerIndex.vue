<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-serena-text-main">Ledger des réservations</h1>
                <p class="text-sm text-serena-text-muted">Liste complète avec filtres, tri et capacité d'explorer les détails.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border border-serena-border px-4 py-1 text-sm font-medium text-serena-text-muted transition hover:bg-serena-primary-soft hover:text-serena-primary"
                    @click="resetFilters"
                >
                    Réinitialiser
                </button>
                <button
                    type="button"
                    class="rounded-full bg-serena-primary px-4 py-1 text-sm font-semibold text-white transition hover:bg-serena-primary-dark"
                    :disabled="!can.view_details"
                    @click="visitNewReservation"
                >
                    Nouvelle réservation
                </button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <label class="text-sm font-medium text-serena-text-muted">
                Code
                <input
                    v-model="localFilters.code"
                    type="text"
                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none"
                    placeholder="RSV-..."
                />
            </label>
            <label class="text-sm font-medium text-serena-text-muted">
                Invité
                <input
                    v-model="localFilters.guest"
                    type="text"
                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none"
                    placeholder="Nom, email, téléphone"
                />
            </label>
            <label class="text-sm font-medium text-serena-text-muted">
                Date d'arrivée (de)
                <input v-model="localFilters.check_in_from" type="date" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
            </label>
            <label class="text-sm font-medium text-serena-text-muted">
                Date d'arrivée (à)
                <input v-model="localFilters.check_in_to" type="date" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
            </label>
        </div>
        <div class="flex justify-end">
            <button
                type="button"
                class="rounded-full bg-serena-primary px-4 py-1 text-sm font-semibold text-white transition hover:bg-serena-primary-dark"
                @click="applyFilters"
            >
                Appliquer
            </button>
        </div>

        <section class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="(label, value) in statusOptions"
                    :key="value"
                    class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                    :class="isStatusSelected(value) ? 'border-serena-primary bg-serena-primary/10 text-serena-primary' : 'border-serena-border text-serena-text-muted'"
                    @click="toggleStatus(value)"
                >
                    {{ label }}
                </span>
            </div>
        </section>

        <section class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-serena-border text-sm">
                    <thead class="bg-serena-bg-soft text-left text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Invité</th>
                            <th class="px-3 py-2">Chambre</th>
                            <th class="px-3 py-2">Offre</th>
                            <th class="px-3 py-2">Arrivée</th>
                            <th class="px-3 py-2">Départ</th>
                            <th class="px-3 py-2">Statut</th>
                            <th class="px-3 py-2 text-right">Montant</th>
                            <th class="px-3 py-2 text-right">Solde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-serena-border text-serena-text-main">
                        <tr
                            v-for="reservation in reservations.data"
                            :key="reservation.id"
                            class="hover:bg-serena-bg-soft cursor-pointer"
                            @click="goToDetails(reservation.id)"
                        >
                            <td class="px-3 py-3 font-semibold">{{ reservation.code }}</td>
                            <td class="px-3 py-3">{{ reservation.guest?.full_name ?? '—' }}</td>
                            <td class="px-3 py-3">{{ reservation.room?.number ?? '—' }}</td>
                            <td class="px-3 py-3">{{ reservation.offer?.name ?? '—' }}</td>
                            <td class="px-3 py-3">{{ reservation.check_in_date ?? '—' }}</td>
                            <td class="px-3 py-3">{{ reservation.check_out_date ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full bg-serena-bg-soft px-3 py-1 text-xs font-semibold text-serena-primary">
                                    {{ reservation.status_label }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right font-semibold">{{ formatCurrency(reservation.total_amount, reservation.currency) }}</td>
                            <td class="px-3 py-3 text-right">{{ formatCurrency(reservation.folio_balance, reservation.currency) }}</td>
                        </tr>
                        <tr v-if="!reservations.data.length">
                            <td colspan="9" class="px-3 py-6 text-center text-sm text-serena-text-muted">Aucune réservation trouvée.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex items-center justify-between text-sm text-serena-text-muted">
            <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border border-serena-border px-3 py-1 text-xs font-semibold transition hover:bg-serena-bg-soft"
                    :disabled="!pagination.links.prev"
                    @click="navigateTo(pagination.links.prev)"
                >
                    Précédent
                </button>
                <button
                    type="button"
                    class="rounded-full border border-serena-border px-3 py-1 text-xs font-semibold transition hover:bg-serena-bg-soft"
                    :disabled="!pagination.links.next"
                    @click="navigateTo(pagination.links.next)"
                >
                    Suivant
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { index } from '@/routes/reservations';
import { details } from '@/routes/frontdesk/reservations';

export default {
    name: 'ReservationsLedgerIndex',
    layout: AppLayout,
    props: {
        reservations: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
        statusOptions: {
            type: Object,
            required: true,
        },
        rooms: {
            type: Array,
            default: () => [],
        },
        can: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            localFilters: {
                code: this.filters.code ?? '',
                guest: this.filters.guest ?? '',
                check_in_from: this.filters.check_in_from ?? '',
                check_in_to: this.filters.check_in_to ?? '',
                status: this.filters.status ?? [],
                sort: this.filters.sort ?? '-created_at',
            },
        };
    },
    computed: {
        pagination() {
            const meta = this.reservations.meta ?? {};
            return {
                current_page: meta.current_page ?? 1,
                last_page: meta.last_page ?? 1,
                links: meta.links ?? { prev: null, next: null },
            };
        },
    },
    methods: {
        formatCurrency(amount, currency = 'XAF') {
            const value = Number(amount || 0);
            return `${value.toFixed(0)} ${currency}`;
        },
        applyFilters() {
            router.get(
                window.location.pathname,
                {
                    code: this.localFilters.code,
                    guest: this.localFilters.guest,
                    check_in_from: this.localFilters.check_in_from,
                    check_in_to: this.localFilters.check_in_to,
                    status: this.localFilters.status,
                    sort: this.localFilters.sort,
                },
                { preserveState: true },
            );
        },
        resetFilters() {
            this.localFilters = {
                code: '',
                guest: '',
                check_in_from: '',
                check_in_to: '',
                status: [],
                sort: '-created_at',
            };
            this.applyFilters();
        },
        toggleStatus(value) {
            const idx = this.localFilters.status.indexOf(value);
            if (idx >= 0) {
                this.localFilters.status.splice(idx, 1);
            } else {
                this.localFilters.status.push(value);
            }
            this.applyFilters();
        },
        isStatusSelected(value) {
            return this.localFilters.status.includes(value);
        },
        goToDetails(reservationId) {
            if (!this.can.view_details) {
                return;
            }
            router.visit(details.url({ reservation: reservationId }));
        },
        navigateTo(url) {
            if (!url) {
                return;
            }
            router.visit(url, { preserveState: true });
        },
        visitNewReservation() {
            router.visit(index.url());
        },
    },
};
</script>
