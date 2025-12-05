<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    centralDomain?: string;
}>();

const businessName = ref('');
const slugInput = ref('');
const slugManuallyEdited = ref(false);
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const touched = reactive({
    business_name: false,
    tenant_slug: false,
    name: false,
    email: false,
    password: false,
    password_confirmation: false,
});

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-')
        .slice(0, 63);

watch(businessName, (value) => {
    if (slugManuallyEdited.value) {
        return;
    }

    slugInput.value = slugify(value);
});

const tenantSlug = computed(() => slugInput.value);

const domainPreview = computed(
    () => `${tenantSlug.value || 'your-team'}.${props.centralDomain ?? 'saas-template.test'}`,
);

type SlugStatus = 'idle' | 'checking' | 'available' | 'unavailable' | 'invalid';
const slugStatus = ref<SlugStatus>('idle');
const slugMessage = ref('');
type EmailStatus = 'idle' | 'checking' | 'available' | 'unavailable' | 'invalid';
const emailStatus = ref<EmailStatus>('idle');
const emailMessage = ref('');
const localErrors = reactive({
    business_name: 'Le nom de l’entreprise est requis.',
    tenant_slug: 'Veuillez choisir un sous-domaine.',
    name: 'Votre nom est requis.',
    email: 'Une adresse email valide est requise.',
    password: 'Le mot de passe doit contenir au moins 8 caracteres.',
    password_confirmation: 'Les mots de passe doivent correspondre.',
});

const handleSlugInput = (value: string) => {
    slugManuallyEdited.value = true;
    slugInput.value = slugify(value);
};

const checkAvailability = useDebounceFn(async (slug: string) => {
    if (!slug) {
        slugStatus.value = touched.tenant_slug ? 'invalid' : 'idle';
        slugMessage.value = touched.tenant_slug ? 'Please enter a business name.' : '';
        localErrors.tenant_slug = 'Veuillez saisir un sous-domaine.';
        return;
    }

    slugStatus.value = 'checking';
    slugMessage.value = '';

    try {
        const response = await fetch(`/register/check-slug?slug=${encodeURIComponent(slug)}`);

        if (!response.ok) {
            const error = await response.json();
            slugStatus.value = 'invalid';
            slugMessage.value = error.message ?? 'Veuillez choisir un sous-domaine valide.';
            localErrors.tenant_slug = slugMessage.value;
            return;
        }

        const payload = await response.json();
        slugStatus.value = payload.available ? 'available' : 'unavailable';
        slugMessage.value = payload.message ?? '';
        localErrors.tenant_slug = payload.available ? '' : (payload.message ?? 'Sous-domaine deja pris.');
    } catch (error) {
        slugStatus.value = 'invalid';
        slugMessage.value = 'Unable to verify availability.';
        localErrors.tenant_slug = 'Verification indisponible.';
        console.error(error);
    }
}, 250);

const checkEmailAvailability = useDebounceFn(async () => {
    if (!touched.email) {
        emailStatus.value = 'idle';
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        emailStatus.value = 'invalid';
        emailMessage.value = 'Adresse e-mail invalide.';
        localErrors.email = emailMessage.value;
        return;
    }

    if (!tenantSlug.value) {
        emailStatus.value = 'invalid';
        emailMessage.value = 'Sous-domaine requis avant de vérifier l’e-mail.';
        localErrors.email = emailMessage.value;
        return;
    }

    emailStatus.value = 'checking';
    emailMessage.value = '';

    try {
        const response = await fetch(
            `/register/check-email?email=${encodeURIComponent(email.value)}&tenant=${encodeURIComponent(tenantSlug.value)}`,
        );

        if (!response.ok) {
            const error = await response.json();
            emailStatus.value = 'invalid';
            emailMessage.value = error.message ?? 'Impossible de vérifier cet e-mail.';
            localErrors.email = emailMessage.value;
            return;
        }

        const payload = await response.json();
        emailStatus.value = payload.available ? 'available' : 'unavailable';
        emailMessage.value = payload.message ?? '';
        localErrors.email = payload.available ? '' : (payload.message ?? 'Adresse e-mail déjà utilisée.');
    } catch (error) {
        emailStatus.value = 'invalid';
        emailMessage.value = 'Impossible de vérifier cet e-mail.';
        localErrors.email = emailMessage.value;
        console.error(error);
    }
}, 250);

const validateLocal = () => {
    localErrors.business_name = businessName.value.trim() === '' ? "Le nom de l’entreprise est requis." : '';
    localErrors.name = name.value.trim() === '' ? 'Votre nom est requis.' : '';
    localErrors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)
        ? ''
        : 'Adresse email invalide.';
    localErrors.password = password.value.length >= 8 ? '' : 'Le mot de passe doit contenir au moins 8 caracteres.';
    localErrors.password_confirmation =
        passwordConfirmation.value === password.value ? '' : 'Les mots de passe doivent correspondre.';

    if (tenantSlug.value === '') {
        localErrors.tenant_slug = 'Veuillez choisir un sous-domaine.';
    }
};

watch([businessName, name, email, password, passwordConfirmation], () => {
    validateLocal();
});

