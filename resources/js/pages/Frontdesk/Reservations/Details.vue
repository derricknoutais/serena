<template>
    <div class="space-y-6">
        <header class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Réservation</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-semibold text-serena-text-main">{{ reservation.code }}</h1>
                        <span class="rounded-full border border-serena-border px-3 py-1 text-xs font-semibold text-serena-primary">{{ reservation.status_label }}</span>
                    </div>
                    <p class="text-sm text-serena-text-muted">
                        {{ reservation.guest?.full_name ?? '—' }} · {{ reservation.room?.number ?? 'Non assignée' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="capabilities.cancel_reservation"
                        class="rounded-full border border-serena-border px-4 py-1 text-sm font-semibold text-serena-danger transition hover:bg-danger-50"
                        @click="handleCancel"
                    >
                        Annuler
                    </button>
                    <button
                        v-if="capabilities.refund_payment"
                        class="rounded-full border border-serena-border px-4 py-1 text-sm font-semibold text-serena-text-muted transition hover:bg-serena-bg-soft"
                        @click="handleRefund"
                    >
                        Rembourser
                    </button>
                    <button
                        v-if="capabilities.delete_reservation"
                        class="rounded-full border border-serena-border px-4 py-1 text-sm font-semibold text-serena-text-muted transition hover:bg-serena-bg-soft"
                        @click="handleDelete"
                    >
                        Supprimer
                    </button>
                </div>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Séjour</p>
                    <p class="text-lg font-semibold text-serena-text-main">
                        {{ formatDate(reservation.check_in_date) }} → {{ formatDate(reservation.check_out_date) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Montants</p>
                    <p class="text-lg font-semibold text-serena-text-main">
                        Total {{ formatCurrency(reservation.total_amount, reservation.currency) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Equilibre du folio</p>
                    <p class="text-lg font-semibold text-serena-primary">
                        {{ folio ? formatCurrency(folio.balance, folio.currency) : '—' }}
                    </p>
                </div>
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Informations invité</p>
                <p class="text-lg font-semibold text-serena-text-main">{{ reservation.guest?.full_name ?? '—' }}</p>
                <p class="text-sm text-serena-text-muted">{{ reservation.guest?.email ?? '—' }}</p>
                <p class="text-sm text-serena-text-muted">{{ reservation.guest?.phone ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Chambre & offre</p>
                <p class="text-lg font-semibold text-serena-text-main">
                    {{ reservation.room?.number ?? '—' }} · {{ reservation.room_type?.name ?? '—' }}
                </p>
                <p class="text-sm text-serena-text-muted">{{ reservation.offer?.name ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">Réservé par</p>
                <p class="text-lg font-semibold text-serena-text-main">{{ reservation.booked_by?.name ?? '—' }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-serena-text-main">Folio</p>
                <span class="text-xs text-serena-text-muted">Total charges: {{ formatCurrency(folio?.total_charges, folio?.currency) }}</span>
            </div>
            <div class="mt-4 space-y-3 text-sm text-serena-text-main">
                <div v-for="item in folio?.items" :key="item.id" class="flex items-center justify-between border-b border-serena-border/50 pb-2">
                    <span>{{ item.description }}</span>
                    <span>{{ formatCurrency(item.amount, folio.currency) }}</span>
                </div>
                <p v-if="!folio?.items?.length" class="text-sm text-serena-text-muted">Aucun élément de folio.</p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-serena-text-main">Paiements</p>
                <div class="mt-3 space-y-3 text-sm text-serena-text-main">
                    <div v-for="payment in folio?.payments" :key="payment.id" class="flex items-center justify-between">
                        <span>{{ payment.method ?? 'Méthode inconnue' }}</span>
                        <span>{{ formatCurrency(payment.amount, payment.currency) }}</span>
                    </div>
                    <p v-if="!folio?.payments?.length" class="text-xs text-serena-text-muted">Aucun paiement enregistré.</p>
                </div>
            </div>
            <div class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-serena-text-main">Facture</p>
                <div class="mt-3 space-y-2 text-sm text-serena-text-muted">
                    <p v-if="folio?.invoices.length">Factures : {{ folio.invoices.length }}</p>
                    <p v-else>Aucune facture émise.</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-serena-border bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-serena-text-main">Historique rapide</p>
            <p class="text-xs text-serena-text-muted">Les actions complètes sont disponibles depuis le journal d’activités.</p>
        </section>
    </div>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
    name: 'ReservationDetails',
    layout: AppLayout,
    props: {
        reservation: {
            type: Object,
            required: true,
        },
        folio: {
            type: Object,
            default: null,
        },
        capabilities: {
            type: Object,
            default: () => ({}),
        },
    },
    methods: {
        formatCurrency(amount, currency = 'XAF') {
            const value = Number(amount || 0);
            return `${value.toFixed(0)} ${currency}`;
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
        },
        handleCancel() {
            alert('Annuler la réservation (à implémenter).');
        },
        handleRefund() {
            alert('Ouvrir le modal de remboursement (à implémenter).');
        },
        handleDelete() {
            alert('Supprimer la réservation (à implémenter).');
        },
    },
};
</script>
