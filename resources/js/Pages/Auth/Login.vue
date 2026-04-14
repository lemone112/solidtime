<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { Field, FieldLabel, FieldError } from '@/packages/ui/src/field';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const page = usePage<{
    flash: {
        message: string;
    };
}>();
</script>

<template>
    <Head title="Log in" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <template #actions>
            <Link
                class="py-8 text-text-secondary text-sm font-medium opacity-90 hover:opacity-100 transition"
                :href="route('register')">
                No account yet? <span class="text-text-primary">Register here!</span>
            </Link>
        </template>

        <div v-if="status" class="mb-4 font-medium text-sm text-green-400">
            {{ status }}
        </div>
        <div
            v-if="page.props.flash?.message"
            class="bg-red-400 text-black text-center w-full px-3 py-1 mb-4 rounded-lg">
            {{ page.props.flash?.message }}
        </div>

        <form @submit.prevent="submit">
            <Field>
                <FieldLabel for="email">Email</FieldLabel>
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="block w-full"
                    required
                    autofocus
                    autocomplete="username" />
                <FieldError v-if="form.errors.email">{{ form.errors.email }}</FieldError>
            </Field>

            <Field class="mt-4">
                <FieldLabel for="password">Password</FieldLabel>
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="block w-full"
                    required
                    autocomplete="current-password" />
                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
            </Field>

            <div class="flex items-center justify-end mt-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-text-secondary hover:text-text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Forgot your password?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing">
                    Log in
                </PrimaryButton>
            </div>
        </form>

        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border-primary" />
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 text-text-secondary" style="background: inherit">или</span>
                </div>
            </div>

            <div class="mt-4">
                <a
                    href="/auth/oidc/redirect"
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-border-primary px-4 py-2.5 text-sm font-medium text-text-primary transition-colors hover:bg-card-background-active focus:outline-none focus:ring-2 focus:ring-ring-focus">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                        <polyline points="10 17 15 12 10 7" />
                        <line x1="15" y1="12" x2="3" y2="12" />
                    </svg>
                    Войти через LabPics ID
                </a>
            </div>
        </div>
    </AuthenticationCard>
</template>
