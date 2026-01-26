<template>
    <ConfigLayout>
        <div class="space-y-6">
            <header class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Client</p>
                        <h1 class="text-2xl font-semibold text-serena-text-main">{{ guest.full_name }}</h1>
                        <p class="text-sm text-serena-text-muted">
                            {{ guest.email || '—' }} · {{ guest.phone || '—' }}
                        </p>
                    </div>
                    <Link
                        :href="backUrl"
                        class="rounded-full border border-serena-border px-4 py-1 text-sm font-semibold text-serena-text-muted transition hover:bg-serena-bg-soft"
                    >
                        Retour
                    </Link>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Coordonnées</p>
                    <p class="text-lg font-semibold text-serena-text-main">{{ guest.full_name }}</p>
                    <p class="text-sm text-serena-text-muted">{{ guest.email || '—' }}</p>
                    <p class="text-sm text-serena-text-muted">{{ guest.phone || '—' }}</p>
                    <p class="mt-2 text-xs text-serena-text-muted">
                        {{ guest.document_type || 'Document' }} : {{ guest.document_number || '—' }}
                    </p>
                </div>
                <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Adresse</p>
                    <p class="text-sm text-serena-text-main">{{ formattedAddress }}</p>
                    <p class="mt-2 text-sm text-serena-text-muted">{{ guest.notes || 'Aucune note.' }}</p>
                </div>
                <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Fidélité</p>
                    <p class="text-2xl font-semibold text-serena-primary">{{ loyalty.total_points }}</p>
                    <p class="text-xs text-serena-text-muted">Points cumulés</p>
                </div>
            </section>

            <section class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-serena-text-main">Historique des réservations</p>
                    <span class="text-xs text-serena-text-muted">{{ reservations.length }} réservations</span>
                </div>

                <div class="mt-4 space-y-4">
                    <div
                        v-for="reservation in reservations"
                        :key="reservation.id"
                        class="rounded-xl border border-serena-border/70 p-4"
                    >
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-serena-text-main">{{ reservation.code || 'Réservation' }}</p>
                                <p class="text-xs text-serena-text-muted">
                                    {{ formatDate(reservation.check_in_date) }} → {{ formatDate(reservation.check_out_date) }}
                                    · {{ reservation.room || '—' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-serena-text-main">
                                    {{ formatCurrency(reservation.total_amount, reservation.currency) }}
                                </p>
                                <p class="text-xs text-serena-text-muted">{{ reservation.status_label }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div class="rounded-lg bg-serena-bg-soft p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Paiements</p>
                                <div v-if="reservation.payments.length" class="mt-2 space-y-2 text-sm text-serena-text-main">
                                    <div v-for="payment in reservation.payments" :key="payment.id" class="flex items-center justify-between">
                                        <span>{{ payment.method || 'Méthode' }}</span>
                                        <span class="font-semibold">{{ formatCurrency(payment.amount, payment.currency) }}</span>
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-xs text-serena-text-muted">Aucun paiement enregistré.</p>
                            </div>
                            <div class="rounded-lg border border-serena-border/60 p-3">
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Détails</p>
                                <p class="mt-2 text-sm text-serena-text-main">Offre: {{ reservation.offer || '—' }}</p>
                                <p class="text-sm text-serena-text-muted">Type: {{ reservation.room_type || '—' }}</p>
                                <p class="text-sm text-serena-text-muted">
                                    Solde: {{ formatCurrency(reservation.folio_balance, reservation.currency) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p v-if="!reservations.length" class="text-sm text-serena-text-muted">Aucune réservation pour ce client.</p>
                </div>
            </section>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

export default {
    name: 'GuestShow',
    components: { ConfigLayout, Link },
    props: {
        guest: {
            type: Object,
            required: true,
        },
        reservations: {
            type: Array,
            default: () => [],
        },
        loyalty: {
            type: Object,
            default: () => ({ total_points: 0 }),
        },
    },
    computed: {
        formattedAddress() {
            const parts = [this.guest.address, this.guest.city, this.guest.country].filter(Boolean);
            return parts.length ? parts.join(', ') : '—';
        },
        backUrl() {
            const currentUrl = this.$page?.url ?? '';
            return currentUrl.startsWith('/settings/resources') ? '/settings/resources/guests' : '/guests';
        },
    },
    methods: {
        formatCurrency(amount, currency = 'XAF') {
            const value = Number(amount || 0);
            return `${value.toFixed(0)} ${currency}`;
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
        },
    },
};
</script>
