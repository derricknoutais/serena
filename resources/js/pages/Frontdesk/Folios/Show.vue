<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-8">
            <section class="rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            Folio principal
                        </p>
                        <h1 class="text-2xl font-semibold text-serena-text-main">
                            {{ folio.code }}
                        </h1>
                        <p class="text-sm text-serena-text-muted">
                            Statut :
                            <span class="font-semibold text-serena-text-main">
                                {{ folio.status }}
                            </span>
                        </p>
                    </div>
                    <div class="grid gap-4 text-right text-sm text-serena-text-main md:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-serena-text-muted">
                                Total charges
                            </p>
                            <p class="text-lg font-semibold">
                                {{ formatAmount(folio.charges_total) }} {{ folio.currency }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-serena-text-muted">
                                Paiements
                            </p>
                            <p class="text-lg font-semibold">
                                {{ formatAmount(folio.payments_total) }} {{ folio.currency }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-serena-text-muted">
                                Solde
                            </p>
                            <p
                                class="text-lg font-semibold"
                                :class="folio.balance > 0 ? 'text-serena-danger' : 'text-serena-success'"
                            >
                                {{ formatAmount(folio.balance) }} {{ folio.currency }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="reservation" class="mt-6 rounded-xl bg-white/40 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                        Réservation liée
                    </p>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-serena-text-main">
                        <span class="font-semibold">{{ reservation.code }}</span>
                        <span>Status : {{ reservation.status_label }}</span>
                        <span v-if="reservation.guest">
                            Client : {{ reservation.guest.name }}
                        </span>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 md:grid-cols-2">
                <section class="space-y-4 rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                    <header>
                        <h2 class="text-lg font-semibold text-serena-text-main">Charges</h2>
                        <p class="text-sm text-serena-text-muted">
                            Liste des prestations imputées au folio.
                        </p>
                    </header>
                    <div
                        v-if="items.length === 0"
                        class="rounded-xl border border-dashed border-serena-border/50 p-6 text-center text-sm text-serena-text-muted"
                    >
                        Aucune charge enregistrée pour le moment.
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="item in items"
                            :key="item.id"
                            class="rounded-xl border border-serena-border/50 bg-white/50 p-4"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="font-semibold text-serena-text-main">
                                        {{ item.description }}
                                    </p>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ item.quantity }} × {{ formatAmount(item.unit_price) }} —
                                        {{ item.date }}
                                    </p>
                                </div>
                                <p class="text-base font-semibold text-serena-text-main">
                                    {{ formatAmount(item.total_amount) }} {{ folio.currency }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="space-y-4 rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                    <header>
                        <h2 class="text-lg font-semibold text-serena-text-main">Ajouter une charge</h2>
                        <p class="text-sm text-serena-text-muted">
                            Ajoutez rapidement un produit ou service sur ce folio.
                        </p>
                    </header>
                    <form class="space-y-4" @submit.prevent="submitCharge">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Description
                            </label>
                            <input
                                v-model="chargeForm.description"
                                type="text"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                required
                            />
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                    Quantité
                                </label>
                                <input
                                    v-model.number="chargeForm.quantity"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                    required
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                    Prix unitaire
                                </label>
                                <input
                                    v-model.number="chargeForm.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                    required
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                    Taxe
                                </label>
                                <input
                                    v-model.number="chargeForm.tax_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Date de service
                            </label>
                            <input
                                v-model="chargeForm.date"
                                type="date"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                        </div>
                        <PrimaryButton
                            type="submit"
                            class="w-full justify-center"
                            :disabled="chargeForm.processing"
                        >
                            Ajouter la charge
                        </PrimaryButton>
                    </form>
                </section>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <section class="space-y-4 rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                    <header>
                        <h2 class="text-lg font-semibold text-serena-text-main">Paiements</h2>
                        <p class="text-sm text-serena-text-muted">
                            Historique des règlements appliqués à ce folio.
                        </p>
                    </header>
                    <div
                        v-if="payments.length === 0"
                        class="rounded-xl border border-dashed border-serena-border/50 p-6 text-center text-sm text-serena-text-muted"
                    >
                        Aucun paiement enregistré.
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="payment in payments"
                            :key="payment.id"
                            class="rounded-xl border border-serena-border/50 bg-white/50 p-4"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <p class="font-semibold text-serena-text-main">
                                        {{ payment.method ? payment.method.name : 'Paiement' }}
                                    </p>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ payment.paid_at }} — Réf. {{ payment.reference || 'N/A' }}
                                    </p>
                                </div>
                                <p class="text-base font-semibold text-serena-text-main">
                                    {{ formatAmount(payment.amount) }} {{ payment.currency }}
                                </p>
                            </div>
                            <p v-if="payment.notes" class="mt-2 text-xs text-serena-text-muted">
                                {{ payment.notes }}
                            </p>
                        </li>
                    </ul>
                </section>

                <section class="space-y-4 rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                    <header>
                        <h2 class="text-lg font-semibold text-serena-text-main">Enregistrer un paiement</h2>
                        <p class="text-sm text-serena-text-muted">
                            Choisissez une méthode de paiement disponible pour cet hôtel.
                        </p>
                    </header>
                    <form class="space-y-4" @submit.prevent="submitPayment">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Montant
                            </label>
                            <input
                                v-model.number="paymentForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                required
                            />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                    Devise
                                </label>
                                <input
                                    v-model="paymentForm.currency"
                                    type="text"
                                    maxlength="3"
                                    class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm uppercase text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                    required
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                    Date
                                </label>
                                <input
                                    v-model="paymentForm.paid_at"
                                    type="date"
                                    class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Méthode
                            </label>
                            <select
                                v-model="paymentForm.payment_method_id"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                required
                            >
                                <option value="" disabled>Choisir...</option>
                                <option
                                    v-for="method in paymentMethods"
                                    :key="method.id"
                                    :value="method.id"
                                >
                                    {{ method.name }} ({{ method.code }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Référence
                            </label>
                            <input
                                v-model="paymentForm.reference"
                                type="text"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                                Notes
                            </label>
                            <textarea
                                v-model="paymentForm.notes"
                                rows="3"
                                class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                            />
                        </div>
                        <PrimaryButton
                            type="submit"
                            class="w-full justify-center"
                            :disabled="paymentForm.processing"
                        >
                            Enregistrer le paiement
                        </PrimaryButton>
                    </form>
                </section>
            </div>

            <section class="space-y-4 rounded-2xl border border-serena-border/30 bg-serena-card p-6 shadow-sm">
                <header>
                    <h2 class="text-lg font-semibold text-serena-text-main">Factures</h2>
                    <p class="text-sm text-serena-text-muted">
                        Historique des factures générées pour ce folio.
                    </p>
                </header>
                <div
                    v-if="invoices.length === 0"
                    class="rounded-xl border border-dashed border-serena-border/50 p-6 text-center text-sm text-serena-text-muted"
                >
                    Aucune facture générée.
                </div>
                <ul v-else class="space-y-3">
                    <li
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="rounded-xl border border-serena-border/50 bg-white/50 p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-4 text-sm">
                            <div>
                                <p class="font-semibold text-serena-text-main">
                                    {{ invoice.number }}
                                </p>
                                <p class="text-xs text-serena-text-muted">
                                    {{ invoice.status }} — {{ invoice.issue_date || '—' }}
                                </p>
                            </div>
                            <p class="text-base font-semibold text-serena-text-main">
                                {{ formatAmount(invoice.total_amount) }} {{ invoice.currency }}
                            </p>
                        </div>
                    </li>
                </ul>
                <form class="mt-6 space-y-4 rounded-xl border border-serena-border/50 bg-white/60 p-4" @submit.prevent="submitInvoice">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase text-serena-text-muted">
                            Notes
                        </label>
                        <textarea
                            v-model="invoiceForm.notes"
                            rows="3"
                            class="w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-serena-text-main">
                        <input
                            v-model="invoiceForm.close_folio"
                            type="checkbox"
                            class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary-soft"
                        />
                        Clôturer automatiquement le folio
                    </label>
                    <PrimaryButton
                        type="submit"
                        class="w-full justify-center"
                        :disabled="invoiceForm.processing"
                    >
                        Générer une facture
                    </PrimaryButton>
                </form>
            </section>
        </div>
    </AppLayout>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'FrontdeskFolioShow',
    components: {
        AppLayout,
        PrimaryButton,
    },
    props: {
        folio: {
            type: Object,
            required: true,
        },
        reservation: {
            type: Object,
            default: null,
        },
        items: {
            type: Array,
            default: () => [],
        },
        payments: {
            type: Array,
            default: () => [],
        },
        invoices: {
            type: Array,
            default: () => [],
        },
        paymentMethods: {
            type: Array,
            default: () => [],
        },
        errors: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        const today = new Date().toISOString().slice(0, 10);
        const defaultMethod = this.paymentMethods.length ? this.paymentMethods[0].id : '';

        return {
            chargeForm: useForm({
                description: '',
                quantity: 1,
                unit_price: 0,
                tax_amount: 0,
                date: today,
            }),
            paymentForm: useForm({
                amount: 0,
                currency: this.folio.currency,
                payment_method_id: defaultMethod,
                paid_at: today,
                reference: '',
                notes: '',
            }),
            invoiceForm: useForm({
                notes: '',
                close_folio: false,
            }),
        };
    },
    methods: {
        formatAmount(value) {
            const amount = Number(value || 0);
            return amount.toLocaleString('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        submitCharge() {
            this.chargeForm.post(`/folios/${this.folio.id}/items`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.chargeForm.reset('description', 'quantity', 'unit_price', 'tax_amount');
                    this.chargeForm.quantity = 1;
                    this.chargeForm.unit_price = 0;
                    this.chargeForm.tax_amount = 0;
                },
            });
        },
        submitPayment() {
            this.paymentForm.post(`/folios/${this.folio.id}/payments`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.paymentForm.reset('amount', 'reference', 'notes');
                    this.paymentForm.amount = 0;
                    window.dispatchEvent(new CustomEvent('cash-session-updated', {
                        detail: { type: 'frontdesk' },
                    }));
                },
            });
        },
        submitInvoice() {
            this.invoiceForm.post(`/folios/${this.folio.id}/invoices`, {
                preserveScroll: true,
                onSuccess: () => {
                    this.invoiceForm.reset('notes', 'close_folio');
                },
            });
        },
    },
};
</script>
