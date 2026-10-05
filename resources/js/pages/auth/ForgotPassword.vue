<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Verify your account and create a new password',
    },
});

defineProps<{
    status?: string;
}>();

const verificationMethod = ref<'student_no' | 'email'>('student_no');
const verified = ref(false);
</script>

<template>
    <Head title="Reset password" />

    <div class="mx-auto w-full max-w-lg">
        <!-- Status -->
        <div
            v-if="status"
            class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700"
        >
            {{ status }}
        </div>

        <!-- Account lookup -->
        <div v-if="!verified">
            <!-- Header -->
            <div class="mb-8">
                <div
                    class="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="size-5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A1.75 1.75 0 0 1 19 12.25v7A1.75 1.75 0 0 1 17.25 21h-10.5A1.75 1.75 0 0 1 5 19.25v-7a1.75 1.75 0 0 1 1.75-1.75Z"
                        />
                    </svg>
                </div>

                <div class="flex items-center gap-3">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary"
                    >
                        01
                    </div>

                    <div>
                        <h1
                            class="text-xl font-semibold tracking-tight sm:text-2xl"
                        >
                            Verify your account
                        </h1>

                        <p
                            class="mt-1 text-sm leading-6 text-muted-foreground"
                        >
                            Enter your student number. If you do not remember
                            it, use your email address instead.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Verification Form -->
            <Form
                action="/forgot-password/verify"
                method="post"
                :transform="(data) => ({ ...data, mode: verificationMethod })"
                v-slot="{ errors, processing }"
                @success="verified = true"
            >
                <div class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="identifier">
                            {{ verificationMethod === 'student_no' ? 'Student number' : 'Email address' }}
                        </Label>

                        <Input
                            :key="verificationMethod"
                            id="identifier"
                            name="identifier"
                            :type="verificationMethod === 'student_no' ? 'text' : 'email'"
                            :autocomplete="verificationMethod === 'student_no' ? 'off' : 'email'"
                            autofocus
                            :placeholder="verificationMethod === 'student_no' ? '2026-12345' : 'you@example.com'"
                            :maxlength="verificationMethod === 'student_no' ? 10 : 255"
                            class="h-11"
                        />

                        <p
                            v-if="verificationMethod === 'student_no'"
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Enter your student number in the format
                            <span class="font-medium">YYYY-NNNNN</span>.
                        </p>

                        <InputError :message="errors.identifier" />
                    </div>

                    <div class="text-center">
                        <Button
                            type="button"
                            variant="link"
                            class="h-auto p-0 text-sm"
                            @click="verificationMethod = verificationMethod === 'student_no' ? 'email' : 'student_no'"
                        >
                            {{ verificationMethod === 'student_no' ? 'Forgot your student number? Use email instead' : 'Use student number instead' }}
                        </Button>
                    </div>

                    <!-- Button -->
                    <Button
                        type="submit"
                        class="h-11 w-full"
                        :disabled="processing"
                        :aria-busy="processing"
                    >
                        <Spinner
                            v-if="processing"
                            class="mr-2"
                        />

                        {{
                            processing
                                ? 'Verifying account...'
                                : 'Verify account'
                        }}
                    </Button>
                </div>
            </Form>

            <!-- Footer -->
            <div
                class="mt-8 border-t pt-6 text-center text-sm text-muted-foreground"
            >
                Remember your password?

                <TextLink
                    :href="login()"
                    class="font-medium underline underline-offset-4"
                >
                    Log in
                </TextLink>
            </div>
        </div>

        <div v-else>
            <div class="mb-8 flex items-center gap-3">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary"
                >
                    02
                </div>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
                        Create a new password
                    </h1>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        Your account is verified. Choose a new password below.
                    </p>
                </div>
            </div>

            <Form
                action="/forgot-password/reset"
                method="post"
                v-slot="{ errors, processing }"
            >
                <div class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            autofocus
                            class="h-11"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm new password</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="h-11"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        class="h-11 w-full"
                        :disabled="processing"
                        :aria-busy="processing"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Changing password...' : 'Change password' }}
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>