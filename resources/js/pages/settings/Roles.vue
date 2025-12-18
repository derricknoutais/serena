<script setup lang="ts">
import Card from '@/components/Card.vue';
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
    roles: { id: number; name: string; permissions: string[] }[];
    users: { id: number; name: string; email: string; role: string | null; is_owner: boolean }[];
    permissionGroups: { group: string; items: { name: string; label: string }[] }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Gestion des rôles',
        href: '#',
    },
];

const form = useForm({
    role: '',
    permissions: [] as string[],
});
const selectedRoles = reactive<Record<number, string>>({});
const lastSubmittedUserId = ref<number | null>(null);
const loadingUserId = ref<number | null>(null);
const selectedRoleId = ref<number | null>(null);

const selectedRole = computed(() => (selectedRoleId.value ? rolesById.value[selectedRoleId.value] : null));

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

const rolesById = computed(() =>
    props.roles.reduce<Record<number, { id: number; name: string; permissions: string[] }>>((acc, role) => {
        acc[role.id] = role;
        return acc;
    }, {}),
);

watch(
    () => selectedRoleId.value,
    (newRoleId) => {
        if (!newRoleId) {
            form.permissions = [];
            return;
        }

        form.permissions = [...(rolesById.value[newRoleId]?.permissions ?? [])];
    },
    { immediate: true },
);

const groupNames = computed(() =>
    Object.fromEntries(
        props.permissionGroups.map((group) => [
            group.group,
            group.items.map((item) => item.name),
        ]),
    ),
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

const submitPermissions = () => {
    if (!selectedRoleId.value) {
        return;
    }

    form.patch(route('settings.roles.update', selectedRoleId.value));
};

const isGroupFullyChecked = (groupKey: string) => {
    const items = groupNames.value[groupKey] ?? [];
    if (!items.length) {
        return false;
    }

    return items.every((permission) => form.permissions.includes(permission));
};

const toggleGroup = (groupKey: string) => {
    const items = groupNames.value[groupKey] ?? [];
    const allChecked = isGroupFullyChecked(groupKey);

    form.permissions = allChecked
        ? form.permissions.filter((permission) => !items.includes(permission))
        : Array.from(new Set([...form.permissions, ...items]));
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

                <Card>
                    <div class="space-y-1 border-b border-serena-border/60 pb-4">
                        <h3 class="text-lg font-semibold text-serena-text-main">Permissions par rôle</h3>
                        <p class="text-sm text-serena-text-muted">
                            Sélectionnez un rôle puis cochez les permissions à lui attribuer.
                        </p>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <select
                                v-model="selectedRoleId"
                                class="w-56 rounded-lg border border-serena-border bg-white px-3 py-2 text-sm text-serena-text-main transition focus:border-serena-primary focus:ring-2 focus:ring-serena-primary-soft"
                            >
                                <option disabled value="">Choisir un rôle</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                            <PrimaryButton
                                class="px-3 py-2 text-xs"
                                :disabled="!selectedRoleId"
                                @click="submitPermissions"
                            >
                                Enregistrer les permissions
                            </PrimaryButton>
                            <InputError :message="form.errors.permissions" />
                        </div>

                        <div v-if="selectedRoleId" class="space-y-2 text-sm text-serena-text-muted">
                            <span>Rôle sélectionné :</span>
                            <span class="font-semibold text-serena-text-main">{{ selectedRole?.name }}</span>
                        </div>

                        <div v-if="selectedRoleId" class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="group in permissionGroups"
                                :key="group.group"
                                class="rounded-xl border border-serena-border/60 bg-white p-4 shadow-sm"
                            >
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                                            :checked="isGroupFullyChecked(group.group)"
                                            @change="toggleGroup(group.group)"
                                        />
                                        <h4 class="text-sm font-semibold text-serena-text-main">{{ group.group }}</h4>
                                    </label>
                                    <span class="text-xs text-serena-text-muted">{{ group.items.length }} droits</span>
                                </div>
                                <div class="space-y-2">
                                    <label
                                        v-for="item in group.items"
                                        :key="item.name"
                                        class="flex items-start gap-3 rounded-lg border border-transparent px-2 py-1.5 transition hover:border-serena-border"
                                    >
                                        <input
                                            v-model="form.permissions"
                                            :value="item.name"
                                            type="checkbox"
                                            class="mt-0.5 h-4 w-4 rounded border-serena-border text-serena-primary focus:ring-serena-primary"
                                        />
                                        <div>
                                            <p class="text-sm font-medium text-serena-text-main">{{ item.label }}</p>
                                            <p class="text-xs text-serena-text-muted">{{ item.name }}</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
