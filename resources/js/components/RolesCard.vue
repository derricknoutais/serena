<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import PlaceholderPattern from './PlaceholderPattern.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Permission = {
    name: string;
};

type Role = {
    name: string;
    permissions: Permission[];
};

const page = usePage<{
    auth: {
        user: {
            roles?: Role[];
            permissions?: string[];
        };
    };
}>();

const roles = computed<Role[]>(() => page.props.auth.user.roles ?? []);
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader>
            <CardTitle>Rôles & permissions</CardTitle>
            <CardDescription>
                Consultez vos rôles et les permissions associées.
            </CardDescription>
        </CardHeader>
        <CardContent class="flex-1 space-y-3">
            <template v-if="roles.length">
                <div v-for="role in roles" :key="role.name" class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium capitalize">{{ role.name }}</span>
                        <Badge variant="secondary">{{ role.permissions.length }} permissions</Badge>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="permission in role.permissions"
                            :key="permission.name"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ permission.name }}
                        </Badge>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="relative aspect-[4/3] overflow-hidden rounded-lg border border-border/70 dark:border-border/50">
                    <PlaceholderPattern />
                    <div class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                        Aucun rôle assigné
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
