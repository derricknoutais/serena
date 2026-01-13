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
                                    <div class="mt-1 flex flex-wrap gap-2 text-[11px]">
                                        <span
                                            v-if="payment.voided_at || payment.deleted_at"
                                            class="rounded-full bg-gray-200 px-2 py-0.5 font-semibold text-gray-600"
                                        >
                                            Annulé
                                        </span>
                                        <span
                                            v-else-if="payment.entry_type === 'refund'"
                                            class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700"
                                        >
                                            Remboursement
                                        </span>
                                    </div>
                                </div>
                                <p class="text-base font-semibold text-serena-text-main">
                                    {{ formatAmount(payment.amount) }} {{ payment.currency }}
                                </p>
                            </div>
                            <p v-if="payment.notes" class="mt-2 text-xs text-serena-text-muted">
                                {{ payment.notes }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-rose-300 bg-white px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    @click="confirmVoidPayment(payment)"
                                    v-if="canVoidPayments && !payment.voided_at && !payment.deleted_at && payment.entry_type !== 'refund'"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-serena-border bg-white px-3 py-1 text-xs font-semibold text-serena-text-main hover:bg-serena-bg-soft"
                                    @click="promptRefundPayment(payment)"
                                    v-if="canRefundPayments && !payment.voided_at && !payment.deleted_at && payment.entry_type !== 'refund'"
                                >
                                    Rembourser
                                </button>
                            </div>
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
                    <div v-if="!canCreatePayments" class="rounded-xl border border-dashed border-serena-border/50 bg-white/50 p-4 text-sm text-serena-text-muted">
                        Vous n’avez pas l’autorisation d’enregistrer un paiement.
                    </div>
                    <form v-else class="space-y-4" @submit.prevent="submitPayment">
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
import axios from 'axios';
import Swal from 'sweetalert2';

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
        permissions: {
            type: Object,
            default: () => ({
                can_void_payments: false,
                can_refund_payments: false,
            }),
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
    computed: {
        canVoidPayments() {
            return Boolean(this.permissions?.can_void_payments ?? false);
        },
        canRefundPayments() {
            return Boolean(this.permissions?.can_refund_payments ?? false);
        },
        canCreatePayments() {
            const permissions = this.$page?.props?.auth?.can ?? {};

            return Boolean(permissions.payments_create ?? false);
        },
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
        reloadFolio() {
            this.$inertia.reload({
                preserveScroll: true,
                only: ['folio', 'reservation', 'items', 'payments', 'invoices', 'paymentMethods', 'permissions'],
            });
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits nécessaires.',
            });
        },
        async confirmVoidPayment(payment) {
            if (!this.canVoidPayments) {
                this.showUnauthorizedAlert();

                return;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Annuler le paiement',
                html: `
                    <textarea id="swal-void-reason" class="swal2-textarea" placeholder="Raison (facultatif)" rows="3"></textarea>
                `,
                showCancelButton: true,
                confirmButtonText: 'Annuler',
                cancelButtonText: 'Retour',
                focusConfirm: false,
                preConfirm: () => document.getElementById('swal-void-reason')?.value?.trim() ?? null,
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                await axios.post(`/payments/${payment.id}/void`, {
                    reason: result.value,
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Paiement annulé',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.reloadFolio();
            } catch (error) {
                this.handleAdjustmentError(error, 'Impossible d’annuler ce paiement.');
            }
        },
        async promptRefundPayment(payment) {
            if (!this.canRefundPayments) {
                this.showUnauthorizedAlert();

                return;
            }

            if (!this.paymentMethods.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'Pas de moyens définis',
                    text: 'Ajoutez au moins une méthode de paiement pour effectuer un remboursement.',
                });

                return;
            }

            const amount = Math.abs(payment.amount ?? 0) || 0;
            const methodOptions = this.paymentMethods.map((method) => `<option value="${method.id}">${method.name} (${method.code})</option>`).join('');
            const html = `
                <div class="space-y-3 text-left">
                    <label class="text-xs font-semibold text-serena-text-muted">Montant</label>
                    <input id="swal-refund-amount" class="swal2-input" type="number" min="0.01" step="0.01" value="${amount.toFixed(2)}">
                    <label class="text-xs font-semibold text-serena-text-muted">Méthode</label>
                    <select id="swal-refund-method" class="swal2-select">
                        ${methodOptions}
                    </select>
                    <label class="text-xs font-semibold text-serena-text-muted">Référence</label>
                    <input id="swal-refund-reference" class="swal2-input" type="text">
                    <label class="text-xs font-semibold text-serena-text-muted">Motif</label>
                    <textarea id="swal-refund-reason" class="swal2-textarea" rows="3" placeholder="Raison (facultatif)"></textarea>
                </div>
            `;

            const result = await Swal.fire({
                icon: 'info',
                title: 'Rembourser le paiement',
                html,
                showCancelButton: true,
                confirmButtonText: 'Rembourser',
                cancelButtonText: 'Annuler',
                focusConfirm: false,
                preConfirm: () => {
                    const amountInput = Number(document.getElementById('swal-refund-amount')?.value || 0);
                    const methodId = document.getElementById('swal-refund-method')?.value;
                    const reference = document.getElementById('swal-refund-reference')?.value?.trim() || null;
                    const reason = document.getElementById('swal-refund-reason')?.value?.trim() || null;

                    if (!amountInput || amountInput <= 0) {
                        Swal.showValidationMessage('Le montant doit être supérieur à 0.');

                        return null;
                    }

                    if (amountInput > amount) {
                        Swal.showValidationMessage('Le montant ne peut dépasser le paiement original.');

                        return null;
                    }

                    if (!methodId) {
                        Swal.showValidationMessage('Sélectionnez une méthode de remboursement.');

                        return null;
                    }

                    return {
                        amount: amountInput,
                        payment_method_id: Number(methodId),
                        reference,
                        reason,
                    };
                },
            });

            if (!result.value) {
                return;
            }

            try {
                await axios.post(`/payments/${payment.id}/refund`, {
                    amount: result.value.amount,
                    payment_method_id: result.value.payment_method_id,
                    reference: result.value.reference,
                    reason: result.value.reason,
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Remboursement enregistré',
                    timer: 1500,
                    showConfirmButton: false,
                });

                this.reloadFolio();
            } catch (error) {
                this.handleAdjustmentError(error, 'Impossible de rembourser ce paiement.');
            }
        },
        handleAdjustmentError(error, fallback) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.response?.data?.message ?? fallback,
            });
        },
    },
};
</script>
