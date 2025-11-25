<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    centralDomain?: string;
}>();

const businessName = ref('');
const tenantSlug = ref('');
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const slugManuallyEdited = ref(false);

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-')
        .slice(0, 63);

const handleSlugInput = (value: string) => {
    tenantSlug.value = slugify(value);
    slugManuallyEdited.value = value.length > 0;
};

watch(businessName, (value) => {
    if (slugManuallyEdited.value) {
        return;
    }

    tenantSlug.value = slugify(value);
});

const domainPreview = computed(
    () => `${tenantSlug.value || 'your-team'}.${props.centralDomain ?? 'saas-template.test'}`,
);
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="business_name">Business name</Label>
                    <Input
                        id="business_name"
                        v-model="businessName"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="organization"
                        name="business_name"
                        placeholder="Acme, Inc."
                    />
                    <InputError :message="errors.business_name" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="tenant_slug">Subdomain</Label>
                        <span class="text-xs text-muted-foreground"
                            >Preview: {{ domainPreview }}</span
                        >
                    </div>
                    <Input
                        id="tenant_slug"
                        name="tenant_slug"
                        :model-value="tenantSlug"
                        @update:modelValue="handleSlugInput"
                        type="text"
                        :tabindex="2"
                        autocomplete="off"
                        placeholder="acme"
                    />
                    <InputError :message="errors.tenant_slug" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Your name</Label>
                    <Input
                        id="name"
                        v-model="name"
                        type="text"
                        required
                        :tabindex="3"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        :tabindex="4"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        v-model="password"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        required
                        :tabindex="6"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="7"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
