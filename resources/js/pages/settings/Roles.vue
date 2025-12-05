<script setup lang="ts">
import Card from '@/components/Card.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    roles: { name: string }[];
    users: { id: number; name: string; email: string; role: string | null; is_owner: boolean }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Gestion des rôles',
        href: '#',
    },
];

const form = useForm({
    role: '',
});
const selectedRoles = reactive<Record<number, string>>({});
const lastSubmittedUserId = ref<number | null>(null);
const loadingUserId = ref<number | null>(null);

const syncSelectedRoles = () => {
    props.users.forEach((user) => {
        selectedRoles[user.id] = user.role ?? '';
    });
};

onMounted(syncSelectedRoles);
watch(
    () => props.users,
    () => syncSelectedRoles(),
    { deep: true },
);

const submitRole = (userId: number) => {
    form.role = selectedRoles[userId] ?? '';
    lastSubmittedUserId.value = userId;

    form.patch(`/users/${userId}/role`, {
        onStart: () => {
            loadingUserId.value = userId;
        },
        onFinish: () => {
            loadingUserId.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head title="Rôles" />

        <SettingsLayout>
            <div class="space-y-6 px-4 pb-8">
                <Card>
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Rôles</h3>
                        <p class="text-sm text-serena-text-muted">Attribuer un rôle aux membres de l’équipe</p>
                    </div>
                    <div class="grid gap-6 pt-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <div
                                v-for="user in users"
                                :key="user.id"
                                class="flex flex-col gap-3 rounded-xl border border-serena-border/50 bg-serena-card p-4 shadow-sm"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-serena-text-main">{{ user.name }}</p>
                                        <p class="text-sm text-serena-text-muted">{{ user.email }}</p>
                                    </div>
                                    <div class="text-sm text-serena-text-muted">
                                        Rôle actuel :
                                        <span class="font-medium text-serena-text-main">{{ user.role ?? 'Aucun' }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <select
                                        v-model="selectedRoles[user.id]"
                                        :disabled="user.is_owner || loadingUserId === user.id"
                                        class="w-44 rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <option disabled value="">Choisir un rôle</option>
                                        <option
                                            v-for="role in roles"
                                            :key="role.name"
                                            :value="role.name"
                                            :disabled="role.name === 'owner'"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </select>
                                    <PrimaryButton
                                        class="px-3 py-1.5 text-xs"
                                        :disabled="user.is_owner || loadingUserId === user.id || !selectedRoles[user.id]"
                                        @click="submitRole(user.id)"
                                    >
                                        Mettre à jour
                                    </PrimaryButton>
                                </div>
                                <InputError v-if="lastSubmittedUserId === user.id" :message="form.errors.role" />
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div
                                v-for="role in roles"
                                :key="role.name"
                                class="flex items-center justify-between rounded-xl border border-serena-border/50 bg-white p-3 text-sm"
                            >
                                <span class="font-medium capitalize text-serena-text-main">{{ role.name }}</span>
                                <span class="text-xs text-serena-text-muted">Rôle disponible</span>
                            </div>
                        </div>
                    </div>
                </Card>

                <div class="text-sm text-serena-text-muted">
                    Les rôles sont définis par le superadmin. Les owners et managers peuvent attribuer les rôles
                    existants aux utilisateurs.
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
