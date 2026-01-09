<template>
    <ConfigLayout>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Utilisateurs</h1>
                <p class="text-sm text-gray-500">Gestion des membres du tenant.</p>
            </div>
            <Link href="/settings/resources/users/create">
                <PrimaryButton type="button" class="px-4 py-2 text-sm">
                    Nouvel utilisateur
                </PrimaryButton>
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Nom</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Rôle</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ user.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ user.email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ user.role || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 space-x-3">
                            <Link :href="`/settings/resources/users/${user.id}/edit`" class="text-indigo-600 hover:underline">
                                Éditer
                            </Link>
                            <button
                                type="button"
                                class="cursor-pointer text-sm font-medium text-red-600 hover:text-red-700"
                                @click="destroy(user.id)"
                            >
                                Supprimer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">
                Pagination à ajouter selon vos besoins.
            </div>
        </div>
    </ConfigLayout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import ConfigLayout from '@/layouts/ConfigLayout.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';

export default {
    name: 'UsersIndex',
    components: { ConfigLayout, Link, PrimaryButton },
    props: {
        users: {
            type: Object,
            required: true,
        },
    },
    methods: {
        destroy(id) {
            if (confirm('Supprimer cet utilisateur ?')) {
                router.delete(`/settings/resources/users/${id}`);
            }
        },
    },
};
</script>
