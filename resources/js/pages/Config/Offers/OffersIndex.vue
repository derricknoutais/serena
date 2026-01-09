<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Offres</h1>
                <p class="text-sm text-gray-500">Offres et packages.</p>
            </div>
            <PrimaryButton
                v-if="canCreate"
                type="button"
                class="px-4 py-2"
                @click="openCreateModal"
            >
                Nouvelle offre
            </PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Type d’offre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Mode de facturation</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Active</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="offer in offers.data" :key="offer.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ offer.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ kindLabels[offer.kind] ?? offer.kind }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ billingModeLabels[offer.billing_mode] ?? offer.billing_mode }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                :class="offer.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ offer.is_active ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-3 text-sm text-gray-600">
                            <SecondaryButton
                                v-if="canUpdate"
                                type="button"
                                class="px-2 py-1 text-xs"
                                @click="openEditModal(offer)"
                            >
                                Éditer
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="canDelete"
                                type="button"
                                variant="danger"
                                class="px-2 py-1 text-xs bg-serena-danger"
                                @click="destroy(offer.id)"
                            >
                                Supprimer
                            </PrimaryButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                Pagination à ajouter selon vos besoins.
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 px-4 py-10 sm:items-center"
            @click.self="closeModal"
        >
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ isEditing ? 'Modifier l’offre' : 'Nouvelle offre' }}</h2>
                        <p class="text-sm text-gray-500">Renseignez les informations de l’offre.</p>
                    </div>
                    <SecondaryButton type="button" class="text-sm" @click="closeModal">Fermer</SecondaryButton>
                </div>

                <Form
                    ref="offerForm"
                    :key="formKey"
                    :initial-values="form"
                    @submit="handleSubmit"
                    class="space-y-4"
                >
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <span
                            :class="['rounded-full px-3 py-1', currentStep === 1 ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700']"
                        >
                            1. Détails
                        </span>
                        <span
                            :class="['rounded-full px-3 py-1', currentStep === 2 ? 'bg-serena-primary text-white' : 'bg-gray-100 text-gray-700']"
                        >
                            2. Tarifs & activation
                        </span>
                    </div>

                    <div v-if="step1Errors.length" class="rounded-lg border border-serena-danger/30 bg-red-50 px-3 py-2 text-xs text-red-700">
                        Merci de corriger : {{ step1Errors.join(' | ') }}.
                    </div>

                    <div v-show="currentStep === 1" class="grid gap-4 md:grid-cols-2">
                        <Field name="name" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Nom <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-bind="field"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    Champ requis.
                                </p>
                                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                                </div>
                            </Field>

                        <!-- champ code supprimé -->

                        <Field name="kind" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Type <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.kind"
                                    @update:modelValue="(val) => { field.onChange(val); form.kind = val; }"
                                    :options="kindOptionsNormalized"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un type"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="kind" />
                                </p>
                                    <p v-if="errors.kind" class="mt-1 text-xs text-red-600">{{ errors.kind }}</p>
                                </div>
                            </Field>

                        <Field name="billing_mode" rules="required" v-slot="{ field, meta }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">
                                    Mode de facturation <span class="text-red-600">*</span>
                                </label>
                                <Multiselect
                                    :model-value="field.value ?? form.billing_mode"
                                    @update:modelValue="(val) => { field.onChange(val); form.billing_mode = val; }"
                                    :options="billingModeOptions"
                                    label="label"
                                    track-by="value"
                                    placeholder="Sélectionner un mode"
                                    :allow-empty="false"
                                    class="mt-1"
                                />
                                <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                                    <ErrorMessage name="billing_mode" />
                                </p>
                                    <p v-if="errors.billing_mode" class="mt-1 text-xs text-red-600">{{ errors.billing_mode }}</p>
                                </div>
                            </Field>

                        <div class="md:col-span-2 mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-gray-800">
                                Règle de temps de l’offre
                            </h3>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Type de règle</label>
                                    <select
                                        v-model="form.time_rule"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    >
                                        <option :value="null">Non configuré</option>
                                        <option
                                            v-for="opt in timeRuleOptions"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="form.time_rule === 'rolling'">
                                    <label class="text-sm font-medium text-gray-700">Durée (heures)</label>
                                    <input
                                        v-model.number="timeConfigDraft.duration_hours"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Exemple : 3h, 6h, 24h.
                                    </p>
                                </div>

                                <div v-else-if="form.time_rule === 'fixed_window'" class="md:col-span-2 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Heure de début</label>
                                        <input
                                            v-model="timeConfigDraft.start_time"
                                            type="time"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Heure de fin</label>
                                        <input
                                            v-model="timeConfigDraft.end_time"
                                            type="time"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        />
                                    </div>
                                    <p class="md:col-span-2 mt-1 text-xs text-gray-500">
                                        Si l’heure de fin est avant l’heure de début, la sortie se fait le lendemain (plage de nuit).
                                    </p>
                                </div>

                                <div v-else-if="form.time_rule === 'fixed_checkout'" class="md:col-span-2 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Heure de départ</label>
                                        <input
                                            v-model="timeConfigDraft.checkout_time"
                                            type="time"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Nombre de nuits</label>
                                        <input
                                            v-model.number="timeConfigDraft.day_offset"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Heure limite arrivée (cutoff)</label>
                                        <input
                                            v-model="timeConfigDraft.night_cutoff_time"
                                            type="time"
                                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">
                                            Si l’arrivée est avant cette heure, la nuitée est rattachée à la veille (sortie à l’heure de checkout le même jour).
                                        </p>
                                    </div>
                                    <p class="md:col-span-2 mt-1 text-xs text-gray-500">
                                        Exemple : arrivée aujourd’hui après l’heure de cutoff, départ le jour suivant à 12:00.
                                    </p>
                                </div>

                                <div v-else-if="form.time_rule === 'weekend_window'" class="md:col-span-2 space-y-3">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Jours d’arrivée autorisés</label>
                                            <Multiselect
                                                :model-value="timeConfigDraft.checkin.allowed_weekdays"
                                                @update:modelValue="(val) => { timeConfigDraft.checkin.allowed_weekdays = val; }"
                                                :options="weekdayOptions"
                                                label="label"
                                                track-by="value"
                                                placeholder="Sélectionner les jours"
                                                :multiple="true"
                                                :close-on-select="false"
                                                :allow-empty="true"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Heure minimale d’arrivée</label>
                                            <input
                                                v-model="timeConfigDraft.checkin.start_time"
                                                type="time"
                                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Heure de départ</label>
                                            <input
                                                v-model="timeConfigDraft.checkout.time"
                                                type="time"
                                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Nombre de jours après l’arrivée</label>
                                            <input
                                                v-model.number="timeConfigDraft.checkout.max_days_after_checkin"
                                                type="number"
                                                min="1"
                                                step="1"
                                                class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                            />
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Exemple : entrée ven/sam dès {{ timeConfigDraft.checkin.start_time || '12:00' }} → sortie +{{ timeConfigDraft.checkout.max_days_after_checkin || 2 }} jours à {{ timeConfigDraft.checkout.time || '12:00' }}.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-gray-800">
                                Départ tardif (par offre)
                            </h3>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Politique</label>
                                    <select
                                        v-model="timeConfigDraft.late_checkout.policy"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    >
                                        <option value="inherit">Utiliser la politique de l’hôtel</option>
                                        <option value="free">Toléré (gratuit)</option>
                                        <option value="paid">Payant</option>
                                        <option value="forbidden">Interdit</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Tolérance (minutes)</label>
                                    <input
                                        v-model.number="timeConfigDraft.late_checkout.grace_minutes"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                                <div v-if="timeConfigDraft.late_checkout.policy === 'paid'">
                                    <label class="text-sm font-medium text-gray-700">Type de frais</label>
                                    <select
                                        v-model="timeConfigDraft.late_checkout.fee_type"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    >
                                        <option value="flat">Montant fixe</option>
                                        <option value="per_hour">Par heure</option>
                                        <option value="per_day">Par jour</option>
                                        <option value="percent">Pourcentage</option>
                                    </select>
                                </div>
                                <div v-if="timeConfigDraft.late_checkout.policy === 'paid'">
                                    <label class="text-sm font-medium text-gray-700">Valeur des frais</label>
                                    <input
                                        v-model.number="timeConfigDraft.late_checkout.fee_value"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Exemple : tolérance 15 min, puis frais si le client dépasse l’heure prévue.
                            </p>
                        </div>

                        <Field name="valid_from" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Valide à partir du</label>
                                <input
                                    v-bind="field"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="valid_from" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="valid_to" v-slot="{ field }">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Valide jusqu’au</label>
                                <input
                                    v-bind="field"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                />
                                <ErrorMessage name="valid_to" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>

                        <Field name="description" v-slot="{ field }">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    v-bind="field"
                                    rows="3"
                                    class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                ></textarea>
                                <ErrorMessage name="description" class="mt-1 text-xs text-red-600" />
                            </div>
                        </Field>
                    </div>

                    <div v-show="currentStep === 2" class="space-y-4">
                        <div class="flex items-center gap-2">
                            <Field name="is_active" type="checkbox" v-slot="{ field }">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input
                                        v-bind="field"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    Activer l’offre
                                </label>
                            </Field>
                        </div>

                        <div class="mt-2 rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-gray-800">
                                Tarifs par type de chambre
                            </h3>
                            <p class="mb-3 text-xs text-gray-500">
                                Vous pouvez saisir un prix par type de chambre. Ces tarifs seront utilisés pour pré-remplir les réservations.
                            </p>
                            <div class="max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Type de chambre</th>
                                            <th class="px-3 py-2 text-right">Prix (XAF)</th>
                                            <th class="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="rtPrice in roomTypePrices"
                                            :key="rtPrice.room_type_id"
                                            class="border-t text-gray-700"
                                        >
                                            <td class="px-3 py-1.5">
                                                {{ rtPrice.room_type_name }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right">
                                                <input
                                                    v-model.number="rtPrice.price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="w-28 rounded-md border border-gray-200 px-2 py-1 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-100"
                                                />
                                            </td>
                                            <td class="px-3 py-1.5 text-right">
                                                <button
                                                    type="button"
                                                    class="text-[11px] font-semibold text-serena-danger hover:underline"
                                                    @click="removeRoomTypePrice(rtPrice.room_type_id)"
                                                >
                                                    Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold text-gray-600">Ajouter un type de chambre</label>
                                    <Multiselect
                                        :model-value="newPriceRoomType"
                                        @update:modelValue="(val) => { newPriceRoomType = val; }"
                                        :options="availableRoomTypeOptions"
                                        label="label"
                                        track-by="value"
                                        placeholder="Sélectionner"
                                        :allow-empty="false"
                                        class="mt-1 w-56"
                                    />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold text-gray-600">Prix</label>
                                    <input
                                        v-model.number="newPriceValue"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-32 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                                <PrimaryButton
                                    type="button"
                                    class="px-4 py-2 text-sm"
                                    :disabled="!newPriceRoomType || newPriceValue === '' || Number.isNaN(Number(newPriceValue))"
                                    @click="addRoomTypePrice"
                                >
                                    Ajouter
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <SecondaryButton type="button" class="text-sm" @click="closeModal">Annuler</SecondaryButton>
                        <SecondaryButton
                            v-if="currentStep === 2"
                            type="button"
                            class="text-sm"
                            @click="goToStep(1)"
                        >
                            Retour
                        </SecondaryButton>
                        <PrimaryButton
                            v-if="currentStep === 1"
                            type="button"
                            class="px-4 py-2 text-sm"
                            @click="goToStep(2)"
                        >
                            Suivant
                        </PrimaryButton>
                        <PrimaryButton
                            v-else
                            type="submit"
                            class="px-4 py-2 text-sm"
                            :disabled="submitting"
                        >
                            <span v-if="submitting">Enregistrement…</span>
                            <span v-else>Enregistrer</span>
                        </PrimaryButton>
                    </div>
                </Form>
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import SecondaryButton from '@/components/SecondaryButton.vue';

export default {
    name: 'OffersIndex',
    components: { ConfigLayout, Form, Field, ErrorMessage, PrimaryButton, SecondaryButton },
    props: {
        offers: {
            type: Object,
            required: true,
        },
        kindOptions: {
            type: Array,
            required: true,
        },
        billingModes: {
            type: Array,
            required: true,
        },
        dayOptions: {
            type: Array,
            required: true,
        },
        roomTypes: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            showModal: false,
            isEditing: false,
            submitting: false,
            editId: null,
            formKey: 0,
            currentStep: 1,
            step1Errors: [],
            form: {
                name: '',
                kind: null,
                billing_mode: null,
                time_rule: null,
                valid_from: '',
                valid_to: '',
                is_active: true,
            },
            timeRuleOptions: [
                { label: 'Durée glissante (H/M)', value: 'rolling' },
                { label: 'Plage fixe (22:00 → 08:00)', value: 'fixed_window' },
                { label: 'Départ fixe (type nuit)', value: 'fixed_checkout' },
                { label: 'Formule week-end (H48/H72)', value: 'weekend_window' },
            ],
            timeConfigDraft: {
                duration_hours: 24,
                start_time: '22:00',
                end_time: '08:00',
                checkout_time: '12:00',
                day_offset: 1,
                checkin: {
                    allowed_weekdays: [5, 6],
                    start_time: '12:00',
                },
                checkout: {
                    time: '12:00',
                    max_days_after_checkin: 2,
                },
                late_checkout: {
                    policy: 'inherit',
                    grace_minutes: 0,
                    fee_type: 'flat',
                    fee_value: 0,
                },
            },
            roomTypePrices: [],
            newPriceRoomType: null,
            newPriceValue: '',
        };
    },
    computed: {
        kindOptionsNormalized() {
            return this.kindOptions.map((k) => ({
                label: this.kindLabels[k] ?? k,
                value: k,
            }));
        },
        billingModeOptions() {
            return this.billingModes.map((k) => ({
                label: this.billingModeLabels[k] ?? k,
                value: k,
            }));
        },
        dayOptionsNormalized() {
            const mapDayToNumber = {
                mon: 1,
                tue: 2,
                wed: 3,
                thu: 4,
                fri: 5,
                sat: 6,
                sun: 7,
            };

            return this.dayOptions.map((k) => ({
                label: k,
                value: mapDayToNumber[k] ?? k,
            }));
        },
        weekdayOptions() {
            return [
                { label: 'Lundi', value: 1 },
                { label: 'Mardi', value: 2 },
                { label: 'Mercredi', value: 3 },
                { label: 'Jeudi', value: 4 },
                { label: 'Vendredi', value: 5 },
                { label: 'Samedi', value: 6 },
                { label: 'Dimanche', value: 7 },
            ];
        },
        selectedRoomTypeIds() {
            return this.roomTypePrices.map((p) => p.room_type_id);
        },
        availableRoomTypeOptions() {
            const selected = this.selectedRoomTypeIds;

            return (this.roomTypes || [])
                .filter((rt) => !selected.includes(rt.id))
                .map((rt) => ({
                    label: rt.name,
                    value: rt.id,
                }));
        },
        kindLabels() {
            return {
                hourly: 'Tarif horaire',
                night: 'Nuitée',
                day: 'Journée',
                package: 'Package',
            };
        },
        billingModeLabels() {
            return {
                fixed: 'Prix fixe',
                per_night: 'Par nuit',
                per_hour: 'Par heure',
            };
        },
        errors() {
            return this.$page.props.errors || {};
        },
        canCreate() {
            return this.$page.props.auth?.can?.offers_create ?? false;
        },
        canUpdate() {
            return this.$page.props.auth?.can?.offers_update ?? false;
        },
        canDelete() {
            return this.$page.props.auth?.can?.offers_delete ?? false;
        },
    },
    methods: {
        syncFormFromFields() {
            const values = this.$refs.offerForm?.values || {};

            this.form = {
                ...this.form,
                ...values,
                kind: values.kind ?? this.form.kind,
                billing_mode: values.billing_mode ?? this.form.billing_mode,
                valid_from: values.valid_from ?? this.form.valid_from,
                valid_to: values.valid_to ?? this.form.valid_to,
                description: values.description ?? this.form.description,
                is_active: values.is_active ?? this.form.is_active,
            };
        },
        goToStep(step) {
            this.syncFormFromFields();

            if (step === 2) {
                const missing = [];
                if (!this.form.name) {
                    missing.push('Nom');
                }
                if (!this.form.kind) {
                    missing.push('Type');
                }
                if (!this.form.billing_mode) {
                    missing.push('Mode de facturation');
                }

                this.step1Errors = missing;

                if (missing.length) {
                    return;
                }
            } else {
                this.step1Errors = [];
            }

            this.currentStep = step;
        },
        updateStep1ErrorsFromServer() {
            const serverErrors = this.errors || {};

            this.step1Errors = Object.values(serverErrors)
                .map((message) => (Array.isArray(message) ? message[0] : message))
                .filter(Boolean);

            if (this.step1Errors.length) {
                this.currentStep = 1;
            }
        },
        openCreateModal() {
            if (!this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = false;
            this.editId = null;
            this.resetForm();
            this.initializeRoomTypePrices();
            this.formKey += 1;
            this.currentStep = 1;
            this.step1Errors = [];
            this.showModal = true;
        },
        openEditModal(offer) {
            if (!this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.isEditing = true;
            this.editId = offer.id;
            this.form = {
                name: offer.name || '',
                kind: this.kindOptionsNormalized.find((opt) => opt.value === offer.kind) ?? null,
                billing_mode: this.billingModeOptions.find((opt) => opt.value === offer.billing_mode) ?? null,
                time_rule: offer.time_rule || null,
                valid_from: offer.valid_from || '',
                valid_to: offer.valid_to || '',
                is_active: !!offer.is_active,
            };
            this.initializeTimeConfigDraft(offer);
            this.initializeRoomTypePrices(offer);
            this.formKey += 1;
            this.currentStep = 1;
            this.step1Errors = [];
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.resetForm();
            this.currentStep = 1;
            this.step1Errors = [];
        },
        resetForm() {
            this.form = {
                name: '',
                kind: null,
                billing_mode: null,
                time_rule: null,
                valid_from: '',
                valid_to: '',
                is_active: true,
            };
            this.roomTypePrices = [];
            this.newPriceRoomTypeId = null;
            this.newPriceValue = '';
            this.resetTimeConfigDraft();
        },
        initializeRoomTypePrices(offer = null) {
            const pricesByRoomType = {};

            if (offer && Array.isArray(offer.prices)) {
                offer.prices.forEach((p) => {
                    pricesByRoomType[p.room_type_id] = Number(p.price || 0);
                });
            }

            this.roomTypePrices = (this.roomTypes || [])
                .filter((rt) => Object.prototype.hasOwnProperty.call(pricesByRoomType, rt.id))
                .map((rt) => ({
                    room_type_id: rt.id,
                    room_type_name: rt.name,
                    price: Object.prototype.hasOwnProperty.call(pricesByRoomType, rt.id)
                        ? pricesByRoomType[rt.id]
                        : '',
                }));

            if (!this.roomTypePrices.length) {
                this.roomTypePrices = (this.roomTypes || []).map((rt) => ({
                    room_type_id: rt.id,
                    room_type_name: rt.name,
                    price: '',
                }));
            }
        },
        resetTimeConfigDraft() {
            this.timeConfigDraft = {
                duration_hours: 24,
                start_time: '22:00',
                end_time: '08:00',
                checkout_time: '12:00',
                day_offset: 1,
                checkin: {
                    allowed_weekdays: this.weekdayOptions.filter((opt) => [5, 6].includes(opt.value)),
                    start_time: '12:00',
                },
                checkout: {
                    time: '12:00',
                    max_days_after_checkin: 2,
                },
                night_cutoff_time: '',
                late_checkout: {
                    policy: 'inherit',
                    grace_minutes: 0,
                    fee_type: 'flat',
                    fee_value: 0,
                },
            };
        },
        addRoomTypePrice() {
            if (!this.newPriceRoomType) {
                return;
            }

            const roomTypeId = this.newPriceRoomType?.value ?? null;
            if (!roomTypeId) {
                return;
            }

            const roomType = (this.roomTypes || []).find((rt) => rt.id === roomTypeId);

            if (!roomType) {
                return;
            }

            if (this.selectedRoomTypeIds.includes(roomType.id)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Déjà présent',
                    text: 'Ce type de chambre est déjà dans la liste.',
                });

                return;
            }

            this.roomTypePrices.push({
                room_type_id: roomType.id,
                room_type_name: roomType.name,
                price: Number.isNaN(Number(this.newPriceValue)) ? '' : Number(this.newPriceValue),
            });

            this.newPriceRoomType = null;
            this.newPriceValue = '';
        },
        removeRoomTypePrice(roomTypeId) {
            this.roomTypePrices = this.roomTypePrices.filter((p) => p.room_type_id !== roomTypeId);
        },
        initializeTimeConfigDraft(offer) {
            this.resetTimeConfigDraft();

            if (!offer || !offer.time_rule || !offer.time_config) {
                return;
            }

            const cfg = offer.time_config || {};

            if (offer.time_rule === 'rolling') {
                this.timeConfigDraft.duration_hours = Math.max(
                    1,
                    Math.round((cfg.duration_minutes || 60) / 60),
                );
            } else if (offer.time_rule === 'fixed_window') {
                this.timeConfigDraft.start_time = cfg.start_time || '22:00';
                this.timeConfigDraft.end_time = cfg.end_time || '08:00';
            } else if (offer.time_rule === 'fixed_checkout') {
                this.timeConfigDraft.checkout_time = cfg.checkout_time || '12:00';
                this.timeConfigDraft.day_offset = cfg.day_offset || 1;
                this.timeConfigDraft.night_cutoff_time = cfg.night_cutoff_time || '';
            } else if (offer.time_rule === 'weekend_window') {
                const allowedWeekdays = Array.isArray(cfg.checkin?.allowed_weekdays)
                    ? cfg.checkin.allowed_weekdays
                    : [5, 6];

                this.timeConfigDraft.checkin.allowed_weekdays = allowedWeekdays
                    .map((day) => this.weekdayOptions.find((opt) => opt.value === day) ?? null)
                    .filter((opt) => opt !== null);
                this.timeConfigDraft.checkin.start_time = cfg.checkin?.start_time || '12:00';
                this.timeConfigDraft.checkout.time = cfg.checkout?.time || '12:00';
                this.timeConfigDraft.checkout.max_days_after_checkin = cfg.checkout?.max_days_after_checkin || 2;
            }

            if (cfg.late_checkout) {
                this.timeConfigDraft.late_checkout.policy = cfg.late_checkout.policy || 'inherit';
                this.timeConfigDraft.late_checkout.grace_minutes = Number(cfg.late_checkout.grace_minutes || 0);
                this.timeConfigDraft.late_checkout.fee_type = cfg.late_checkout.fee_type || 'flat';
                this.timeConfigDraft.late_checkout.fee_value = Number(cfg.late_checkout.fee_value || 0);
            }
        },
        buildTimeConfigPayload() {
            if (!this.form.time_rule) {
                return null;
            }

            const lateCheckoutPolicy = this.timeConfigDraft.late_checkout.policy;

            if (this.form.time_rule === 'rolling') {
                const minutes = Math.max(1, Number(this.timeConfigDraft.duration_hours || 0)) * 60;

                return {
                    duration_minutes: minutes,
                    late_checkout: lateCheckoutPolicy === 'inherit'
                        ? null
                        : {
                            policy: lateCheckoutPolicy,
                            grace_minutes: Math.max(0, Number(this.timeConfigDraft.late_checkout.grace_minutes || 0)),
                            fee_type: this.timeConfigDraft.late_checkout.fee_type || 'flat',
                            fee_value: Number(this.timeConfigDraft.late_checkout.fee_value || 0),
                        },
                };
            }

            if (this.form.time_rule === 'fixed_window') {
                return {
                    start_time: this.timeConfigDraft.start_time,
                    end_time: this.timeConfigDraft.end_time,
                    late_checkout: lateCheckoutPolicy === 'inherit'
                        ? null
                        : {
                            policy: lateCheckoutPolicy,
                            grace_minutes: Math.max(0, Number(this.timeConfigDraft.late_checkout.grace_minutes || 0)),
                            fee_type: this.timeConfigDraft.late_checkout.fee_type || 'flat',
                            fee_value: Number(this.timeConfigDraft.late_checkout.fee_value || 0),
                        },
                };
            }

            if (this.form.time_rule === 'fixed_checkout') {
                return {
                    checkout_time: this.timeConfigDraft.checkout_time,
                    day_offset: Math.max(1, Number(this.timeConfigDraft.day_offset || 1)),
                    night_cutoff_time: this.timeConfigDraft.night_cutoff_time || null,
                    late_checkout: lateCheckoutPolicy === 'inherit'
                        ? null
                        : {
                            policy: lateCheckoutPolicy,
                            grace_minutes: Math.max(0, Number(this.timeConfigDraft.late_checkout.grace_minutes || 0)),
                            fee_type: this.timeConfigDraft.late_checkout.fee_type || 'flat',
                            fee_value: Number(this.timeConfigDraft.late_checkout.fee_value || 0),
                        },
                };
            }

            if (this.form.time_rule === 'weekend_window') {
                return {
                    checkin: {
                        allowed_weekdays: Array.isArray(this.timeConfigDraft.checkin.allowed_weekdays)
                            ? this.timeConfigDraft.checkin.allowed_weekdays.map((d) => d?.value ?? d)
                            : [],
                        start_time: this.timeConfigDraft.checkin.start_time,
                    },
                    checkout: {
                        time: this.timeConfigDraft.checkout.time,
                        max_days_after_checkin: Math.max(
                            1,
                            Number(this.timeConfigDraft.checkout.max_days_after_checkin || 1),
                        ),
                    },
                    late_checkout: lateCheckoutPolicy === 'inherit'
                        ? null
                        : {
                            policy: lateCheckoutPolicy,
                            grace_minutes: Math.max(0, Number(this.timeConfigDraft.late_checkout.grace_minutes || 0)),
                            fee_type: this.timeConfigDraft.late_checkout.fee_type || 'flat',
                            fee_value: Number(this.timeConfigDraft.late_checkout.fee_value || 0),
                        },
                };
            }

            return null;
        },
        handleSubmit(values) {
            this.syncFormFromFields();

            if (!this.isEditing && !this.canCreate) {
                this.showUnauthorizedAlert();

                return;
            }

            if (this.isEditing && !this.canUpdate) {
                this.showUnauthorizedAlert();

                return;
            }
            this.submitting = true;
            const payload = {
                ...values,
                kind: values.kind?.value ?? values.kind,
                billing_mode: values.billing_mode?.value ?? values.billing_mode,
                time_rule: this.form.time_rule || null,
                valid_from: values.valid_from || null,
                valid_to: values.valid_to || null,
                is_active: !!values.is_active,
                time_config: this.buildTimeConfigPayload(),
                prices: this.roomTypePrices
                    .filter((p) => p.price !== '' && !Number.isNaN(Number(p.price)))
                    .map((p) => ({
                        room_type_id: p.room_type_id,
                        price: Number(p.price),
                    })),
            };
            const url = this.isEditing ? `/settings/resources/offers/${this.editId}` : '/settings/resources/offers';

            if (this.isEditing) {
                router.put(
                    url,
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            if (Object.keys(this.errors).length) {
                                this.updateStep1ErrorsFromServer();

                                return;
                            }

                            this.closeModal();
                        },
                        onError: () => {
                            this.submitting = false;
                            this.updateStep1ErrorsFromServer();
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            } else {
                router.post(
                    url,
                    payload,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            if (Object.keys(this.errors).length) {
                                this.updateStep1ErrorsFromServer();

                                return;
                            }

                            this.closeModal();
                        },
                        onError: () => {
                            this.submitting = false;
                            this.updateStep1ErrorsFromServer();
                        },
                        onFinish: () => {
                            this.submitting = false;
                        },
                    },
                );
            }
        },
        destroy(id) {
            if (!this.canDelete) {
                this.showUnauthorizedAlert();

                return;
            }
            Swal.fire({
                title: 'Supprimer cette offre ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    router.delete(`/settings/resources/offers/${id}`, { preserveScroll: true });
                }
            });
        },
        showUnauthorizedAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Action non autorisée',
                text: 'Vous ne disposez pas des droits suffisants.',
            });
        },
    },
};

defineRule('required', (value) => {
    if (value === undefined || value === null || value === '') {
        return 'Ce champ est requis.';
    }
    return true;
});

defineRule('alpha_num_dash', (value) => {
    return true;
});

defineRule('numeric', (value) => {
    if (value === undefined || value === null || value === '') {
        return true;
    }

    return !Number.isNaN(Number(value)) || 'Veuillez saisir un nombre.';
});

defineRule('min', (value, [limit]) => {
    if (value === undefined || value === null || value === '') {
        return true;
    }

    return Number(value) >= Number(limit) || `Valeur minimale ${limit}.`;
});

configure({
    validateOnBlur: true,
    validateOnChange: true,
    validateOnInput: true,
    validateOnModelUpdate: true,
});
</script>
