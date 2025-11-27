<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { useForm } from '@inertiajs/vue3';
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
                    <CardHeader>
                        <CardTitle>Rôles</CardTitle>
                        <CardDescription>Attribuer un rôle aux membres de l’équipe</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-3">
                            <div v-for="user in users" :key="user.id"
                                class="flex flex-col gap-2 rounded-lg border border-border/60 p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ user.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Rôle actuel : <span class="font-medium">{{ user.role ?? 'Aucun' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <select v-model="selectedRoles[user.id]"
                                        :disabled="user.is_owner || loadingUserId === user.id"
                                        class="w-40 rounded-md border border-border bg-background px-3 py-2 text-sm disabled:opacity-60">
                                        <option disabled value="">Choisir un rôle</option>
                                        <option v-for="role in roles" :key="role.name" :value="role.name"
                                            :disabled="role.name === 'owner'">
                                            {{ role.name }}
                                        </option>
                                    </select>
                                    <Button
                                        size="sm"
                                        :disabled="user.is_owner || loadingUserId === user.id || !selectedRoles[user.id]"
                                        @click="submitRole(user.id)">
                                        Mettre à jour
                                    </Button>
                                </div>
                                <InputError v-if="lastSubmittedUserId === user.id" :message="form.errors.role" />
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div v-for="role in roles" :key="role.name"
                                class="flex items-center justify-between rounded-lg border border-border/60 p-3">
                                <span class="font-medium capitalize">{{ role.name }}</span>
                                <span class="text-xs text-muted-foreground">Rôle disponible</span>
                            </div>
                        </div>


                    </CardContent>
                </Card>

                <div class="text-sm text-muted-foreground">
                    Les rôles sont définis par le superadmin. Les owners et managers peuvent attribuer les rôles
                    existants aux utilisateurs.
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
