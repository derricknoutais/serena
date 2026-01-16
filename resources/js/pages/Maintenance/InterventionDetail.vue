<template>
    <AppLayout title="Intervention">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-serena-text-muted">
                        Intervention #{{ intervention.id }}
                    </p>
                    <h1 class="text-2xl font-semibold text-serena-text-main">
                        {{ interventionTitle }}
                    </h1>
                    <p class="text-sm text-serena-text-muted">
                        Statut :
                        <span :class="statusClasses(intervention.accounting_status)" class="rounded-full px-3 py-0.5 text-[11px] font-semibold">
                            {{ statusLabel(intervention.accounting_status) }}
                        </span>
                        <span v-if="intervention.technician" class="ml-2 text-serena-text-main">
                            · {{ intervention.technician.name }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" class="bg-serena-primary" @click="goBack">
                        Retour
                    </PrimaryButton>
                    <button
                        v-if="canSubmitIntervention && intervention.accounting_status === 'draft'"
                        type="button"
                        class="rounded-full border border-blue-200 bg-blue-50 px-4 py-1 text-xs font-semibold text-blue-700"
                        @click="submitIntervention"
                        :disabled="processing"
                    >
                        Soumettre
                    </button>
                    <button
                        v-if="canApproveIntervention && intervention.accounting_status === 'submitted'"
                        type="button"
                        class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1 text-xs font-semibold text-emerald-700"
                        @click="approveIntervention"
                        :disabled="processing"
                    >
                        Approuver
                    </button>
                    <button
                        v-if="canRejectIntervention && intervention.accounting_status === 'submitted'"
                        type="button"
                        class="rounded-full border border-rose-200 bg-rose-50 px-4 py-1 text-xs font-semibold text-rose-700"
                        @click="promptReject"
                        :disabled="processing"
                    >
                        Rejeter
                    </button>
                    <button
                        v-if="canMarkPaidIntervention && intervention.accounting_status === 'approved'"
                        type="button"
                        class="rounded-full border border-purple-200 bg-purple-50 px-4 py-1 text-xs font-semibold text-purple-700"
                        @click="markPaidIntervention"
                        :disabled="processing"
                    >
                        Marquer payé
                    </button>
                </div>
            </div>

            <section class="grid gap-6 lg:grid-cols-2">
                <article class="space-y-3 rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-serena-text-main">Détails</h2>
                    <div class="space-y-2 text-sm text-serena-text-main">
                        <p>
                            <span class="font-semibold text-serena-text-muted">Technicien :</span>
                            {{ intervention.technician?.name ?? 'Non assigné' }}
                        </p>
                        <p>
                            <span class="font-semibold text-serena-text-muted">Période :</span>
                            {{ formatDateTime(intervention.started_at) || 'Non renseignée' }} →
                            {{ formatDateTime(intervention.ended_at) || '—' }}
                        </p>
                        <p>
                            <span class="font-semibold text-serena-text-muted">Résumé :</span>
                            {{ intervention.summary || 'Aucun résumé.' }}
                        </p>
                    </div>
                </article>
                <article class="space-y-3 rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-serena-text-main">Tickets associés</h2>
                    <div v-if="!intervention.tickets.length" class="text-sm text-serena-text-muted">
                        Aucun ticket pour cette intervention.
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="ticket in intervention.tickets"
                            :key="ticket.id"
                            class="rounded-xl border border-serena-border bg-serena-bg-soft/40 p-3 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-serena-text-main">{{ ticket.title }}</p>
                                    <p class="text-xs text-serena-text-muted">Chambre {{ ticket.room_number || '—' }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                    @click="detachTicket(ticket)"
                                >
                                    Dissocier
                                </button>
                            </div>
                            <label class="text-[11px] font-semibold text-serena-text-muted">Travaux</label>
                            <div class="flex gap-3">
                                <textarea
                                    v-model="ticketDetails[ticket.id]"
                                    rows="2"
                                    class="flex-1 rounded-xl border border-serena-border bg-white px-3 py-2 text-xs text-serena-text-main focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                                ></textarea>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                    @click="saveTicketDetails(ticket)"
                                    :disabled="processingTicketId === ticket.id"
                                >
                                    {{ processingTicketId === ticket.id ? 'En cours...' : 'Mettre à jour' }}
                                </button>
                            </div>
                        </li>
                    </ul>
                    <div class="rounded-xl border border-serena-border bg-serena-bg-soft/40 p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-serena-text-main">Associer un ticket</h3>
                        <div class="grid gap-3 md:grid-cols-2">
                            <select v-model="newTicketTicketId" class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm">
                                <option value="">Choisir un ticket</option>
                                <option v-for="ticket in availableTickets" :key="ticket.id" :value="ticket.id">
                                    {{ ticket.room_number ? `Ch ${ticket.room_number}` : '—' }} · {{ ticket.title }}
                                </option>
                            </select>
                            <textarea
                                v-model="newTicketWork"
                                rows="2"
                                placeholder="Travaux effectués"
                                class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="rounded-xl bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                                @click="attachTicket"
                                :disabled="!newTicketTicketId || processingTicketId"
                            >
                                {{ processingTicketId ? 'Enregistrement…' : 'Associer le ticket' }}
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-serena-text-main">Coûts (estimés)</h2>
                        <p class="text-xs text-serena-text-muted">
                            Info interne : ces montants n’impactent ni la caisse ni les paiements.
                        </p>
                    </div>
                    <p class="text-sm font-semibold text-serena-text-main">
                        {{ formatAmount(intervention.estimated_total_amount ?? intervention.total_cost, intervention.currency) }}
                    </p>
                </div>
            <div v-if="!permissions.can_view_costs" class="mt-3 text-sm text-serena-text-muted">
                Vous n’avez pas accès aux coûts estimés.
            </div>
            <div v-else-if="!intervention.costs.length" class="mt-3 text-sm text-serena-text-muted">
                Aucune ligne de coût estimé ajoutée.
            </div>
            <div v-else class="mt-4 overflow-x-auto rounded-xl border border-serena-border">
                <table class="min-w-full divide-y divide-serena-border text-xs">
                    <thead class="bg-serena-bg-soft/70 text-left text-[10px] font-semibold uppercase text-serena-text-muted">
                        <tr>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Libellé</th>
                            <th class="px-3 py-2 text-right">Qté</th>
                            <th class="px-3 py-2 text-right">PU estimé</th>
                            <th class="px-3 py-2 text-right">Total estimé</th>
                            <th class="px-3 py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-serena-border/60 text-serena-text-main">
                        <tr v-for="line in intervention.costs" :key="line.id">
                            <td class="px-3 py-2">{{ costTypeLabel(line.cost_type) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-col gap-1">
                                    <span>{{ line.label }}</span>
                                    <span
                                        v-if="line.source === 'stock'"
                                        class="inline-flex w-fit items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                    >
                                        Stock (estimé)
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ formatQuantity(line.quantity) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatAmount(line.unit_price, line.currency) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatAmount(line.total_amount, line.currency) }}</td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    v-if="permissions.can_edit_costs && line.source !== 'stock'"
                                    type="button"
                                    class="text-[11px] font-semibold text-serena-primary transition hover:text-serena-primary-dark"
                                    @click="openCostLine(line)"
                                >
                                    Modifier
                                </button>
                                <button
                                    v-if="permissions.can_edit_costs && line.source !== 'stock'"
                                    type="button"
                                    class="ml-2 text-[11px] font-semibold text-rose-600 transition hover:text-rose-700"
                                    @click="confirmDeleteCostLine(line)"
                                >
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="permissions.can_edit_costs" class="mt-6 rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-serena-text-main">
                        {{ editingCostLineId ? 'Modifier une ligne de coût estimé' : 'Nouvelle ligne de coût estimé' }}
                    </h2>
                    <p class="text-sm font-semibold text-serena-text-main">
                        {{ formatAmount(costFormTotal, intervention.currency) }}
                    </p>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <select
                        v-model="costForm.cost_type"
                        class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                    >
                        <option v-for="option in costTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <input
                        v-model="costForm.label"
                        type="text"
                        placeholder="Libellé"
                        class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                    />
                    <input
                        v-model.number="costForm.quantity"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Quantité"
                        class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                    />
                    <input
                        v-model.number="costForm.unit_price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Prix unitaire estimé"
                        class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                    />
                    <textarea
                        v-model="costForm.notes"
                        rows="2"
                        placeholder="Notes (facultatif)"
                        class="col-span-full rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                    ></textarea>
                </div>
                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                        @click="saveCostLine"
                        :disabled="processingCostLine"
                    >
                        {{ processingCostLine ? 'Enregistrement…' : editingCostLineId ? 'Mettre à jour' : 'Ajouter' }}
                    </button>
                    <button
                        v-if="editingCostLineId"
                        type="button"
                        class="rounded-xl border border-serena-border px-4 py-2 text-xs font-semibold text-serena-text-main transition hover:bg-serena-bg-soft"
                        @click="resetCostForm"
                        :disabled="processingCostLine"
                    >
                        Annuler
                    </button>
                </div>
            </div>
            <div v-else class="mt-4 rounded-xl border border-dashed border-serena-border bg-serena-bg-soft/50 p-4 text-xs text-serena-text-muted">
                Vous n’avez pas les droits pour modifier les coûts estimés.
            </div>
        </section>
        <section class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-serena-text-main">
                    Pièces / Équipements utilisés (estimé)
                </h2>
                <div class="text-sm text-right">
                    <p class="font-semibold">
                        Coût pièces (estimé) :
                        <span class="text-serena-text-main">
                            {{ formatAmount(intervention.stock_consumption_total, intervention.currency) }}
                        </span>
                    </p>
                    <p class="text-xs text-serena-text-muted">
                        Quantité :
                        {{ formatQuantity(stockItemsTotalQuantity) }}
                        articles
                    </p>
                </div>
            </div>
            <p class="mt-2 text-xs text-serena-text-muted">
                Info interne : la consommation de pièces n’entraîne aucun paiement automatique.
            </p>
            <div class="mt-4 space-y-3">
                <div v-if="!intervention.items.length" class="text-sm text-serena-text-muted">
                    Aucune pièce consommée pour cette intervention.
                </div>
                <ul v-else class="space-y-3">
                    <li
                        v-for="item in intervention.items"
                        :key="item.id"
                        class="rounded-xl border border-serena-border bg-serena-bg-soft/40 p-3 space-y-2"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-serena-text-main">
                                    {{ item.stock_item?.name ?? 'Pièce supprimée' }}
                                </p>
                                <p class="text-xs text-serena-text-muted">
                                    {{ item.stock_item?.sku ? `SKU ${item.stock_item.sku}` : item.stock_item?.unit }}
                                </p>
                            </div>
                            <p class="text-sm font-semibold text-serena-text-main">
                                {{ formatAmount(item.total_cost, intervention.currency) }}
                            </p>
                        </div>
                        <div class="text-xs text-serena-text-muted">
                            <p>Quantité : {{ formatQuantity(item.quantity) }} {{ item.stock_item?.unit ?? '' }}</p>
                            <p>Emplacement : {{ item.storage_location?.name ?? '—' }}</p>
                            <p v-if="item.notes">Notes : {{ item.notes }}</p>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="mt-6 space-y-3">
                <h3 class="text-sm font-semibold text-serena-text-main">Consommer une pièce</h3>
                <div class="grid gap-3 md:grid-cols-4">
                        <select
                            v-model="stockForm.storage_location_id"
                            :disabled="!stockEditorEnabled"
                            class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        >
                        <option value="">Choisir un emplacement</option>
                        <option
                            v-for="location in availableStorageLocations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }}
                        </option>
                    </select>
                        <select
                            v-model="stockForm.stock_item_id"
                            :disabled="!stockEditorEnabled"
                            class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        >
                        <option value="">Choisir un article</option>
                        <option
                            v-for="item in availableStockItems"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }} <span v-if="item.sku">({{ item.sku }})</span>
                        </option>
                    </select>
                        <input
                            v-model.number="stockForm.quantity"
                            :disabled="!stockEditorEnabled"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Quantité"
                            class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                        <input
                            v-model.number="stockForm.unit_cost"
                            :disabled="!stockEditorEnabled || !permissions.can_override_unit_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Prix unitaire estimé (facultatif)"
                            class="rounded-xl border border-serena-border bg-white px-3 py-2 text-sm focus:border-serena-primary focus:outline-none focus:ring-2 focus:ring-serena-primary-soft"
                        />
                </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-serena-text-muted">
                            Total estimé : {{ formatAmount(stockFormTotal, intervention.currency) }}
                        </p>
                        <button
                            type="button"
                            class="rounded-xl bg-serena-primary px-4 py-2 text-xs font-semibold text-white transition hover:bg-serena-primary-dark"
                            @click="addStockItem"
                            :disabled="!stockEditorEnabled || processingStockItem || !stockForm.stock_item_id || !stockForm.storage_location_id"
                        >
                            {{ processingStockItem ? 'Enregistrement…' : 'Ajouter' }}
                        </button>
                    </div>
                <div v-if="permissions.can_override_negative_stock" class="text-xs text-serena-text-muted">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="stockForm.allow_negative_stock"
                            type="checkbox"
                            :disabled="!stockEditorEnabled"
                            class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                        />
                        Autoriser le stock négatif (responsables)
                    </label>
                </div>
                <div v-if="!stockEditorEnabled" class="text-xs text-rose-600">
                    {{ intervention.accounting_status === 'submitted'
                        ? 'Seuls les responsables peuvent modifier les pièces après soumission.'
                        : 'Cette intervention est verrouillée, impossible d’ajouter des pièces.' }}
                </div>
            </div>
        </section>
        <section class="rounded-2xl border border-serena-border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-serena-text-main">Historique du stock</h2>
                    <p class="text-sm text-serena-text-muted">
                        {{ stockMovements.length }} mouvement(s)
                    </p>
                </div>
                <div v-if="stockMovements.length" class="text-xs text-serena-text-muted">
                    {{ stockMovements[0].occurred_at ? formatDateTime(stockMovements[0].occurred_at) : '' }}
                </div>
            </div>
            <div v-if="!stockMovements.length" class="mt-3 text-sm text-serena-text-muted">
                Aucun mouvement enregistré pour cette intervention.
            </div>
            <ul v-else class="mt-4 space-y-3">
                <li
                    v-for="movement in stockMovements"
                    :key="movement.id"
                    class="rounded-xl border border-serena-border bg-serena-bg-soft/40 p-3 space-y-2"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                                {{ movement.movement_type }}
                            </p>
                            <p class="text-sm text-serena-text-main">
                                {{ formatDateTime(movement.occurred_at) }}
                            </p>
                        </div>
                        <Link
                            v-if="movement.movement_url"
                            :href="movement.movement_url"
                            class="text-xs font-semibold text-serena-primary hover:underline"
                        >
                            Voir le mouvement
                        </Link>
                    </div>
                    <div class="text-xs text-serena-text-muted space-y-1">
                        <p v-if="movement.reference?.label">
                            Référence :
                            <span class="font-semibold text-serena-text-main">
                                {{ movement.reference.label }}
                            </span>
                            <Link
                                v-if="movement.reference?.url"
                                :href="movement.reference.url"
                                class="ml-1 font-semibold text-serena-primary hover:underline"
                            >
                                Voir
                            </Link>
                        </p>
                        <p v-if="movement.from_location">De : {{ movement.from_location.name }}</p>
                        <p v-if="movement.to_location">Vers : {{ movement.to_location.name }}</p>
                        <p v-if="movement.created_by">Enregistré par : {{ movement.created_by.name }}</p>
                    </div>
                    <ul class="space-y-1 text-xs font-semibold text-serena-text-main">
                        <li
                            v-for="line in movement.lines"
                            :key="line.id"
                            class="flex items-center justify-between"
                        >
                            <span>
                                {{ line.stock_item?.name ?? 'Article supprimé' }}
                                · {{ formatQuantity(line.quantity) }}
                                {{ line.stock_item?.unit ?? '' }}
                            </span>
                            <span>
                                {{ formatAmount(line.total_cost, line.currency) }}
                            </span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
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
    name: 'MaintenanceInterventionDetail',
    components: {
        AppLayout,
        PrimaryButton,
        Link,
    },
    props: {
        intervention: {
            type: Object,
            required: true,
        },
        permissions: {
            type: Object,
            default: () => ({
                can_submit: false,
                can_approve: false,
                can_reject: false,
                can_mark_paid: false,
                can_view_costs: false,
                can_edit_costs: false,
                can_add_stock_items: false,
                can_override_negative_stock: false,
                can_modify_submitted_stock_items: false,
                can_override_unit_cost: false,
            }),
        },
        availableTickets: {
            type: Array,
            default: () => [],
        },
        availableStorageLocations: {
            type: Array,
            default: () => [],
        },
        availableStockItems: {
            type: Array,
            default: () => [],
        },
        stockMovements: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            processing: false,
            ticketDetails: {},
            processingTicketId: null,
            newTicketTicketId: '',
            newTicketWork: '',
            costForm: {
                cost_type: 'labor',
                label: '',
                quantity: 1,
                unit_price: 0,
                notes: '',
            },
            editingCostLineId: null,
            processingCostLine: false,
        stockForm: {
            storage_location_id: '',
            stock_item_id: '',
            quantity: 1,
            unit_cost: 0,
            allow_negative_stock: false,
        },
            processingStockItem: false,
        };
    },
    mounted() {
        this.resetTicketDetails();
        this.resetCostForm();
        this.resetStockForm();
        this.applyStockItemDefaults();
    },
    computed: {
        canSubmitIntervention() {
            return Boolean(this.permissions.can_submit);
        },
        canApproveIntervention() {
            return Boolean(this.permissions.can_approve);
        },
        canRejectIntervention() {
            return Boolean(this.permissions.can_reject);
        },
        canMarkPaidIntervention() {
            return Boolean(this.permissions.can_mark_paid);
        },
        interventionTitle() {
            if (this.intervention?.technician?.name) {
                return `Intervention - ${this.intervention.technician.name}`;
            }

            return 'Intervention de maintenance';
        },
        costTypeOptions() {
            return [
                { value: 'labor', label: 'Main d’œuvre' },
                { value: 'parts', label: 'Pièces' },
                { value: 'transport', label: 'Transport' },
                { value: 'service', label: 'Service' },
                { value: 'other', label: 'Autre' },
            ];
        },
        costFormTotal() {
            const quantity = Number(this.costForm.quantity || 0);
            const price = Number(this.costForm.unit_price || 0);

            return quantity * price;
        },
        canAddStockItems() {
            return Boolean(this.permissions.can_add_stock_items);
        },
        stockFormTotal() {
            const quantity = Number(this.stockForm.quantity || 0);
            const price = Number(this.stockForm.unit_cost || 0);

            return quantity * price;
        },
        selectedStockItem() {
            const id = this.stockForm.stock_item_id;

            if (!id) {
                return null;
            }

            return this.availableStockItems.find((item) => Number(item.id) === Number(id)) ?? null;
        },
        stockEditorEnabled() {
            if (!this.permissions.can_add_stock_items) {
                return false;
            }

            const status = this.intervention.accounting_status;

            if (status === 'approved' || status === 'paid') {
                return false;
            }

            if (status === 'submitted' && !this.permissions.can_modify_submitted_stock_items) {
                return false;
            }

            return true;
        },
        stockItemsTotalQuantity() {
            return (this.intervention.items ?? []).reduce((sum, item) => sum + (item?.quantity ?? 0), 0);
        },
    },
    methods: {
        costTypeLabel(value) {
            switch (value) {
                case 'labor':
                    return 'Main d’œuvre';
                case 'parts':
                    return 'Pièces';
                case 'transport':
                    return 'Transport';
                case 'service':
                    return 'Service';
                default:
                    return 'Autre';
            }
        },
        statusLabel(status) {
            switch (status) {
                case 'submitted': return 'Soumise';
                case 'approved': return 'Approuvée';
                case 'paid': return 'Payée';
                case 'rejected': return 'Rejetée';
                default: return 'Brouillon';
            }
        },
        statusClasses(status) {
            switch (status) {
                case 'submitted': return 'bg-blue-50 text-blue-700 border border-blue-200';
                case 'approved': return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                case 'paid': return 'bg-purple-50 text-purple-700 border border-purple-200';
                case 'rejected': return 'bg-rose-50 text-rose-700 border border-rose-200';
                default: return 'bg-gray-100 text-gray-600 border border-gray-200';
            }
        },
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);

            return `${amount.toFixed(0)} ${currency}`;
        },
        formatQuantity(value) {
            const quantity = Number(value || 0);

            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        formatDateTime(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        },
        goBack() {
            this.$inertia.visit('/maintenance', { preserveState: true, replace: true });
        },
        async submitIntervention() {
            if (!this.canSubmitIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            await this.performAction(`/maintenance/interventions/${this.intervention.id}/submit`, 'Intervention soumise');
        },
        async approveIntervention() {
            if (!this.canApproveIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            await this.performAction(`/maintenance/interventions/${this.intervention.id}/approve`, 'Intervention approuvée');
        },
        async promptReject() {
            if (!this.canRejectIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Rejeter l’intervention',
                input: 'textarea',
                inputLabel: 'Raison du rejet',
                inputPlaceholder: 'Expliquez la raison du rejet...',
                showCancelButton: true,
                confirmButtonText: 'Rejeter',
                cancelButtonText: 'Annuler',
            });

            if (!result.isConfirmed) {
                return;
            }

            await this.performAction(
                `/maintenance/interventions/${this.intervention.id}/reject`,
                'Intervention rejetée',
                { rejection_reason: result.value || '' },
            );
        },
        async markPaidIntervention() {
            if (!this.canMarkPaidIntervention) {
                this.showUnauthorizedAlert();

                return;
            }

            await this.performAction(`/maintenance/interventions/${this.intervention.id}/mark-paid`, 'Intervention marquée payée');
        },
        async performAction(url, successTitle, payload = {}) {
            this.processing = true;

            try {
                await axios.post(url, payload);

                Swal.fire({
                    icon: 'success',
                    title: successTitle,
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.response?.data?.message ?? 'Une erreur est survenue.',
                });
            } finally {
                this.processing = false;
            }
        },
        async attachTicket() {
            if (!this.newTicketTicketId || this.processingTicketId) {
                return;
            }

            const ticketId = Number(this.newTicketTicketId);
            this.processingTicketId = ticketId;

            try {
                await axios.post(`/maintenance/interventions/${this.intervention.id}/attach-ticket`, {
                    maintenance_ticket_id: ticketId,
                    work_done: this.newTicketWork || null,
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket associé',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.newTicketTicketId = '';
                this.newTicketWork = '';

                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showTicketError(error);
            } finally {
                this.processingTicketId = null;
            }
        },
        async detachTicket(ticket) {
            if (this.processingTicketId) {
                return;
            }

            this.processingTicketId = ticket.id;

            try {
                await axios.post(`/maintenance/interventions/${this.intervention.id}/detach-ticket`, {
                    maintenance_ticket_id: ticket.id,
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket dissocié',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showTicketError(error);
            } finally {
                this.processingTicketId = null;
            }
        },
        async saveTicketDetails(ticket) {
            if (this.processingTicketId) {
                return;
            }

            this.processingTicketId = ticket.id;

            try {
                await axios.post(`/maintenance/interventions/${this.intervention.id}/attach-ticket`, {
                    maintenance_ticket_id: ticket.id,
                    work_done: this.ticketDetails[ticket.id],
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Travaux mis à jour',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showTicketError(error);
            } finally {
                this.processingTicketId = null;
            }
        },
        resetTicketDetails() {
            const details = {};
            (this.intervention?.tickets ?? []).forEach((ticket) => {
                details[ticket.id] = ticket.work_done ?? '';
            });

            this.ticketDetails = details;
        },
        openCostLine(line) {
            this.editingCostLineId = line.id;
            this.costForm = {
                cost_type: line.cost_type,
                label: line.label,
                quantity: line.quantity,
                unit_price: line.unit_price,
                notes: line.notes,
            };
        },
        async saveCostLine() {
            if (this.processingCostLine) {
                return;
            }

            const isEditing = Boolean(this.editingCostLineId);
            const method = isEditing ? 'put' : 'post';
            const url = isEditing
                ? `/maintenance/interventions/${this.intervention.id}/cost-lines/${this.editingCostLineId}`
                : `/maintenance/interventions/${this.intervention.id}/cost-lines`;

            this.processingCostLine = true;

            try {
                await axios[method](url, this.costForm);

                Swal.fire({
                    icon: 'success',
                    title: isEditing ? 'Ligne mise à jour' : 'Ligne ajoutée',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.resetCostForm();
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showCostLineError(error);
            } finally {
                this.processingCostLine = false;
            }
        },
        async confirmDeleteCostLine(line) {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Supprimer la ligne ?',
                text: 'Cette action est irréversible.',
                showCancelButton: true,
                confirmButtonText: 'Supprimer',
                cancelButtonText: 'Annuler',
            });

            if (!result.isConfirmed) {
                return;
            }

            await this.deleteCostLine(line);
        },
        async deleteCostLine(line) {
            if (this.processingCostLine) {
                return;
            }

            this.processingCostLine = true;

            try {
                await axios.delete(`/maintenance/interventions/${this.intervention.id}/cost-lines/${line.id}`);

                Swal.fire({
                    icon: 'success',
                    title: 'Ligne supprimée',
                    timer: 1400,
                    showConfirmButton: false,
                });

                if (this.editingCostLineId === line.id) {
                    this.resetCostForm();
                }

                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showCostLineError(error);
            } finally {
                this.processingCostLine = false;
            }
        },
        resetCostForm() {
            this.editingCostLineId = null;
            this.costForm = {
                cost_type: 'labor',
                label: '',
                quantity: 1,
                unit_price: 0,
                notes: '',
            };
        },
        applyStockItemDefaults() {
            const item = this.selectedStockItem;

            if (!item) {
                if (!this.permissions.can_override_unit_cost) {
                    this.stockForm.unit_cost = 0;
                }

                return;
            }

            const defaultPrice = Number(item.default_purchase_price ?? 0);

            if (!this.permissions.can_override_unit_cost) {
                this.stockForm.unit_cost = defaultPrice;
            } else if (!this.stockForm.unit_cost) {
                this.stockForm.unit_cost = defaultPrice;
            }
        },
        resetStockForm() {
            const defaultLocationId =
                this.intervention.stock_location?.id
                ?? this.availableStorageLocations[0]?.id
                ?? '';

            this.stockForm = {
                storage_location_id: defaultLocationId,
                stock_item_id: '',
                quantity: 1,
                unit_cost: 0,
                allow_negative_stock: false,
            };
        },
        async addStockItem() {
            if (!this.canAddStockItems || this.processingStockItem) {
                return;
            }

            if (!this.stockForm.storage_location_id || !this.stockForm.stock_item_id) {
                return;
            }

            this.processingStockItem = true;

            try {
                await axios.post(`/maintenance/interventions/${this.intervention.id}/items`, this.stockForm);

                Swal.fire({
                    icon: 'success',
                    title: 'Pièce enregistrée',
                    timer: 1400,
                    showConfirmButton: false,
                });

                this.resetStockForm();
                this.$inertia.reload({ preserveState: true });
            } catch (error) {
                this.showStockItemError(error);
            } finally {
                this.processingStockItem = false;
            }
        },
        showStockItemError(error) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.response?.data?.message ?? 'Impossible d’ajouter la pièce.',
            });
        },
        showCostLineError(error) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.response?.data?.message ?? 'Impossible de modifier les coûts.',
            });
        },
        showTicketError(error) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.response?.data?.message ?? 'Impossible de modifier les tickets.',
            });
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits nécessaires.',
            });
        },
    },
    watch: {
        'intervention.tickets': {
            handler() {
                this.resetTicketDetails();
            },
            deep: true,
        },
        'intervention.costs': {
            handler() {
                this.resetCostForm();
            },
            deep: true,
        },
        'intervention.items': {
            handler() {
                this.resetStockForm();
            },
            deep: true,
        },
            availableStorageLocations() {
                this.resetStockForm();
            },
            'intervention.stock_location'() {
                this.resetStockForm();
            },
            'stockForm.stock_item_id'() {
                this.applyStockItemDefaults();
            },
            availableStockItems() {
                this.applyStockItemDefaults();
            },
            'permissions.can_override_unit_cost'() {
                this.applyStockItemDefaults();
            },
        },
};
</script>
