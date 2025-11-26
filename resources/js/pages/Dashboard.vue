<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const form = useForm({
    email: '',
});

const submitInvitation = () => {
    form.post('/invitations', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <Card class="flex flex-col">
                    <CardHeader>
                        <CardTitle>Inviter un membre</CardTitle>
                        <CardDescription>
                            Envoyez un lien d'invitation pour rejoindre ce tenant.
                        </CardDescription>
                    </CardHeader>
                    <form class="flex h-full flex-col" @submit.prevent="submitInvitation">
                        <CardContent class="flex-1 space-y-3">
                            <div class="space-y-2">
                                <Label for="invite-email">Email de l'invite</Label>
                                <Input
                                    id="invite-email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    required
                                    autocomplete="email"
                                    placeholder="invite@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                            <p
                                v-if="form.recentlySuccessful"
                                class="text-sm text-foreground"
                            >
                                Invitation envoyee.
                            </p>
                        </CardContent>
                        <CardFooter class="justify-end">
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full md:w-auto"
                            >
                                Envoyer l'invitation
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
                <Card class="relative aspect-video overflow-hidden border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </Card>
                <Card class="relative aspect-video overflow-hidden border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </Card>
            </div>
            <div
                class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
