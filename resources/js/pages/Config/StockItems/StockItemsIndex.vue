<template>
    <ConfigLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-serena-text-main">Articles</h1>
                    <p class="text-sm text-serena-text-muted">Gérez les articles stockables.</p>
                </div>
                <PrimaryButton v-if="canManage" type="button" class="px-4 py-2" @click="openModal()">
                    Nouvel article
                </PrimaryButton>
            </div>

            <div class="overflow-hidden rounded-xl border border-serena-border bg-white shadow-sm">
                <table class="min-w-full divide-y divide-serena-border text-sm">
                    <thead class="bg-serena-bg-soft/80 text-left text-[11px] font-semibold uppercase tracking-wide text-serena-text-muted">
                        <tr>
                            <th class="px-4 py-3">Nom</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Unité</th>
                            <th class="px-4 py-3">Catégorie</th>
                            <th class="px-4 py-3 text-right">Prix unitaire</th>
                            <th class="px-4 py-3 text-right">Seuil</th>
                            <th class="px-4 py-3">Actif</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-serena-border text-serena-text-main">
                        <tr v-for="item in stockItems" :key="item.id" class="hover:bg-serena-bg-soft">
                            <td class="px-4 py-3 font-semibold">{{ item.name }}</td>
                            <td class="px-4 py-3 text-xs text-serena-text-muted">{{ item.sku || '—' }}</td>
                            <td class="px-4 py-3">{{ item.unit }}</td>
                            <td class="px-4 py-3">{{ item.category }}</td>
                            <td class="px-4 py-3 text-right font-semibold">
                                {{ formatAmount(item.default_purchase_price, item.currency) }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ formatQuantity(item.reorder_point) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold"
                                    :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ item.is_active ? 'Oui' : 'Non' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-serena-text-muted">
                                <SecondaryButton v-if="canManage" type="button" class="px-3 py-1 text-xs" @click="openModal(item)">
                                    Éditer
                                </SecondaryButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 sm:items-center">
            <div class="w-full max-w-2xl rounded-2xl border border-serena-border bg-white p-6 shadow-xl" @click.stop>
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-serena-text-main">
                            {{ editing ? 'Modifier un article' : 'Nouvel article' }}
                        </h2>
                        <p class="text-sm text-serena-text-muted">Créez ou modifiez un article stockable.</p>
                    </div>
                    <button type="button" class="text-sm text-serena-text-muted" @click="closeModal">Fermer</button>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium text-serena-text-muted">
                            Nom
                            <input v-model="form.name" type="text" required class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            SKU
                            <input v-model="form.sku" type="text" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Unité
                            <input v-model="form.unit" type="text" required class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Catégorie
                            <input v-model="form.item_category" type="text" required class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Prix unitaire
                            <input v-model.number="form.default_purchase_price" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Devise
                            <input v-model="form.currency" type="text" maxlength="3" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="text-sm font-medium text-serena-text-muted">
                            Seuil de réapprovisionnent
                            <input v-model.number="form.reorder_point" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none" />
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-serena-text-muted">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary" />
                            Active
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-serena-text-muted">
                            <input
                                v-model="form.is_kit"
                                @change="!form.is_kit && (form.components = [])"
                                type="checkbox"
                                class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                            />
                            Est un kit ?
                        </label>
                    </div>
                    <div v-if="form.is_kit" class="rounded-2xl border border-serena-border/80 bg-serena-bg-soft px-4 py-4">
                        <p class="text-sm font-semibold text-serena-text-main">Composition du kit</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-[2fr,1fr,auto]">
                            <label class="text-sm font-medium text-serena-text-muted">
                                Article
                                <Multiselect
                                    v-model="kitComponentSelection"
                                    :options="componentOptions"
                                    label="name"
                                    track-by="id"
                                    placeholder="Sélectionner un article"
                                    :clear-on-select="true"
                                    :close-on-select="true"
                                    class="mt-1"
                                />
                            </label>
                            <label class="text-sm font-medium text-serena-text-muted">
                                Quantité
                                <input
                                    v-model.number="kitComponentQuantity"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="mt-1 w-full rounded-xl border border-serena-border px-3 py-2 focus:border-serena-primary focus:outline-none"
                                />
                            </label>
                            <div class="flex items-end">
                                <PrimaryButton type="button" class="w-full" @click="addComponent">Ajouter</PrimaryButton>
                            </div>
                        </div>
                        <div v-if="form.components.length" class="mt-4 space-y-2">
                            <div
                                v-for="(component, index) in form.components"
                                :key="component.stock_item_id"
                                class="flex items-center justify-between rounded-xl border border-serena-border bg-white px-3 py-2 text-sm"
                            >
                                <div>
                                    <p class="font-semibold text-serena-text-main">{{ componentLabel(component) }}</p>
                                    <p class="text-xs text-serena-text-muted">Quantité : {{ formatQuantity(component.quantity) }}</p>
                                </div>
                                <button type="button" class="text-xs font-semibold text-serena-danger hover:underline" @click="removeComponent(index)">Supprimer</button>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-serena-text-muted">
                            Les kits sont automatiquement décomposés en articles simples lors des mouvements de stock et facturation.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton type="button" class="px-4 py-2 text-xs" @click="closeModal">Annuler</SecondaryButton>
                        <PrimaryButton type="submit" class="px-4 py-2 text-xs" :disabled="submitting">
                            {{ submitting ? 'Enregistrement…' : editing ? 'Mettre à jour' : 'Enregistrer' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'StockItemsIndex',
    components: { ConfigLayout, PrimaryButton, SecondaryButton, Multiselect },
    props: {
        stockItems: {
            type: Array,
            default: () => [],
        },
        componentOptions: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            showModal: false,
            editing: null,
            submitting: false,
            form: this.resetForm(),
            kitComponentSelection: null,
            kitComponentQuantity: 1,
        };
    },
    computed: {
        canManage() {
            return Boolean((this.$page?.props?.auth?.can ?? {})['stock_items_manage']);
        },
    },
    methods: {
        formatAmount(value, currency = 'XAF') {
            const amount = Number(value || 0);
            return `${amount.toFixed(0)} ${currency}`;
        },
        formatQuantity(value) {
            const quantity = Number(value ?? 0);
            return quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
        },
        componentLabel(component) {
            if (!component) {
                return '';
            }

            const option = this.componentOptions.find((item) => item.id === component.stock_item_id);
            if (option) {
                return option.name;
            }

            return component.name ?? 'Article';
        },
        prepareComponents(components) {
            return components.map((component) => ({
                stock_item_id: component.stock_item_id,
                quantity: component.quantity,
                name: component.name ?? this.componentOptions.find((item) => item.id === component.stock_item_id)?.name ?? '',
            }));
        },
        addComponent() {
            if (!this.kitComponentSelection || this.kitComponentQuantity <= 0) {
                return;
            }

            const existing = this.form.components.find(
                (component) => component.stock_item_id === this.kitComponentSelection.id,
            );

            if (existing) {
                existing.quantity += this.kitComponentQuantity;
            } else {
                this.form.components.push({
                    stock_item_id: this.kitComponentSelection.id,
                    quantity: this.kitComponentQuantity,
                    name: this.kitComponentSelection.name,
                });
            }

            this.kitComponentSelection = null;
            this.kitComponentQuantity = 1;
        },
        removeComponent(index) {
            this.form.components.splice(index, 1);
        },
        openModal(item = null) {
            this.editing = item;
            this.form = item
                ? {
                    name: item.name,
                    sku: item.sku,
                    unit: item.unit,
                    item_category: item.category,
                    default_purchase_price: item.default_purchase_price,
                    currency: item.currency,
                    reorder_point: item.reorder_point,
                    is_active: item.is_active,
                    is_kit: item.is_kit,
                    components: this.prepareComponents(item.components ?? []),
                }
                : this.resetForm();
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editing = null;
            this.form = this.resetForm();
            this.kitComponentSelection = null;
            this.kitComponentQuantity = 1;
        },
        resetForm() {
            return {
                name: '',
                sku: '',
                unit: '',
                item_category: 'maintenance',
                default_purchase_price: 0,
                currency: 'XAF',
                reorder_point: 0,
                is_active: true,
                is_kit: false,
                components: [],
            };
        },
        async submitForm() {
            if (this.submitting) {
                return;
            }

            this.submitting = true;

            try {
                const payload = {
                    ...this.form,
                    components: this.form.is_kit
                        ? this.form.components.map((component) => ({
                            stock_item_id: component.stock_item_id,
                            quantity: component.quantity,
                        }))
                        : [],
                };

                if (this.editing) {
                    await router.put(`/settings/resources/stock-items/${this.editing.id}`, payload, {
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                } else {
                    await router.post('/settings/resources/stock-items', payload, {
                        preserveState: true,
                        onSuccess: () => this.closeModal(),
                    });
                }
            } catch (error) {
                console.error(error);
            } finally {
                this.submitting = false;
            }
        },
    },
};
</script>
