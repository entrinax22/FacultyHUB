<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Receive a secure password reset link by email',
    },
});

defineProps<{ status?: string }>();
</script>

<template>
    <Head title="Forgot password" />

    <div class="mx-auto w-full max-w-lg">
        <div class="mb-8">
            <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
                Forgot your password?
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Enter your account email and we will send a secure, single-use
                reset link.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-400"
        >
            {{ status }}
        </div>

        <Form
            v-bind="email.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    required
                    class="h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="h-11 w-full"
                :disabled="processing"
                :aria-busy="processing"
            >
                <Spinner v-if="processing" class="mr-2" />
                {{ processing ? 'Sending link...' : 'Email reset link' }}
            </Button>
        </Form>

        <div class="mt-8 border-t pt-6 text-center text-sm text-muted-foreground">
            Remember your password?
            <TextLink :href="login()" class="font-medium underline underline-offset-4">
                Log in
            </TextLink>
        </div>
    </div>
</template>
