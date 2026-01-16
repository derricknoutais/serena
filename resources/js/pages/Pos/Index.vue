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
                <div class="flex items-center rounded-full bg-white px-3 py-1 shadow-sm">
                    <CashIndicator type="bar" />
                </div>
            </div>

            <div class="flex flex-col gap-4 xl:flex-row">
                <aside class="rounded-2xl bg-white p-4 shadow-sm xl:w-72">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-serena-text-muted">Tables</p>
                        <button
                            type="button"
                            class="text-xs text-serena-primary underline decoration-dotted"
                            @click="loadTables"
                        >
                            Rafraîchir
                        </button>
                    </div>
                    <div class="mt-3 space-y-2">
                        <input
                            v-model="tableSearch"
                            type="text"
                            placeholder="Rechercher une table..."
                            class="w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        />
                        <select
                            v-model="tableArea"
                            class="w-full rounded-xl border border-serena-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-serena-primary/40"
                        >
                            <option value="all">Toutes les zones</option>
                            <option v-for="area in tableAreas" :key="area" :value="area">
                                {{ area }}
                            </option>
                        </select>
                    </div>
                    <div v-if="loadingTables" class="mt-4 text-xs text-serena-text-muted">Chargement...</div>
                    <div v-else class="mt-4 grid gap-2">
                        <button
                            v-for="table in filteredTables"
                            :key="table.id"
                            type="button"
                            class="rounded-2xl border px-3 py-2 text-left transition"
                            :class="tableButtonClass(table)"
                            @click="selectTable(table)"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold">{{ table.name }}</p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="table.open_order ? 'bg-serena-warning/10 text-serena-warning' : 'bg-serena-success/10 text-serena-success'"
                                >
                                    {{ table.open_order ? 'Ouverte' : 'Libre' }}
                                </span>
                            </div>
                            <p v-if="table.area" class="text-xs text-serena-text-muted">{{ table.area }}</p>
                            <p v-if="table.open_order?.cashier" class="text-[11px] text-serena-text-muted">
                                {{ table.open_order.cashier.name }}
                            </p>
                        </button>
                        <div v-if="!filteredTables.length" class="rounded-xl border border-dashed border-serena-border p-3 text-xs text-serena-text-muted">
                            Aucune table disponible.
                        </div>
                    </div>
                </aside>

                <section class="flex-1 space-y-4">
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase text-serena-text-muted">Commande en cours</p>
                                <p class="text-lg font-semibold text-serena-text-main">
                                    {{ currentTable ? currentTable.name : 'Sélectionnez une table' }}
                                </p>
                                <p v-if="currentTable?.area" class="text-xs text-serena-text-muted">
                                    {{ currentTable.area }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="currentOrder"
                                    class="rounded-full bg-serena-primary-soft px-3 py-1 text-xs font-semibold text-serena-primary"
                                >
                                    Commande #{{ currentOrder.id }}
                                </span>
                                <button
                                    v-if="canManageTables && currentOrder"
                                    type="button"
                                    class="rounded-full border border-serena-border px-3 py-1 text-xs font-semibold text-serena-text-main transition hover:border-serena-primary"
                                    @click="moveCurrentOrder"
                                >
                                    Déplacer
                                </button>
                            </div>
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
                            {{ currentTable ? 'Aucun article sélectionné.' : 'Choisissez une table pour commencer.' }}
                        </div>
                    </div>

                    <section class="rounded-2xl bg-white p-4 shadow-sm">
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
                        <div class="mb-4 flex flex-wrap gap-2">
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
                        <div
                            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
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
                                    :disabled="!currentTable"
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
                </section>

                <section
                    class="rounded-2xl bg-white p-4 shadow-lg xl:w-96"
                    :class="{ 'order-first': isMobile }"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-serena-text-main">Actions</h2>
                        <button
                            type="button"
                            class="text-sm text-serena-danger underline decoration-dotted disabled:opacity-30"
                            :disabled="!cart.length || !canCreatePos"
                            @click="clearCart"
                        >
                            Vider
                        </button>
                    </div>

                    <div class="mt-4 space-y-2 rounded-2xl bg-serena-bg-soft p-4">
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

                    <div class="mt-4 flex rounded-full bg-white p-1 shadow-sm">
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
import CashIndicator from '@/components/CashIndicator.vue';

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
            tables: [],
            tableSearch: '',
            tableArea: 'all',
            loadingTables: false,
            currentTableId: null,
            currentOrder: null,
            tableStates: {},
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
        canManageTables() {
            return this.permissionFlags.pos_tables_manage ?? false;
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
            return (
                this.canCreatePos &&
                this.cart.length > 0 &&
                !!this.selectedPaymentMethodId &&
                !!this.currentTableId &&
                !this.submitting
            );
        },
        canSubmitRoom() {
            return (
                this.canCreatePos &&
                this.cart.length > 0 &&
                !!this.selectedReservationId &&
                !!this.currentTableId &&
                !this.submitting &&
                this.inHouseReservations.length > 0
            );
        },
        tableAreas() {
            return [...new Set(this.tables.map((table) => table.area).filter(Boolean))].sort();
        },
        filteredTables() {
            return this.tables.filter((table) => {
                if (this.tableArea !== 'all' && table.area !== this.tableArea) {
                    return false;
                }
                if (!this.tableSearch) {
                    return true;
                }
                const needle = this.tableSearch.toLowerCase();
                return `${table.name} ${table.area || ''}`.toLowerCase().includes(needle);
            });
        },
        currentTable() {
            if (!this.currentTableId) {
                return null;
            }
            return this.tables.find((table) => table.id === this.currentTableId) || null;
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
        this.loadTables();
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
        async loadTables() {
            this.loadingTables = true;
            try {
                const response = await axios.get('/bar/tables');
                this.tables = response.data.tables || [];
            } catch (error) {
                this.handleError(error);
            } finally {
                this.loadingTables = false;
            }
        },
        tableButtonClass(table) {
            if (this.currentTableId === table.id) {
                return 'border-serena-primary bg-serena-primary-soft';
            }
            if (table.open_order) {
                return 'border-serena-warning/50 bg-serena-warning/5';
            }
            return 'border-serena-border/60 hover:border-serena-primary/60';
        },
        persistCurrentTableState() {
            if (!this.currentTableId) {
                return;
            }
            this.tableStates[this.currentTableId] = {
                mode: this.mode,
                cart: this.cart.map((line) => ({ ...line })),
                selectedPaymentMethodId: this.selectedPaymentMethodId,
                selectedReservationId: this.selectedReservationId,
                clientLabel: this.clientLabel,
            };
        },
        loadTableState(tableId) {
            const state = this.tableStates[tableId];
            if (state) {
                this.mode = state.mode;
                this.cart = state.cart.map((line) => ({ ...line }));
                this.selectedPaymentMethodId = state.selectedPaymentMethodId ?? this.paymentMethods[0]?.id ?? null;
                this.selectedReservationId = state.selectedReservationId ?? this.inHouseReservations[0]?.id ?? null;
                this.clientLabel = state.clientLabel ?? '';
                return;
            }

            this.mode = 'counter';
            this.cart = [];
            this.selectedPaymentMethodId = this.paymentMethods[0]?.id ?? null;
            this.selectedReservationId = this.inHouseReservations[0]?.id ?? null;
            this.clientLabel = '';
        },
        async selectTable(table) {
            if (this.currentTableId === table.id) {
                return;
            }
            this.persistCurrentTableState();
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();
                return;
            }

            try {
                const response = await axios.post('/bar/orders/open-for-table', {
                    bar_table_id: table.id,
                });
                this.currentOrder = response.data.order;
                this.currentTableId = table.id;
                this.loadTableState(table.id);
                this.loadTables();
            } catch (error) {
                this.handleError(error);
            }
        },
        async moveCurrentOrder() {
            if (!this.currentOrder || !this.canManageTables) {
                return;
            }
            const availableTables = this.tables.filter(
                (table) => !table.open_order && table.id !== this.currentTableId,
            );

            if (!availableTables.length) {
                await Swal.fire({
                    icon: 'info',
                    title: 'Aucune table disponible',
                    text: 'Toutes les tables sont déjà ouvertes.',
                });
                return;
            }

            const inputOptions = availableTables.reduce((options, table) => {
                options[table.id] = table.area ? `${table.name} • ${table.area}` : table.name;
                return options;
            }, {});

            const result = await Swal.fire({
                title: 'Déplacer la commande',
                input: 'select',
                inputOptions,
                inputPlaceholder: 'Sélectionner une table',
                showCancelButton: true,
                confirmButtonText: 'Déplacer',
                cancelButtonText: 'Annuler',
                inputValidator: (value) => (value ? null : 'Veuillez sélectionner une table.'),
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.patch(`/bar/orders/${this.currentOrder.id}/move-table`, {
                    bar_table_id: Number(result.value),
                });
                const newTableId = response.data.order?.bar_table?.id;
                if (newTableId) {
                    this.tableStates[newTableId] = this.tableStates[this.currentTableId] ?? {
                        mode: this.mode,
                        cart: this.cart.map((line) => ({ ...line })),
                        selectedPaymentMethodId: this.selectedPaymentMethodId,
                        selectedReservationId: this.selectedReservationId,
                        clientLabel: this.clientLabel,
                    };
                    delete this.tableStates[this.currentTableId];
                    this.currentTableId = newTableId;
                    this.currentOrder = response.data.order;
                    this.loadTableState(newTableId);
                }
                await this.loadTables();
            } catch (error) {
                this.handleError(error);
            }
        },
        resetCurrentTableState() {
            if (!this.currentTableId) {
                return;
            }
            delete this.tableStates[this.currentTableId];
            this.loadTableState(this.currentTableId);
            this.currentOrder = null;
        },
        addToCart(product) {
            if (!this.canCreatePos) {
                this.showUnauthorizedAlert();

                return;
            }
            if (!this.currentTableId) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sélectionnez une table',
                    text: 'Choisissez une table avant d’ajouter des produits.',
                });
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
                    bar_order_id: this.currentOrder?.id ?? null,
                };
                const response = await axios.post('/pos/sales/counter', payload);

                this.resetCurrentTableState();
                await this.loadTables();

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
                    bar_order_id: this.currentOrder?.id ?? null,
                };
                const response = await axios.post('/pos/sales/room', payload);

                const reservation = this.inHouseReservations.find((item) => item.id === this.selectedReservationId);
                this.resetCurrentTableState();
                await this.loadTables();

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
