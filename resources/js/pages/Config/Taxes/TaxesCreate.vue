<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Créer une taxe</h1>
                <p class="text-sm text-gray-500">Renseignez les informations de la taxe.</p>
            </div>
            <Link href="/ressources/taxes" class="text-sm text-indigo-600 hover:underline">Retour</Link>
        </div>

        <Form @submit="handleSubmit" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <Field name="name" rules="required" v-slot="{ field }">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nom</label>
                        <input
                            v-bind="field"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                        <ErrorMessage name="name" class="mt-1 text-xs text-red-600" />
                    </div>
                </Field>

                <Field name="code" rules="alpha_num_dash" v-slot="{ field }">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Code</label>
                        <input
                            v-bind="field"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                        <ErrorMessage name="code" class="mt-1 text-xs text-red-600" />
                    </div>
                </Field>

                <Field name="rate" rules="required|numeric" v-slot="{ field }">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Taux (%)</label>
                        <input
                            v-bind="field"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        />
                        <ErrorMessage name="rate" class="mt-1 text-xs text-red-600" />
                    </div>
                </Field>

                <Field name="type" rules="required" v-slot="{ field, meta }">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Type</label>
                        <Multiselect
                            :model-value="field.value"
                            @update:modelValue="field.onChange"
                            :options="typeOptions"
                            :close-on-select="true"
                            :allow-empty="false"
                            label="label"
                            track-by="value"
                            placeholder="Sélectionner un type"
                            class="mt-1"
                        />
                        <p v-if="!meta.valid && meta.touched" class="mt-1 text-xs text-red-600">
                            <ErrorMessage name="type" />
                        </p>
                    </div>
                </Field>

                <Field name="is_city_tax" type="checkbox" v-slot="{ field }">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input v-bind="field" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        Taxe de séjour
                    </label>
                </Field>

                <Field name="is_active" type="checkbox" v-slot="{ field }">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input v-bind="field" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        Activer la taxe
                    </label>
                </Field>
            </div>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="submitting"
                >
                    <span v-if="submitting">Enregistrement…</span>
                    <span v-else>Enregistrer</span>
                </button>
            </div>
        </Form>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import { ErrorMessage, Field, Form, configure, defineRule } from 'vee-validate';
import ConfigLayout from '@/layouts/ConfigLayout.vue';

defineRule('required', (value) => {
    if (value === undefined || value === null || value === '') {
        return 'Ce champ est requis.';
    }
    return true;
});

defineRule('numeric', (value) => {
    if (value === undefined || value === null || value === '') {
        return true;
    }
    return !Number.isNaN(Number(value)) || 'Veuillez saisir un nombre.';
});

defineRule('alpha_num_dash', (value) => {
    if (!value) {
        return true;
    }
    return /^[A-Za-z0-9-_]+$/.test(value) || 'Utilisez uniquement lettres, chiffres, tirets.';
});

configure({
    validateOnBlur: true,
    validateOnChange: true,
    validateOnInput: true,
    validateOnModelUpdate: true,
});

export default {
    name: 'TaxesCreate',
    components: {
        ConfigLayout,
        Link,
        Form,
        Field,
        ErrorMessage,
    },
    data() {
        return {
            submitting: false,
            typeOptions: [
                { label: 'Pourcentage', value: 'percentage' },
                { label: 'Montant fixe', value: 'fixed' },
            ],
        };
    },
    methods: {
        handleSubmit(values) {
            this.submitting = true;
            router.post('/ressources/taxes', values, {
                onFinish: () => {
                    this.submitting = false;
                },
            });
        },
    },
};
</script>
