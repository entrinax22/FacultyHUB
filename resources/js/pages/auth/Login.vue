<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { home, register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        class="min-h-svh bg-muted px-4 py-6 sm:px-6 lg:px-10 lg:py-10 xl:px-12"
    >
        <div
            class="mx-auto flex min-h-[calc(100svh-3rem)] w-full max-w-6xl items-center justify-center lg:min-h-[calc(100svh-5rem)]"
        >
            <!-- =========================================================
                 SINGLE LOGIN CARD
            ========================================================== -->
            <div
                class="w-full overflow-hidden rounded-3xl border bg-background shadow-xl"
            >
                <div class="grid lg:grid-cols-[0.9fr_1.1fr]">
                    <!-- =================================================
                         BRANDING PANEL
                    ================================================== -->
                    <section
                        class="relative hidden min-h-[650px] overflow-hidden bg-gradient-to-br from-[#88af91] to-[#cee2b1] lg:flex dark:from-[#15281c] dark:to-[#1e2922]"
                    >
                        <!-- Decorative shapes -->
                        <div
                            class="absolute -right-32 -top-32 size-96 rounded-full bg-[#15281c]/10 blur-3xl dark:bg-white/5"
                        />

                        <div
                            class="absolute -bottom-40 -left-32 size-[28rem] rounded-full bg-[#cee2b1]/25 blur-3xl dark:bg-black/20"
                        />

                        <div
                            class="absolute right-12 top-1/2 size-44 -translate-y-1/2 rounded-full border border-[#15281c]/20 dark:border-[#cee2b1]/15"
                        />

                        <div
                            class="absolute bottom-28 right-28 size-20 rounded-full border border-[#15281c]/20 dark:border-[#cee2b1]/15"
                        />

                        <!-- Branding content -->
                        <div
                            class="relative z-10 flex w-full flex-col justify-between p-10 xl:p-14"
                        >
                            <!-- Logo -->
                            <Link
                                :href="home()"
                                class="group flex w-fit items-center gap-3"
                            >
                                <div class="flex items-center">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        class="hidden size-7"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <circle
                                            cx="5"
                                            cy="7"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <circle
                                            cx="19"
                                            cy="7"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <circle
                                            cx="5"
                                            cy="17"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <circle
                                            cx="19"
                                            cy="17"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="5"
                                            y2="7"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />

                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="19"
                                            y2="7"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />

                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="5"
                                            y2="17"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />

                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="19"
                                            y2="17"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />
                                    </svg>
                                    <img
                                        src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-light.svg"
                                        alt="FacultyHUB LMS"
                                        class="h-auto w-56 object-contain object-left dark:hidden"
                                    />
                                    <img
                                        src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-dark.svg"
                                        alt="FacultyHUB LMS"
                                        class="hidden h-auto w-56 object-contain object-left dark:block"
                                    />
                                </div>

                                <div class="hidden">
                                    <div
                                        class="text-xl font-bold tracking-tight text-white"
                                    >
                                        Faculty<span class="text-white/60">
                                            HUB
                                        </span>
                                    </div>

                                    <p
                                        class="text-[11px] font-medium uppercase tracking-[0.18em] text-white/50"
                                    >
                                        Faculty-LMS
                                    </p>
                                </div>
                            </Link>

                            <!-- Main message -->
                            <div class="max-w-md">
                                <p
                                    class="mb-4 text-xs font-semibold uppercase tracking-[0.2em] text-[#15281c]/70 dark:text-[#cee2b1]/70"
                                >
                                    Welcome back
                                </p>

                                <h1
                                    class="text-4xl font-bold leading-[1.05] tracking-tight text-[#15281c] xl:text-5xl dark:text-[#cee2b1]"
                                >
                                    Your learning,
                                    <br />
                                    all in one place.
                                </h1>

                                <p
                                    class="mt-6 max-w-sm text-sm leading-6 text-[#1e2922]/75 dark:text-white/70"
                                >
                                    Access your classes, assignments, grades,
                                    attendance, and other academic resources
                                    through FacultyHUB.
                                </p>
                            </div>

                            <!-- Footer -->
                            <p class="text-xs text-[#1e2922]/60 dark:text-[#cee2b1]/60">
                                FacultyHUB Student Portal
                            </p>
                        </div>
                    </section>

                    <!-- =================================================
                         LOGIN FORM
                    ================================================== -->
                    <section
                        class="flex min-h-[650px] items-center px-6 py-10 sm:px-10 lg:px-12 xl:px-16"
                    >
                        <div class="mx-auto w-full max-w-md">
                            <!-- Mobile brand -->
                            <Link
                                :href="home()"
                                class="mb-10 flex w-fit items-center gap-2.5 lg:hidden"
                            >
                                <div class="flex items-center">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        class="hidden size-5"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                            fill="hsl(142 30% 12%)"
                                        />
                                        <circle
                                            cx="5"
                                            cy="7"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />
                                        <circle
                                            cx="19"
                                            cy="7"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />
                                        <circle
                                            cx="5"
                                            cy="17"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />
                                        <circle
                                            cx="19"
                                            cy="17"
                                            r="1.8"
                                            fill="hsl(142 30% 12%)"
                                        />

                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="5"
                                            y2="7"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />
                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="19"
                                            y2="7"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />
                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="5"
                                            y2="17"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />
                                        <line
                                            x1="12"
                                            y1="12"
                                            x2="19"
                                            y2="17"
                                            stroke="hsl(142 30% 12%)"
                                            stroke-width="1.3"
                                        />
                                    </svg>
                                    <img
                                        src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-light.svg"
                                        alt="FacultyHUB LMS"
                                        class="h-auto w-44 object-contain object-left"
                                    />
                                </div>

                            </Link>

                            <!-- Heading -->
                            <div class="mb-8">
                                <h2
                                    class="text-2xl font-semibold tracking-tight sm:text-3xl"
                                >
                                    Sign in to your account
                                </h2>

                                <p
                                    class="mt-2 text-sm leading-6 text-muted-foreground"
                                >
                                    Enter your email and password to continue
                                    to FacultyHUB.
                                </p>
                            </div>

                            <!-- Status -->
                            <div
                                v-if="status"
                                class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-400"
                            >
                                {{ status }}
                            </div>

                            <!-- Form -->
                            <Form
                                v-bind="store.form()"
                                :reset-on-success="['password']"
                                v-slot="{ errors, processing }"
                                class="flex w-full flex-col gap-6"
                            >
                                <div class="grid gap-5">
                                    <!-- Email -->
                                    <div class="grid gap-2">
                                        <Label for="email">
                                            Email address
                                        </Label>

                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autofocus
                                            :tabindex="1"
                                            autocomplete="email"
                                            placeholder="email@example.com"
                                            class="h-11"
                                        />

                                        <InputError
                                            :message="errors.email"
                                        />
                                    </div>

                                    <!-- Password -->
                                    <div class="grid gap-2">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <Label for="password">
                                                Password
                                            </Label>

                                            <TextLink
                                                v-if="canResetPassword"
                                                :href="request()"
                                                class="shrink-0 text-xs font-medium sm:text-sm"
                                                :tabindex="5"
                                            >
                                                Forgot password?
                                            </TextLink>
                                        </div>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            :tabindex="2"
                                            autocomplete="current-password"
                                            placeholder="Enter your password"
                                        />

                                        <InputError
                                            :message="errors.password"
                                        />
                                    </div>

                                    <!-- Remember -->
                                    <div class="flex items-center">
                                        <Label
                                            for="remember"
                                            class="flex cursor-pointer items-center gap-3 text-sm font-normal"
                                        >
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                :tabindex="3"
                                            />

                                            <span>Remember me</span>
                                        </Label>
                                    </div>

                                    <!-- Submit -->
                                    <Button
                                        type="submit"
                                        class="mt-1 h-11 w-full sm:h-12"
                                        :tabindex="4"
                                        :disabled="processing"
                                        :aria-busy="processing"
                                        data-test="login-button"
                                    >
                                        <Spinner
                                            v-if="processing"
                                            class="mr-2"
                                        />

                                        {{
                                            processing
                                                ? 'Logging in...'
                                                : 'Log in'
                                        }}
                                    </Button>
                                </div>

                                <!-- Register -->
                                <div
                                    v-if="canRegister"
                                    class="border-t pt-6 text-center text-sm text-muted-foreground"
                                >
                                    Don't have an account?

                                    <TextLink
                                        :href="register()"
                                        class="font-medium underline underline-offset-4"
                                        :tabindex="6"
                                    >
                                        Create a student account
                                    </TextLink>
                                </div>
                            </Form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
