<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="canViewPos" class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase text-serena-primary">Bar / POS</p>
                    <h1 class="text-2xl font-bold text-serena-text-main">Encaissement rapide</h1>
                    <p class="text-sm text-serena-text-muted">
                        Vendez au comptoir ou portez les consommations sur une chambre en quelques clics.
                    </p>
                </div>
                <div class="flex rounded-full bg-white p-1 shadow-sm">
                    <CashIndicator type="bar" class="mr-4" />
                    <button
                        v-for="tab in modes"
                        :key="tab.value"
                        type="button"
                        class="flex-1 rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="mode === tab.value ? 'bg-serena-primary text-white shadow' : 'text-serena-text-muted'"
                        @click="mode = tab.value"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row">
                <aside class="rounded-2xl bg-white p-4 shadow-sm lg:w-64">
                    <p class="mb-3 text-sm font-semibold text-serena-text-muted">Catégories</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-sm transition"
                            :class="selectedCategoryId === 'all' ? 'bg-serena-primary text-white' : 'bg-serena-primary-soft text-serena-primary'"
                            @click="selectedCategoryId = 'all'"
                        >
                            Tous
                        </button>
                        <button
                            v-for="category in categories"
                            :key="category.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-sm transition"
                            :class="selectedCategoryId === category.id ? 'bg-serena-primary text-white' : 'bg-serena-primary-soft text-serena-primary'"
                            @click="selectedCategoryId = category.id"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </aside>

                <section class="flex-1 rounded-2xl bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-semibold text-serena-text-muted">
                            {{ filteredProducts.length }} produit(s)
                        </p>
                        <button
                            type="button"
                            class="text-sm text-serena-primary underline decoration-dotted"
                            @click="refreshProducts"
                        >
                            Rafraîchir
                        </button>
                    </div>
                    <div
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                        v-if="filteredProducts.length"
                    >
                        <article
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="flex flex-col rounded-2xl border border-serena-border/60 p-3"
                        >
                            <div class="flex-1 space-y-1">
                                <p class="text-base font-semibold text-serena-text-main">{{ product.name }}</p>
                                <p class="text-sm text-serena-text-muted">
                                    {{ product.category_name || 'Sans catégorie' }}
                                </p>
                            </div>
                            <p class="mt-4 text-lg font-bold text-serena-primary">
                                {{ formatCurrency(product.unit_price) }}
                            </p>
                            <button
                                v-if="canCreatePos"
                                type="button"
                                class="mt-3 rounded-xl bg-serena-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-serena-primary-dark"
                                @click="addToCart(product)"
                            >
                                Ajouter
                            </button>
                            <span v-else class="mt-3 text-xs text-serena-text-muted">
                                Ajout non autorisé
                            </span>
                        </article>
                    </div>
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-serena-border p-8 text-center text-sm text-serena-text-muted"
                    >
                        Aucun produit actif dans cette catégorie.
                    </div>
                </section>

                <section
                    class="rounded-2xl bg-white p-4 shadow-lg lg:w-96"
                    :class="{ 'order-first': isMobile }"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-serena-text-main">Panier</h2>
                        <button
                            type="button"
                            class="text-sm text-serena-danger underline decoration-dotted disabled:opacity-30"
                            :disabled="!cart.length || !canCreatePos"
                            @click="clearCart"
                        >
                            Vider
                        </button>
                    </div>

                    <div v-if="cart.length" class="mt-4 space-y-3">
                        <div
                            v-for="line in cart"
                            :key="line.product_id"
                            class="rounded-2xl border border-serena-border/60 p-3"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-serena-text-main">{{ line.name }}</p>
                                    <p class="text-xs text-serena-text-muted">
                                        {{ formatCurrency(line.unit_price) }} · TVA {{ line.tax_rate }}%
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-xs text-serena-danger"
                                    :disabled="!canCreatePos"
                                    @click="removeFromCart(line.product_id)"
                                >
                                    Retirer
                                </button>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex items-center gap-2 rounded-full bg-serena-bg-soft px-3 py-1">
                                    <button
                                        type="button"
                                        class="text-lg font-bold text-serena-text-muted"
                                        :disabled="!canCreatePos"
                                        @click="decrement(line.product_id)"
                                    >
                                        −
                                    </button>
                                    <span class="text-sm font-semibold">{{ line.quantity }}</span>
                                    <button
                                        type="button"
                                        class="text-lg font-bold text-serena-text-muted"
                                        :disabled="!canCreatePos"
                                        @click="increment(line.product_id)"
                                    >
                                        +
                                    </button>
                                </div>
                                <p class="text-base font-semibold text-serena-text-main">
                                    {{ formatCurrency(line.total_amount) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-4 rounded-2xl border border-dashed border-serena-border p-6 text-center text-sm text-serena-text-muted"
                    >
                        Aucun article sélectionné.
                    </div>

                    <div class="mt-6 space-y-2 rounded-2xl bg-serena-bg-soft p-4">
                        <div class="flex items-center justify-between text-sm text-serena-text-muted">
                            <span>Sous-total</span>
                            <span>{{ formatCurrency(cartSubtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-serena-text-muted">
                            <span>Taxes</span>
                            <span>{{ formatCurrency(cartTaxes) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-lg font-bold text-serena-text-main">
                            <span>Total</span>
                            <span>{{ formatCurrency(cartTotal) }}</span>
                        </div>
                    </div>

                    <div v-if="mode === 'counter'" class="mt-4 space-y-3">
                        <label class="block text-sm font-semibold text-serena-text-main">
                            Méthode de paiement
                            <select
                                v-model="selectedPaymentMethodId"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                            >
                                <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                    {{ method.name }}
                                </option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-serena-text-main">
                            Libellé client (optionnel)
                            <input
                                v-model="clientLabel"
                                type="text"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                                placeholder="Ex: Client bar, Extérieur..."
                            />
                        </label>
                        <button
                            type="button"
                            class="w-full rounded-2xl bg-serena-primary px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:cursor-not-allowed disabled:bg-serena-primary/60"
                            :disabled="!canSubmitCounter || submitting"
                            @click="submitCounterSale"
                        >
                            {{ submitting ? 'Encaissement...' : 'Encaisser' }}
                        </button>
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <label class="block text-sm font-semibold text-serena-text-main">
                            Réservation (en séjour)
                            <select
                                v-model="selectedReservationId"
                                class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                            >
                                <option v-for="reservation in inHouseReservations" :key="reservation.id" :value="reservation.id">
                                    {{ reservation.code }} · {{ reservation.room_number || '—' }} · {{ reservation.guest_name }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="w-full rounded-2xl bg-serena-primary px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-serena-primary-dark disabled:cursor-not-allowed disabled:bg-serena-primary/60"
                            :disabled="!canSubmitRoom || submitting"
                            @click="submitRoomSale"
                        >
                            {{ submitting ? 'Imputation...' : 'Imputer à la chambre' }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
        <div
            v-else
            class="rounded-2xl border border-dashed border-serena-border bg-white p-8 text-center text-sm text-serena-text-muted"
        >
            Accès POS non autorisé.
        </div>
    </AppLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/layouts/AppLayout.vue';
import CashIndicator from '@/Components/CashIndicator.vue';

export default {
    name: 'PosIndexPage',
    components: { AppLayout, CashIndicator },
    props: {
        categories: {
            type: Array,
            default: () => [],
        },
        products: {
            type: Array,
            default: () => [],
        },
        paymentMethods: {
            type: Array,
            default: () => [],
        },
        inHouseReservations: {
            type: Array,
            default: () => [],
        },
        currency: {
            type: String,
            default: 'XAF',
        },
    },
    data() {
        return {
            mode: 'counter',
            modes: [
                { label: 'Vente comptoir', value: 'counter' },
                { label: 'Imputer à une chambre', value: 'room' },
            ],
            selectedCategoryId: 'all',
            cart: [],
            selectedPaymentMethodId: null,
            selectedReservationId: null,
            submitting: false,
            clientLabel: '',
        };
    },
    computed: {
        breadcrumbs() {
            return [
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Bar / POS' },
            ];
        },
        permissionFlags() {
            return this.$page?.props?.auth?.can ?? {};
        },
        canViewPos() {
            return this.permissionFlags.pos_view ?? true;
        },
        canCreatePos() {
            return this.permissionFlags.pos_create ?? false;
        },
        filteredProducts() {
            if (this.selectedCategoryId === 'all') {
                return this.products;
            }
            return this.products.filter((product) => product.category_id === this.selectedCategoryId);
        },
        cartSubtotal() {
            return this.cart.reduce((sum, line) => sum + line.quantity * line.unit_price, 0);
        },
        cartTaxes() {
            return this.cart.reduce((sum, line) => sum + line.tax_amount, 0);
        },
        cartTotal() {
            return this.cart.reduce((sum, line) => sum + line.total_amount, 0);
        },
        canSubmitCounter() {
            return this.canCreatePos && this.cart.length > 0 && !!this.selectedPaymentMethodId && !this.submitting;
        },
        canSubmitRoom() {
            return (
                this.canCreatePos &&
                this.cart.length > 0 &&
                !!this.selectedReservationId &&
                !this.submitting &&
                this.inHouseReservations.length > 0
            );
        },
        isMobile() {
            if (typeof window === 'undefined') {
                return false;
            }

            return window.innerWidth < 1024;
        },
    },
    created() {
        this.selectedPaymentMethodId = this.paymentMethods[0]?.id ?? null;
        this.selectedReservationId = this.inHouseReservations[0]?.id ?? null;
    },
    methods: {
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
        refreshProducts() {
            this.$inertia.reload({ only: ['products', 'categories'] });
        },
        addToCart(product) {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            const existing = this.cart.find((line) => line.product_id === product.id);

            if (existing) {
                existing.quantity += 1;
                this.updateLineAmounts(existing);
                return;
            }

            const line = {
                product_id: product.id,
                name: product.name,
                quantity: 1,
                unit_price: product.unit_price,
                tax_rate: product.tax_rate || 0,
                tax_amount: 0,
                total_amount: 0,
            };

            this.updateLineAmounts(line);
            this.cart.push(line);
        },
        increment(productId) {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            const line = this.cart.find((item) => item.product_id === productId);
            if (!line) {
                return;
            }
            line.quantity += 1;
            this.updateLineAmounts(line);
        },
        decrement(productId) {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            const line = this.cart.find((item) => item.product_id === productId);
            if (!line) {
                return;
            }
            line.quantity -= 1;

            if (line.quantity <= 0) {
                this.cart = this.cart.filter((item) => item.product_id !== productId);
                return;
            }

            this.updateLineAmounts(line);
        },
        removeFromCart(productId) {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            this.cart = this.cart.filter((item) => item.product_id !== productId);
        },
        clearCart() {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            this.cart = [];
            this.clientLabel = '';
        },
        updateLineAmounts(line) {
            const baseAmount = this.round(line.quantity * line.unit_price);
            const taxAmount = this.round(baseAmount * ((line.tax_rate ?? 0) / 100));

            line.tax_amount = taxAmount;
            line.total_amount = this.round(baseAmount + taxAmount);
        },
        round(value) {
            return Math.round(value * 100) / 100;
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: this.currency || 'XAF',
                minimumFractionDigits: 0,
            }).format(amount);
        },
        payloadItems() {
            return this.cart.map((line) => ({
                product_id: line.product_id,
                name: line.name,
                quantity: line.quantity,
                unit_price: line.unit_price,
                tax_amount: line.tax_amount,
                total_amount: line.total_amount,
            }));
        },
        async submitCounterSale() {
            if (!this.canSubmitCounter) {
                if (!this.canCreatePos) {
                    this.showUnauthorizedAlert();
                }

                return;
            }
            this.submitting = true;
            try {
                const payload = {
                    items: this.payloadItems(),
                    payment_method_id: Number(this.selectedPaymentMethodId),
                    client_label: this.clientLabel?.trim() || null,
                };
                const response = await axios.post('/pos/sales/counter', payload);

                this.cart = [];
                this.clientLabel = '';

                Swal.fire({
                    icon: 'success',
                    title: 'Vente enregistrée',
                    text: `Ticket #${response.data.folio_id} - ${this.formatCurrency(response.data.total)}`,
                    timer: 3500,
                    showConfirmButton: false,
                });
            } catch (error) {
                this.handleError(error);
            } finally {
                this.submitting = false;
            }
        },
        async submitRoomSale() {
            if (!this.canSubmitRoom) {
                if (!this.canCreatePos) {
                    this.showUnauthorizedAlert();
                }

                return;
            }
            this.submitting = true;
            try {
                const payload = {
                    reservation_id: Number(this.selectedReservationId),
                    items: this.payloadItems(),
                };
                const response = await axios.post('/pos/sales/room', payload);

                const reservation = this.inHouseReservations.find((item) => item.id === this.selectedReservationId);
                this.cart = [];

                Swal.fire({
                    icon: 'success',
                    title: 'Montant imputé',
                    text: `Ajouté sur le folio ${response.data.folio_id} (${reservation?.room_number || 'Chambre'})`,
                    timer: 4000,
                    showConfirmButton: false,
                });
            } catch (error) {
                this.handleError(error);
            } finally {
                this.submitting = false;
            }
        },
        handleError(error) {
            if (error?.response?.status === 403) {
                this.showUnauthorizedAlert();

                return;
            }

            let message = 'Une erreur est survenue. Merci de réessayer.';

            if (error?.response?.data?.message) {
                message = error.response.data.message;
            } else if (error?.response?.data?.errors) {
                const firstError = Object.values(error.response.data.errors)[0];
                if (Array.isArray(firstError)) {
                    message = firstError[0];
                }
            }

            Swal.fire({
                icon: 'error',
                title: 'Oops',
                text: message,
            });
        },
    },
};
</script>
