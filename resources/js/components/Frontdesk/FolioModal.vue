<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Folio {{ folio?.code || '' }}
                    </h2>
                    <p v-if="reservation" class="text-xs text-gray-500">
                        Réservation {{ reservation.code }}
                        <span v-if="reservation.guest"> · {{ reservation.guest.name }}</span>
                    </p>
                    <p v-if="reservation" class="text-xs text-gray-500">
                        Séjour : {{ reservation.check_in_date }} → {{ reservation.check_out_date }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-2 text-xs">
                    <div v-if="reservation">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold"
                            :class="reservationStatusClass(reservation.status)"
                        >
                            {{ reservation.status_label }}
                        </span>
                    </div>
                    <div v-if="folio" class="text-right">
                        <div class="text-[11px] text-gray-500">Total charges</div>
                        <div class="font-semibold text-gray-800">
                            {{ formatMoney(folio.charges_total, folio.currency) }}
                        </div>
                        <div class="text-[11px] text-gray-500">Total paiements</div>
                        <div class="font-semibold text-gray-800">
                            {{ formatMoney(folio.payments_total, folio.currency) }}
                        </div>
                        <div class="mt-1 text-[11px] font-semibold" :class="balanceClass">
                            Solde :
                            <span>{{ formatMoney(folio.balance, folio.currency) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3 flex gap-2 border-b border-gray-200 text-xs font-medium">
                <button
                    type="button"
                    class="border-b-2 px-3 py-1.5"
                    :class="activeTab === 'charges' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                    @click="activeTab = 'charges'"
                >
                    Charges
                </button>
                <button
                    type="button"
                    class="border-b-2 px-3 py-1.5"
                    :class="activeTab === 'payments' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                    @click="activeTab = 'payments'"
                >
                    Paiements
                </button>
                <button
                    type="button"
                    class="border-b-2 px-3 py-1.5"
                    :class="activeTab === 'invoices' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500'"
                    @click="activeTab = 'invoices'"
                >
                    Facture
                </button>
            </div>

            <div class="space-y-4">
                <div v-if="activeTab === 'charges'" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">Charges du folio</h3>
                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                            @click="showAddCharge = true"
                        >
                            Ajouter une charge
                        </button>
                    </div>

                    <div v-if="items.length === 0" class="text-xs text-gray-500">
                        Aucune charge pour l’instant.
                    </div>

                    <div v-else class="max-h-52 overflow-y-auto rounded-lg border border-gray-100">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-right">Qté</th>
                                    <th class="px-3 py-2 text-right">PU</th>
                                    <th class="px-3 py-2 text-right">Remise</th>
                                    <th class="px-3 py-2 text-right">Taxe</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in items"
                                    :key="item.id"
                                    :class="['border-t', item.deleted_at ? 'bg-gray-50 text-gray-400' : 'text-gray-700']"
                                >
                                    <td class="px-3 py-1.5">
                                        <div class="flex items-center gap-2">
                                            <span :class="item.deleted_at ? 'line-through' : ''">
                                                {{ item.description }}
                                            </span>
                                            <span
                                                v-if="item.deleted_at"
                                                class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold text-gray-600"
                                            >
                                                Annulé
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <span :class="item.deleted_at ? 'line-through' : ''">
                                            {{ item.quantity }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <span :class="item.deleted_at ? 'line-through' : ''">
                                            {{ formatMoney(item.unit_price, folio?.currency) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <span
                                            v-if="item.discount_amount > 0"
                                            :class="item.deleted_at ? 'line-through' : ''"
                                        >
                                            -{{ formatMoney(item.discount_amount, folio?.currency) }}
                                        </span>
                                        <span
                                            v-else-if="item.discount_percent > 0"
                                            :class="item.deleted_at ? 'line-through' : ''"
                                        >
                                            -{{ item.discount_percent.toFixed(2) }} %
                                        </span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <span :class="item.deleted_at ? 'line-through' : ''">
                                            {{ formatMoney(item.tax_amount, folio?.currency) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-semibold">
                                        <span :class="item.deleted_at ? 'line-through' : ''">
                                            {{ formatMoney(item.total_amount, folio?.currency) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="showAddCharge"
                        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                        @click.self="closeAddCharge"
                    >
                        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                            <h3 class="mb-3 text-sm font-semibold text-gray-800">Ajouter une charge</h3>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <label class="text-xs font-medium text-gray-700">Description</label>
                                    <input
                                        v-model="chargeForm.description"
                                        type="text"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-700">Qté</label>
                                        <input
                                            v-model.number="chargeForm.quantity"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-700">PU</label>
                                        <input
                                            v-model.number="chargeForm.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-700">Taxe</label>
                                        <input
                                            v-model.number="chargeForm.tax_amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
                                        />
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-700">Remise (%)</label>
                                        <input
                                            v-model.number="chargeForm.discount_percent"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
                                        />
                                        <p class="mt-1 text-[10px] text-gray-400">
                                            Laissez à 0 si vous utilisez un montant fixe.
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-700">Remise (montant)</label>
                                        <input
                                            v-model.number="chargeForm.discount_amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm"
                                        />
                                        <p class="mt-1 text-[10px] text-gray-400">
                                            Prioritaire sur le % si renseigné.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-800"
                                    @click="closeAddCharge"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    @click="submitCharge"
                                    :disabled="isSubmitting"
                                >
                                    Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'payments'" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500">Total folio</p>
                            <p class="text-lg font-semibold text-gray-900">
                                {{ formatMoney(folio?.charges_total, folio?.currency) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500">Total payé</p>
                            <p class="text-lg font-semibold text-gray-900">
                                {{ formatMoney(folio?.payments_total, folio?.currency) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500">Solde</p>
                            <p class="text-lg font-semibold" :class="balanceClass">
                                {{ formatMoney(folio?.balance, folio?.currency) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800">Liste des paiements</h3>
                            <span class="text-xs text-gray-400">{{ payments.length }} paiement(s)</span>
                        </div>
                        <div v-if="payments.length === 0" class="rounded-lg border border-dashed border-gray-200 p-4 text-xs text-gray-500">
                            Aucun paiement enregistré pour le moment.
                        </div>
                        <div v-else class="max-h-60 overflow-y-auto rounded-lg border border-gray-100">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Méthode</th>
                                        <th class="px-3 py-2 text-right">Montant</th>
                                    <th class="px-3 py-2 text-left">Note</th>
                                    <th class="px-3 py-2 text-right" v-if="canVoidPayments">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="payment in payments"
                                        :key="payment.id"
                                        class="border-t text-gray-700"
                                    >
                                        <td class="px-3 py-1.5">{{ formatDateTime(payment.paid_at) }}</td>
                                        <td class="px-3 py-1.5">{{ payment.payment_method?.name || payment.method?.name || '—' }}</td>
                                        <td class="px-3 py-1.5 text-right font-semibold">
                                            {{ formatMoney(payment.amount, payment.currency) }}
                                        </td>
                                        <td class="px-3 py-1.5">{{ payment.notes || '—' }}</td>
                                        <td
                                            v-if="canVoidPayments"
                                            class="px-3 py-1.5 text-right"
                                        >
                                            <button
                                                type="button"
                                                class="text-xs text-red-500 hover:text-red-600"
                                                @click="deletePayment(payment.id)"
                                            >
                                                Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800">Enregistrer un paiement</h3>
                        <form class="mt-3 grid gap-3 md:grid-cols-2" @submit.prevent="submitPayment">
                            <div>
                                <label class="text-xs font-medium text-gray-700">Montant</label>
                                <input
                                    v-model.number="paymentForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">Méthode de paiement</label>
                                <select
                                    v-model="paymentForm.payment_method_id"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm"
                                >
                                    <option value="">Sélectionner</option>
                                    <option
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        :value="method.id"
                                    >
                                        {{ method.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-medium text-gray-700">Note</label>
                                <textarea
                                    v-model="paymentForm.note"
                                    rows="2"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm"
                                ></textarea>
                            </div>
                            <div class="md:col-span-2 flex items-center justify-end">
                                <button
                                    type="submit"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    :disabled="isSubmitting"
                                >
                                    Enregistrer le paiement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="activeTab === 'invoices'" class="space-y-4">
                    <div v-if="invoices.length === 0" class="rounded-xl border border-dashed border-gray-200 p-5 text-center text-sm text-gray-600">
                        <p class="mb-3">Aucune facture générée.</p>
                        <button
                            type="button"
                            class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                            @click="confirmGenerateInvoice"
                            :disabled="!folio || isSubmitting || !canManageInvoices"
                        >
                            Générer la facture
                        </button>
                    </div>

                    <div v-else class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800">Facture émise</h3>
                        <div class="mt-2 grid gap-4 md:grid-cols-3">
                            <div>
                                <p class="text-[11px] uppercase text-gray-500">Numéro</p>
                                <p class="text-base font-semibold text-gray-900">{{ invoices[0].number }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase text-gray-500">Montant</p>
                                <p class="text-base font-semibold text-gray-900">
                                    {{ formatMoney(invoices[0].total_amount, invoices[0].currency) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase text-gray-500">Statut</p>
                                <p class="text-sm font-semibold text-emerald-600">{{ invoices[0].status || 'émise' }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                @click="downloadInvoice(invoices[0])"
                            >
                                Télécharger le PDF
                            </button>
                            <button
                                v-if="canManageInvoices"
                                type="button"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                                @click="confirmGenerateInvoice"
                                :disabled="isSubmitting"
                            >
                                Regénérer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="text-xs text-gray-600 hover:text-gray-800"
                    @click="$emit('close')"
                >
                    Fermer
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    name: 'FolioModal',
    props: {
        show: { type: Boolean, default: false },
        folio: { type: Object, default: null },
        reservation: { type: Object, default: null },
        items: { type: Array, default: () => [] },
        payments: { type: Array, default: () => [] },
        invoices: { type: Array, default: () => [] },
        paymentMethods: { type: Array, default: () => [] },
        initialTab: { type: String, default: 'charges' },
        permissions: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            activeTab: this.initialTab || 'charges',
            showAddCharge: false,
            isSubmitting: false,
            chargeForm: this.defaultChargeForm(),
            paymentForm: this.defaultPaymentForm(),
        };
    },
    computed: {
        balanceClass() {
            if (!this.folio) {
                return 'text-gray-600';
            }

            if (this.folio.balance > 0) {
                return 'text-amber-600';
            }

            if (this.folio.balance < 0) {
                return 'text-red-600';
            }

            return 'text-green-600';
        },
        permissionFlags() {
            return this.$page?.props?.auth?.can ?? {};
        },
        canVoidPayments() {
            return this.permissionFlags.folio_items_void
                ?? (this.permissions?.can_manage_payments ?? false);
        },
        canManageInvoices() {
            return this.permissionFlags.invoices_create
                ?? (this.permissions?.can_manage_invoices ?? false);
        },
        canViewInvoices() {
            if (this.permissionFlags.invoices_view !== undefined) {
                return this.permissionFlags.invoices_view;
            }

            return this.canManageInvoices;
        },
    },
    methods: {
        reservationStatusClass(status) {
            const map = {
                pending: 'bg-yellow-50 text-yellow-700',
                confirmed: 'bg-blue-50 text-blue-700',
                in_house: 'bg-green-50 text-green-700',
                checked_out: 'bg-gray-100 text-gray-700',
                cancelled: 'bg-red-50 text-red-700',
                no_show: 'bg-orange-50 text-orange-700',
            };

            return map[status] || 'bg-gray-100 text-gray-700';
        },
        formatMoney(value, currency) {
            const amount = Number(value || 0).toFixed(2);

            return `${amount} ${currency || ''}`.trim();
        },
        formatDateTime(value) {
            if (!value) {
                return '—';
            }

            return value;
        },
        defaultChargeForm() {
            return {
                description: '',
                quantity: 1,
                unit_price: 0,
                tax_amount: 0,
                discount_percent: 0,
                discount_amount: 0,
            };
        },
        defaultPaymentForm() {
            const balance = this.folio ? Number(this.folio.balance) : 0;

            const formatted = balance > 0 ? Number(balance.toFixed(2)) : 0;

            return {
                amount: formatted,
                payment_method_id: '',
                note: '',
            };
        },
        closeAddCharge() {
            this.showAddCharge = false;
            this.chargeForm = this.defaultChargeForm();
        },
        async submitCharge() {
            if (!this.folio) {
                return;
            }

            this.isSubmitting = true;

            try {
                const http = window.axios ?? axios;
                await http.post(`/folios/${this.folio.id}/items`, this.chargeForm);
                this.closeAddCharge();
                this.$emit('updated');
            } finally {
                this.isSubmitting = false;
            }
        },
        async submitPayment() {
            if (!this.folio) {
                return;
            }

            if (!this.paymentForm.payment_method_id || Number(this.paymentForm.amount) <= 0) {
                return;
            }

            this.isSubmitting = true;

            try {
                const http = window.axios ?? axios;
                await http.post(`/folios/${this.folio.id}/payments`, {
                    amount: this.paymentForm.amount,
                    payment_method_id: this.paymentForm.payment_method_id,
                    note: this.paymentForm.note,
                    currency: this.folio.currency,
                });
                this.paymentForm = this.defaultPaymentForm();
                this.$emit('updated');
                window.dispatchEvent(new CustomEvent('cash-session-updated', {
                    detail: { type: 'frontdesk' },
                }));
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response.data.message,
                });
            } finally {
                this.isSubmitting = false;
            }
        },
        async deletePayment(paymentId) {
            if (!this.folio || !this.canVoidPayments) {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });

                return;
            }

            const http = window.axios ?? axios;
            try {
                await http.delete(`/folios/${this.folio.id}/payments/${paymentId}`);
                this.$emit('updated');
            } catch (error) {
                if (error?.response?.status === 403) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Action non autorisée',
                        text: 'Vous ne disposez pas des droits suffisants.',
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de supprimer ce paiement.',
                    });
                }
            }
        },
        async confirmGenerateInvoice() {
            if (!this.folio) {
                return;
            }

            if (!this.canManageInvoices) {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });

                return;
            }

            if (!window.Swal) {
                await this.generateInvoice();

                return;
            }

            const result = await window.Swal.fire({
                title: 'Générer la facture',
                text: 'Voulez-vous générer une facture pour ce folio ?'
                    + '\nVous pourrez toujours la fermer plus tard.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Annuler',
            });

            if (result.isConfirmed) {
                await this.generateInvoice();
            }
        },
        async generateInvoice() {
            if (!this.folio) {
                return;
            }

            if (!this.canManageInvoices) {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });

                return;
            }

            this.isSubmitting = true;

            try {
                const http = window.axios ?? axios;
                await http.post(`/folios/${this.folio.id}/invoices`, {
                    close_folio: false,
                });
                this.$emit('updated');
            } catch (error) {
                if (error?.response?.status === 403) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Action non autorisée',
                        text: 'Vous ne disposez pas des droits suffisants.',
                    });
                }
            } finally {
                this.isSubmitting = false;
            }
        },
        downloadInvoice(invoice) {
            if (!invoice?.id) {
                return;
            }

            if (!this.canViewInvoices) {
                Swal.fire({
                    icon: 'error',
                    title: 'Action non autorisée',
                    text: 'Vous ne disposez pas des droits suffisants.',
                });

                return;
            }

            window.open(`/invoices/${invoice.id}/print`, '_blank');
        },
    },
    watch: {
        show(value) {
            if (value) {
                this.activeTab = this.initialTab || 'charges';
                this.chargeForm = this.defaultChargeForm();
                this.paymentForm = this.defaultPaymentForm();
            }
        },
        folio() {
            this.paymentForm = this.defaultPaymentForm();
        },
        initialTab(value) {
            if (this.show) {
                this.activeTab = value || 'charges';
            }
        },
    },
};
</script>
