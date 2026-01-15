<template>
    <AppLayout title="Stock">
        <div class="space-y-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-serena-text-main">Stock</h1>
                    <p class="text-sm text-serena-text-muted">
                        Approvisionnements, transferts et inventaires dans un seul tableau de bord.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        type="button"
                        class="bg-serena-primary text-white"
                        @click="goToPurchaseCreate"
                        :disabled="!permissions.can_create_purchase"
                    >
                        Nouvelle réception
                    </PrimaryButton>
            <PrimaryButton
                type="button"
                class="bg-serena-primary text-white"
                @click="goToTransferCreate"
                :disabled="!permissions.can_create_transfer"
            >
                Nouveau transfert
            </PrimaryButton>
                    <PrimaryButton
                        type="button"
                        class="bg-serena-primary text-white"
                        @click="showInventoryModal = true"
                        :disabled="!permissions.can_create_inventory"
                    >
                        Nouvel inventaire
                    </PrimaryButton>
                </div>
            </div>

            <article class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ maintenanceConsumptionSummary.period_label || '30 derniers jours' }}
                        </p>
                        <h2 class="text-lg font-semibold text-serena-text-main">
                            Consommation maintenance
                        </h2>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-serena-text-main">
                            {{ formatAmount(maintenanceConsumptionSummary.total_cost, 'XAF') }}
                        </p>
                        <p class="text-xs text-serena-text-muted">
                            Articles : {{ formatQuantity(maintenanceConsumptionSummary.total_quantity) }}
                        </p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-serena-text-muted">
                    Total des pièces consommées via les interventions de maintenance.
                </p>
            </article>

            <section class="space-y-6">
                <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                    <header class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-serena-text-main">Réceptions récentes</h2>
                        <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ purchases.length }} achats
                        </span>
                    </header>
                    <div v-if="!purchases.length" class="mt-4 text-sm text-serena-text-muted">
                        Aucune réception enregistrée.
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">Réf.</th>
                                    <th class="px-4 py-3">Emplacement</th>
                                    <th class="px-4 py-3">Fournisseur</th>
                                    <th class="px-4 py-3">Montant</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border text-serena-text-main">
                                <tr v-for="purchase in purchases" :key="purchase.id">
                                    <td class="px-4 py-3 font-semibold">
                                        {{ purchase.reference_no || `#${purchase.id}` }}
                                    </td>
                                    <td class="px-4 py-3">{{ purchase.storage_location?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ purchase.supplier_name ?? '—' }}</td>
                                    <td class="px-4 py-3 font-semibold">
                                        {{ formatAmount(purchase.total_amount, purchase.currency) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="statusClasses(purchase.status)" class="rounded-full px-3 py-1 text-[11px] font-semibold">
                                            {{ statusLabel(purchase.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="permissions.can_receive_purchase && purchase.status === 'draft'"
                                            type="button"
                                            class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                            @click="receivePurchase(purchase)"
                                        >
                                            Recevoir
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                    <header class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-serena-text-main">Transferts</h2>
                        <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ transfers.length }} transferts
                        </span>
                    </header>
                    <div v-if="!transfers.length" class="mt-4 text-sm text-serena-text-muted">
                        Pas encore de transferts.
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">De → À</th>
                                    <th class="px-4 py-3">Montant estimé</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border text-serena-text-main">
                                <tr v-for="transfer in transfers" :key="transfer.id">
                                    <td class="px-4 py-3">
                                        {{ transfer.from_location?.name ?? '—' }}
                                        →
                                        {{ transfer.to_location?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatAmount(transfer.lines?.reduce((sum, line) => sum + (line.total_cost ?? 0), 0), 'XAF') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="statusClasses(transfer.status)" class="rounded-full px-3 py-1 text-[11px] font-semibold">
                                            {{ statusLabel(transfer.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="permissions.can_complete_transfer && transfer.status === 'draft'"
                                            type="button"
                                            class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                            @click="completeTransfer(transfer)"
                                        >
                                            Finaliser
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                    <header class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-serena-text-main">Inventaires</h2>
                        <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ inventories.length }} inventaires
                        </span>
                    </header>
                    <div v-if="!inventories.length" class="mt-4 text-sm text-serena-text-muted">
                        Aucun inventaire enregistré.
                    </div>
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">Emplacement</th>
                                    <th class="px-4 py-3">Lignes</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border text-serena-text-main">
                                <tr v-for="inventory in inventories" :key="inventory.id">
                                    <td class="px-4 py-3">{{ inventory.storage_location?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ inventory.lines?.length ?? 0 }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="statusClasses(inventory.status)" class="rounded-full px-3 py-1 text-[11px] font-semibold">
                                            {{ statusLabel(inventory.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="permissions.can_post_inventory && inventory.status === 'draft'"
                                            type="button"
                                            class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                            @click="postInventory(inventory)"
                                        >
                                            Poster
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                    <header class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-serena-text-main">Emplacements</h2>
                            <p class="text-sm text-serena-text-muted">
                                Sélectionnez un emplacement pour consulter les articles stockés et leurs quantités.
                            </p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ storageLocations.length }} emplacements
                        </span>
                    </header>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <button
                            v-for="location in locationSummaries"
                            :key="location.id"
                            type="button"
                            class="w-full rounded-2xl border p-4 text-left transition"
                            :class="selectedLocationId === location.id
                                ? 'border-serena-primary bg-serena-primary-soft text-serena-primary'
                                : 'border-serena-border bg-white text-serena-text-main'"
                            @click="selectLocation(location.id)"
                        >
                            <p class="text-sm font-semibold">{{ location.name }}</p>
                            <p class="text-xs text-serena-text-muted">
                                {{ location.totalItems }} articles • {{ formatQuantity(location.totalQuantity) }} totaux
                            </p>
                        </button>
                    </div>
                    <div class="mt-5" v-if="selectedLocation">
                        <div class="overflow-x-auto rounded-2xl border border-serena-border bg-white p-4">
                            <h3 class="text-sm font-semibold text-serena-text-main">
                                Articles dans {{ selectedLocation.name }}
                            </h3>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-serena-border text-sm">
                                    <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                                        <tr>
                                            <th class="px-4 py-3">Article</th>
                                            <th class="px-4 py-3">Unité</th>
                                            <th class="px-4 py-3 text-right">Quantité</th>
                                            <th class="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-serena-border text-serena-text-main">
                                        <tr v-for="record in selectedLocationRecords" :key="record.id">
                                            <td class="px-4 py-3">
                                                {{ record.stock_item?.name ?? '—' }}
                                                <span
                                                    v-if="isLowStock(record)"
                                                    class="ml-2 inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600"
                                                >
                                                    Bas
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">{{ record.stock_item?.unit ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right font-semibold">
                                                {{ formatQuantity(record.quantity_on_hand) }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                                    @click="selectItem(record.stock_item?.id)"
                                                    :disabled="!record.stock_item"
                                                >
                                                    Voir mouvements
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4 text-sm text-serena-text-muted">
                        Sélectionnez un emplacement pour voir les articles stockés.
                    </div>
                </article>

                <article class="rounded-2xl border border-serena-border bg-white p-6 shadow-sm">
                    <header class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-serena-text-main">Articles</h2>
                            <p class="text-sm text-serena-text-muted">
                                Aperçu rapide des articles existants et de leurs emplacements.
                            </p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                            {{ itemSummaries.length }} articles
                        </span>
                    </header>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-serena-border text-sm">
                            <thead class="bg-serena-bg-soft/70 text-left text-[11px] font-semibold uppercase text-serena-text-muted">
                                <tr>
                                    <th class="px-4 py-3">Article</th>
                                    <th class="px-4 py-3 text-right">Quantité totale</th>
                                    <th class="px-4 py-3">Emplacements</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-serena-border text-serena-text-main">
                                <tr v-for="item in itemSummaries" :key="item.id">
                                    <td class="px-4 py-3">
                                        {{ item.name }}
                                        <span v-if="item.sku" class="ml-2 text-[11px] text-serena-text-muted">
                                            ({{ item.sku }})
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        {{ formatQuantity(item.totalQuantity) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[11px] text-serena-text-muted">
                                            {{ item.locations.join(', ') || 'Aucun' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-serena-primary hover:text-serena-primary-dark"
                                            @click="selectItem(item.id)"
                                        >
                                            Voir mouvements
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="selectedItem" class="mt-5 space-y-4 rounded-2xl border border-serena-border bg-serena-card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">Article sélectionné</p>
                                <h3 class="text-xl font-semibold text-serena-text-main">
                                    {{ selectedItem.name }}
                                </h3>
                                <p class="text-sm text-serena-text-muted">
                                    {{ selectedItem.sku ? `SKU ${selectedItem.sku}` : 'Sans SKU' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-serena-text-main">
                                    {{ formatQuantity(selectedItemSummary?.totalQuantity) }}
                                </p>
                                <p class="text-xs text-serena-text-muted">Quantité totale</p>
                            </div>
                        </div>
                        <div v-if="selectedItemMovements.length" class="space-y-3">
                            <div
                                v-for="movement in selectedItemMovements"
                                :key="movement.id"
                                class="rounded-xl border border-serena-border bg-white p-4 text-sm text-serena-text-main"
                            >
                                <div class="flex items-center justify-between text-xs uppercase tracking-wide text-serena-text-muted">
                                    <span>{{ movement.movement_type }}</span>
                                    <span>{{ formatDate(movement.occurred_at) }}</span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-serena-text-main">
                                    {{ movement.reference?.label ?? `#${movement.id}` }}
                                </p>
                                <p class="text-[13px] text-serena-text-muted">
                                    {{ movement.from_location?.name ?? '—' }}
                                    →
                                    {{ movement.to_location?.name ?? '—' }}
                                </p>
                                <p class="mt-1 text-[13px] text-serena-text-main">
                                    Quantité : {{ formatQuantity(movementItemQuantity(movement, selectedItemId)) }}
                                    {{ movementLineUnit(movement, selectedItemId) }}
                                </p>
                                <Link
                                    :href="movement.movement_url"
                                    class="mt-2 inline-flex text-[11px] font-semibold text-serena-primary hover:underline"
                                >
                                    Voir détail
                                </Link>
                            </div>
                        </div>
                        <div v-else class="text-xs text-serena-text-muted">
                            Aucun mouvement récent pour cet article.
                        </div>
                    </div>
                </article>
            </section>


            <div v-if="showInventoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-3xl rounded-2xl border border-serena-border bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-serena-text-main">Nouvel inventaire</h3>
                            <p class="text-sm text-serena-text-muted">Enregistrez les quantités comptées pour chaque article.</p>
                        </div>
                        <button type="button" class="text-sm text-serena-text-muted" @click="showInventoryModal = false">
                            Fermer
                        </button>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <select v-model="inventoryForm.storage_location_id" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm">
                            <option value="">Emplacement</option>
                            <option v-for="location in storageLocations" :key="location.id" :value="location.id">
                                {{ location.name }}
                            </option>
                        </select>
                        <input v-model="inventoryForm.counted_at" type="date" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                        <input v-model="inventoryForm.notes" type="text" placeholder="Notes" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="(line, index) in inventoryForm.lines" :key="index" class="grid gap-3 md:grid-cols-[2fr,1fr,auto]">
                            <select v-model="line.stock_item_id" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm">
                                <option value="">Article</option>
                                <option v-for="item in stockItems" :key="item.id" :value="item.id">
                                    {{ item.name }} <span v-if="item.sku">({{ item.sku }})</span>
                                </option>
                            </select>
                            <input v-model.number="line.counted_quantity" type="number" min="0" step="0.01" placeholder="Quantité comptée" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm" />
                            <button type="button" class="text-sm font-semibold text-rose-600" @click="removeInventoryLine(index)" v-if="inventoryForm.lines.length > 1">
                                Supprimer
                            </button>
                        </div>
                        <button type="button" class="text-sm font-semibold text-serena-primary" @click="addInventoryLine">
                            + Ajouter une ligne
                        </button>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-serena-border px-4 py-2 text-xs font-semibold" @click="closeInventoryModal">
                            Annuler
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-serena-primary px-4 py-2 text-xs font-semibold text-white"
                            @click="submitInventory"
                            :disabled="inventorySubmitting"
                        >
                            {{ inventorySubmitting ? 'Enregistrement…' : 'Créer' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'StockIndex',
    components: {
        AppLayout,
        Link,
        PrimaryButton,
    },
    props: {
        purchases: {
            type: Array,
            default: () => [],
        },
        transfers: {
            type: Array,
            default: () => [],
        },
        inventories: {
            type: Array,
            default: () => [],
        },
        stockOnHand: {
            type: Array,
            default: () => [],
        },
        storageLocations: {
            type: Array,
            default: () => [],
        },
        stockItems: {
            type: Array,
            default: () => [],
        },
        movements: {
            type: Array,
            default: () => [],
        },
        maintenanceConsumptionSummary: {
            type: Object,
            default: () => ({
                period_label: '',
                total_quantity: 0,
                total_cost: 0,
            }),
        },
        permissions: {
            type: Object,
            default: () => ({
                can_create_purchase: false,
                can_receive_purchase: false,
                can_create_transfer: false,
                can_complete_transfer: false,
                can_create_inventory: false,
                can_post_inventory: false,
            }),
        },
    },
    data() {
        return {
            showInventoryModal: false,
            inventoryForm: this.createInventoryForm(),
            inventorySubmitting: false,
        selectedLocationId: '',
        selectedItemId: '',
        };
    },
    computed: {
        locationStockMap() {
            return this.stockOnHand.reduce((map, record) => {
                const locationId = record.storage_location?.id;
                if (!locationId) {
                    return map;
                }

                if (!map[locationId]) {
                    map[locationId] = [];
                }

                map[locationId].push(record);

                return map;
            }, {});
        },
        locationSummaries() {
            return this.storageLocations.map((location) => {
                const records = this.locationStockMap[location.id] ?? [];
                const totalQuantity = records.reduce(
                    (total, record) => total + Number(record.quantity_on_hand ?? 0),
                    0,
                );

                return {
                    id: location.id,
                    name: location.name,
                    totalItems: records.length,
                    totalQuantity,
                };
            });
        },
        selectedLocationRecords() {
            return this.locationStockMap[this.selectedLocationId] ?? [];
        },
        selectedLocation() {
            return this.storageLocations.find((location) => location.id === this.selectedLocationId) ?? null;
        },
        itemSummaries() {
            const map = new Map();

            this.stockItems.forEach((item) => {
                map.set(item.id, {
                    id: item.id,
                    name: item.name,
                    sku: item.sku,
                    totalQuantity: 0,
                    locations: [],
                });
            });

            this.stockOnHand.forEach((record) => {
                const item = record.stock_item;
                const locationName = record.storage_location?.name;

                if (!item) {
                    return;
                }

                const entry = map.get(item.id) ?? {
                    id: item.id,
                    name: item.name,
                    sku: item.sku,
                    totalQuantity: 0,
                    locations: [],
                };

                entry.totalQuantity += Number(record.quantity_on_hand ?? 0);

                if (locationName && !entry.locations.includes(locationName)) {
                    entry.locations.push(locationName);
                }

                map.set(item.id, entry);
            });

            return Array.from(map.values());
        },
        selectedItem() {
            return this.stockItems.find((item) => item.id === this.selectedItemId) ?? null;
        },
        selectedItemSummary() {
            return this.itemSummaries.find((item) => item.id === this.selectedItemId) ?? null;
        },
        selectedItemMovements() {
            if (!this.selectedItemId) {
                return [];
            }

            return this.movements.filter((movement) =>
                movement.lines?.some((line) => line.stock_item?.id === this.selectedItemId),
            );
        },
    },
    methods: {
        selectLocation(locationId) {
            if (locationId) {
                this.selectedLocationId = locationId;
            }
        },
        selectItem(itemId) {
            if (itemId) {
                this.selectedItemId = itemId;
            }
        },
        movementItemQuantity(movement, itemId) {
            const line = movement.lines?.find((candidate) => candidate.stock_item?.id === itemId);

            return line ? Number(line.quantity ?? 0) : 0;
        },
        movementLineUnit(movement, itemId) {
            const line = movement.lines?.find((candidate) => candidate.stock_item?.id === itemId);

            return line?.stock_item?.unit ?? '';
        },
        formatDate(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        },
        createInventoryForm() {
            return {
                storage_location_id: '',
                counted_at: '',
                notes: '',
                lines: [this.emptyInventoryLine()],
            };
        },
        emptyInventoryLine() {
            return {
                stock_item_id: '',
                counted_quantity: 0,
            };
        },
        addInventoryLine() {
            this.inventoryForm.lines.push(this.emptyInventoryLine());
        },
        removeInventoryLine(index) {
            this.inventoryForm.lines.splice(index, 1);
        },
        closeInventoryModal() {
            this.showInventoryModal = false;
            this.inventoryForm = this.createInventoryForm();
        },
        async submitInventory() {
            if (this.inventorySubmitting) {
                return;
            }

            this.inventorySubmitting = true;

            try {
                await axios.post('/stock/inventories', this.inventoryForm);

                Swal.fire({ icon: 'success', title: 'Inventaire créé', timer: 1400, showConfirmButton: false });
                this.closeInventoryModal();
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erreur', text: error.response?.data?.message ?? 'Impossible d’enregistrer l’inventaire.' });
            } finally {
                this.inventorySubmitting = false;
            }
        },
        async receivePurchase(purchase) {
            try {
                await axios.post(`/stock/purchases/${purchase.id}/receive`);
                Swal.fire({ icon: 'success', title: 'Réception validée', timer: 1400, showConfirmButton: false });
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erreur', text: error.response?.data?.message ?? 'Impossible de recevoir.' });
            }
        },
        async completeTransfer(transfer) {
            try {
                await axios.post(`/stock/transfers/${transfer.id}/complete`);
                Swal.fire({ icon: 'success', title: 'Transfert finalisé', timer: 1400, showConfirmButton: false });
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erreur', text: error.response?.data?.message ?? 'Impossible de finaliser.' });
            }
        },
        async postInventory(inventory) {
            try {
                await axios.post(`/stock/inventories/${inventory.id}/post`);
                Swal.fire({ icon: 'success', title: 'Inventaire posté', timer: 1400, showConfirmButton: false });
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erreur', text: error.response?.data?.message ?? 'Impossible de poster.' });
            }
        },
        goToPurchaseCreate() {
            this.$inertia.visit('/stock/purchases/create');
        },
        goToTransferCreate() {
            this.$inertia.visit('/stock/transfers/create');
        },
        statusLabel(status) {
            const map = {
                draft: 'Brouillon',
                received: 'Reçu',
                completed: 'Finalisé',
                posted: 'Posté',
                void: 'Annulé',
            };

            return map[status] ?? status;
        },
        statusClasses(status) {
            const styles = {
                draft: 'bg-gray-100 text-gray-600 border border-gray-200',
                received: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                completed: 'bg-amber-50 text-amber-700 border border-amber-200',
                posted: 'bg-blue-50 text-blue-700 border border-blue-200',
                void: 'bg-rose-50 text-rose-700 border border-rose-200',
            };

            return styles[status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
        },
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        formatQuantity(value) {
            const quantity = Number(value || 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        isLowStock(record) {
            const threshold = record.stock_item?.reorder_point;

            if (threshold === null || threshold === undefined) {
                return false;
            }

            return Number(record.quantity_on_hand ?? 0) <= Number(threshold ?? 0);
        },
    },
    watch: {
        storageLocations: {
            handler(locations) {
                const firstId = locations[0]?.id ?? '';

                if (!this.inventoryForm.storage_location_id) {
                    this.inventoryForm.storage_location_id = firstId;
                }

                if (!this.selectedLocationId && firstId) {
                    this.selectedLocationId = firstId;
                }
            },
            immediate: true,
        },
        stockItems: {
            handler(items) {
                if (!this.selectedItemId && items.length) {
                    this.selectedItemId = items[0].id;
                }
            },
            immediate: true,
        },
    },
};
</script>