watch(slugInput, (slug) => {
    checkAvailability(slug);
    validateLocal();
});

watch([email, tenantSlug], () => {
    checkEmailAvailability();
});

const isFormInvalid = computed(() => {
    const hasLocalErrors = Object.values(localErrors).some((message) => message !== '');

    return (
        hasLocalErrors ||
        slugStatus.value === 'checking' ||
        slugStatus.value === 'unavailable' ||
        slugStatus.value === 'invalid' ||
        emailStatus.value === 'checking' ||
        emailStatus.value === 'unavailable' ||
        emailStatus.value === 'invalid'
    );
});
</script>

<template>
    <AuthBase
        title="Créer un compte"
        description="Renseignez vos informations pour créer votre espace"
    >
        <Head title="Inscription" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <TextInput
                        id="business_name"
                        v-model="businessName"
                        label="Nom de l’entreprise"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="organization"
                        name="business_name"
                        placeholder="Ex. Acme"
                        @focus="touched.business_name = true"
                    />
                    <InputError :message="errors.business_name || (touched.business_name ? localErrors.business_name : '')" />
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="tenant_slug"
                        name="tenant_slug"
                        label="Sous-domaine"
                        :model-value="tenantSlug"
                        @update:modelValue="handleSlugInput"
                        type="text"
                        :tabindex="2"
                        autocomplete="off"
                        placeholder="acme"
                        @focus="touched.tenant_slug = true"
                    >
                        <template #label-action>
                            <span class="text-xs text-serena-text-muted"
                                >Aperçu : {{ domainPreview }}</span
                            >
                        </template>
                    </TextInput>
                    <InputError
                        :message="errors.tenant_slug || (touched.tenant_slug ? localErrors.tenant_slug : '')"
                    />
                    <p
                        v-if="touched.tenant_slug && !errors.tenant_slug && slugStatus !== 'idle'"
                        class="text-xs"
                        :class="{
                            'text-green-600': slugStatus === 'available',
                            'text-serena-danger': slugStatus === 'unavailable' || slugStatus === 'invalid',
                            'text-serena-text-muted': slugStatus === 'checking',
                        }"
                    >
                        <template v-if="slugStatus === 'checking'">Vérification…</template>
                        <template v-else-if="slugStatus === 'available'">{{ slugMessage || 'Sous-domaine disponible' }}</template>
                        <template v-else-if="slugStatus === 'unavailable'">{{ slugMessage || 'Sous-domaine déjà pris' }}</template>
                        <template v-else-if="slugStatus === 'invalid'">{{ slugMessage || 'Sous-domaine invalide' }}</template>
                    </p>
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="name"
                        v-model="name"
                        label="Votre nom"
                        type="text"
                        required
                        :tabindex="3"
                        autocomplete="name"
                        name="name"
                        placeholder="Nom complet"
                        @focus="touched.name = true"
                    />
                    <InputError :message="errors.name || (touched.name ? localErrors.name : '')" />
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="email"
                        v-model="email"
                        label="Adresse e-mail"
                        type="email"
                        required
                        :tabindex="4"
                        autocomplete="email"
                        name="email"
                        placeholder="email@exemple.com"
                        @focus="touched.email = true"
                    />
                    <InputError :message="errors.email || (touched.email ? localErrors.email : '')" />
                    <p
                        v-if="touched.email && !errors.email && emailStatus !== 'idle'"
                        class="text-xs"
                        :class="{
                            'text-green-600': emailStatus === 'available',
                            'text-serena-danger': emailStatus === 'unavailable' || emailStatus === 'invalid',
                            'text-serena-text-muted': emailStatus === 'checking',
                        }"
                    >
                        <template v-if="emailStatus === 'checking'">Vérification de l’e-mail…</template>
                        <template v-else-if="emailStatus === 'available'">{{ emailMessage || 'Adresse disponible' }}</template>
                        <template v-else-if="emailStatus === 'unavailable'">{{ emailMessage || 'Adresse déjà utilisée' }}</template>
                        <template v-else-if="emailStatus === 'invalid'">{{ emailMessage || 'E-mail invalide' }}</template>
                    </p>
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="password"
                        v-model="password"
                        label="Mot de passe"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Au moins 8 caractères"
                        @focus="touched.password = true"
                    />
                    <InputError :message="errors.password || (touched.password ? localErrors.password : '')" />
                </div>

                <div class="grid gap-2">
                    <TextInput
                        id="password_confirmation"
                        v-model="passwordConfirmation"
                        label="Confirmer le mot de passe"
                        type="password"
                        required
                        :tabindex="6"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Répétez le mot de passe"
                        @focus="touched.password_confirmation = true"
                    />
                    <InputError
                        :message="
                            errors.password_confirmation ||
                            (touched.password_confirmation ? localErrors.password_confirmation : '')
                        "
                    />
                </div>

                <PrimaryButton
                    type="submit"
                    class="mt-2 w-full justify-center"
                    tabindex="7"
                    :disabled="
                        processing ||
                        !tenantSlug ||
                        isFormInvalid
                    "
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Créer le compte
                </PrimaryButton>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Vous avez déjà un compte ?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Connectez-vous</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
