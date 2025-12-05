<template>
    <div class="flex min-h-svh items-center justify-center bg-gray-50 px-6 py-12">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-md">
            <h1 class="mb-4 text-lg font-semibold text-gray-900">
                Accès à un espace client
            </h1>
            <p class="mb-6 text-sm text-gray-600">
                Entrez le nom de domaine ou le code de votre espace (ex: hotel1.serena.test) pour poursuivre.
            </p>
            <form
                method="post"
                action="/login/tenant"
                class="space-y-4"
            >
                <input type="hidden" name="_token" :value="csrf" />
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Domaine ou code de l’espace
                    </label>
                    <input
                        name="tenant"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                        placeholder="ex: hotel1.serena.test ou hotel1"
                        required
                    />
                </div>
                <div v-if="errors.tenant" class="text-sm text-red-600">
                    {{ errors.tenant }}
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    Continuer
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const csrf = page.props.csrf_token ?? '';
const errors = page.props.errors ?? {};
</script>

