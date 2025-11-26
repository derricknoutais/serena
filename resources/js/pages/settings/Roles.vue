<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    roles: { name: string; permissions: string[] }[];
    users: { id: number; name: string; email: string; role: string | null; is_owner: boolean }[];
}>();

const permissionLabels: Record<string, string> = {
    'users.view': 'Voir les utilisateurs',
    'users.manage': 'Gérer les utilisateurs',
    'invitations.view': 'Voir les invitations',
    'invitations.manage': 'Gérer les invitations',
    'profile.update': 'Mettre à jour le profil',
    'dashboard.view': 'Accéder au tableau de bord',
    'activity.view': 'Voir les journaux d’activité',
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Gestion des rôles',
        href: '#',
    },
];

const form = useForm({
    role: '',
});

const submitRole = (userId: number) => {
    form.patch(`/users/${userId}/role`, {
        onStart: () => {
            form.processing = true;
        },
        onFinish: () => {
            form.processing = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">

        <Head title="Rôles et permissions" />

        <SettingsLayout>
            <div class="space-y-6 px-4 pb-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Rôles et permissions</CardTitle>
                        <CardDescription>Rôles globaux et permissions associées</CardDescription>
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
                                    <select v-model="form.role" :disabled="user.is_owner || form.processing"
                                        class="w-40 rounded-md border border-border bg-background px-3 py-2 text-sm disabled:opacity-60">
                                        <option disabled value="">Choisir un rôle</option>
                                        <option v-for="role in roles" :key="role.name" :value="role.name"
                                            :disabled="role.name === 'owner'">
                                            {{ role.name }}
                                        </option>
                                    </select>
                                    <Button size="sm" :disabled="user.is_owner || form.processing || !form.role"
                                        @click="submitRole(user.id)">
                                        Mettre à jour
                                    </Button>
                                </div>
                                <InputError :message="form.errors.role" />
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div v-for="role in roles" :key="role.name"
                                class="space-y-2 rounded-lg border border-border/60 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium capitalize">{{ role.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ role.permissions.length }}
                                        permissions</span>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span v-for="permission in role.permissions" :key="permission"
                                        class="rounded bg-muted px-2 py-1 text-muted-foreground">
                                        {{ permissionLabels[permission] ?? permission }}
                                    </span>
                                </div>
                            </div>
                        </div>


                    </CardContent>
                </Card>

                <div class="text-sm text-muted-foreground">
                    Les rôles et permissions sont définis par le superadmin. Les owners et admins peuvent seulement
                    attribuer les rôles existants aux utilisateurs.
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
