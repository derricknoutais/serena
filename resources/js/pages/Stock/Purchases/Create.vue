<template>
    <AppLayout title="Nouvelle réception">
        <section class="space-y-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Nouvelle réception</h1>
                    <p class="text-sm text-serena-text-muted">
                        Créez un bon d’achat en ajoutant les lignes comme dans une facture.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/stock"
                        class="text-xs font-semibold uppercase tracking-wide text-serena-primary hover:underline"
                    >
                        ← Retour au tableau de bord
                    </Link>
                </div>
            </div>

            <form @submit.prevent="submitPurchase" class="space-y-6 rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold text-serena-text-muted">
                        Emplacement
                        <Multiselect
                            v-model="selectedStorageLocation"
                            :options="storageLocations"
                            track-by="id"
                            label="name"
                            placeholder="Sélectionner"
                            class="mt-1 w-full"
                            :allow-empty="true"
                        />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-muted">
                        Référence
                        <input v-model="form.reference_no" type="text" placeholder="Référence" class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-muted">
                        Fournisseur
                        <input v-model="form.supplier_name" type="text" placeholder="Fournisseur" class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-muted">
                        Date d’achat
                        <input v-model="form.purchased_at" type="date" class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-semibold text-serena-text-muted">
                        Devise
                        <input v-model="form.currency" type="text" placeholder="XAF" maxlength="3" class="mt-1 w-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                    </label>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-serena-text-main">Lignes</h2>
                        <button
                            type="button"
                            class="rounded-full bg-serena-primary px-3 py-1 text-xs font-semibold text-white"
                            @click="addLine"
                        >
                            + Ajouter une ligne
                        </button>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border border-serena-border bg-serena-card" style="overflow: visible;">
                        <table class="min-w-full text-sm">
                            <thead class="bg-serena-bg-soft/70 text-[11px] font-semibold uppercase text-serena-text-muted">
                                <tr>
                                    <th class="px-3 py-2 text-left">Article</th>
                                    <th class="px-3 py-2 text-right">Quantité</th>
                                    <th class="px-3 py-2 text-right">Prix uni.</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                    <th class="px-3 py-2 text-left">Notes</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, index) in form.lines" :key="index" class="border-t border-serena-border bg-white">
                                    <td class="px-3 py-2">
                                        <Multiselect
                                            :options="stockItems"
                                            track-by="id"
                                            label="name"
                                            :model-value="stockItems.find((item) => String(item.id) === String(line.stock_item_id)) ?? null"
                                            @update:modelValue="(value) => onStockItemSelected(line, value)"
                                            placeholder="Sélectionner"
                                            class="w-full"
                                            :allow-empty="true"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <input v-model.number="line.quantity" type="number" min="0" step="0.01" class="w-20 rounded-xl border border-serena-border bg-white px-2 py-1 text-right text-sm" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="w-24 rounded-xl border border-serena-border bg-white px-2 py-1 text-right text-sm" />
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold">
                                        {{ formatAmount(lineTotal(line), form.currency || 'XAF') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model="line.notes" type="text" placeholder="Notes" class="w-full rounded-xl border border-serena-border bg-white px-2 py-1 text-sm" />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-rose-600 hover:text-rose-800"
                                            @click="removeLine(index)"
                                            :disabled="form.lines.length === 1"
                                        >
                                            Supprimer
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-2 lg:items-end">
                    <div class="flex items-center gap-3 text-sm text-serena-text-muted">
                        <span>Total</span>
                        <span class="text-lg font-semibold text-serena-text-main">
                            {{ formatAmount(totalAmount, form.currency || 'XAF') }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-serena-border px-4 py-2 text-xs font-semibold"
                            @click="$inertia.visit('/stock')"
                        >
                            Annuler
                        </button>
                        <PrimaryButton
                            type="submit"
                            :class="['px-4 py-2 text-xs font-semibold', { 'opacity-60': purchaseSubmitting }]"
                            :disabled="purchaseSubmitting"
                        >
                            {{ purchaseSubmitting ? 'Enregistrement…' : 'Enregistrer' }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'StockPurchaseCreate',
    components: {
        AppLayout,
        Link,
        Multiselect,
        PrimaryButton,
    },
    props: {
        storageLocations: {
            type: Array,
            default: () => [],
        },
        stockItems: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            form: this.createForm(),
            purchaseSubmitting: false,
        };
    },
    computed: {
        totalAmount() {
            return this.form.lines.reduce(
                (total, line) => total + this.lineTotal(line),
                0,
            );
        },
        selectedStorageLocation: {
            get() {
                return this.storageLocations.find((location) => location.id === this.form.storage_location_id) ?? null;
            },
            set(value) {
                this.form.storage_location_id = value?.id ?? '';
            },
        },
    },
    watch: {
        storageLocations: {
            handler(locations) {
                if (!this.form.storage_location_id && locations.length) {
                    this.form.storage_location_id = locations[0].id;
                }
            },
            immediate: true,
        },
        stockItems: {
            handler(items) {
                if (!this.form.lines?.length && items.length) {
                    this.form.lines = [this.emptyLine()];
                }
            },
            immediate: true,
        },
    },
    methods: {
        createForm() {
            return {
                storage_location_id: '',
                reference_no: '',
                supplier_name: '',
                purchased_at: '',
                currency: 'XAF',
                lines: [this.emptyLine()],
            };
        },
        emptyLine() {
            return {
                stock_item_id: '',
                quantity: 1,
                unit_cost: 0,
                currency: 'XAF',
                notes: '',
            };
        },
        lineTotal(line) {
            const qty = Number(line.quantity ?? 0);
            const cost = Number(line.unit_cost ?? 0);
            return qty * cost;
        },
        onStockItemSelected(line, value) {
            line.stock_item_id = value?.id ?? '';
            line.unit_cost = value?.default_purchase_price ?? 0;
        },
        addLine() {
            this.form.lines.push(this.emptyLine());
        },
        removeLine(index) {
            if (this.form.lines.length === 1) {
                return;
            }

            this.form.lines.splice(index, 1);
        },
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        async submitPurchase() {
            if (this.purchaseSubmitting) {
                return;
            }

            this.purchaseSubmitting = true;

            try {
                await axios.post('/stock/purchases', this.form);

                Swal.fire({
                    icon: 'success',
                    title: 'Réception enregistrée',
                    timer: 1400,
                    showConfirmButton: false,
                });
                this.$inertia.visit('/stock');
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Impossible de créer la réception.',
                });
            } finally {
                this.purchaseSubmitting = false;
            }
        },
    },
};
</script>
