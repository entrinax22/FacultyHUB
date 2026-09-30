<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <Head title="Create Student Account" />

    <div
        class="min-h-svh bg-muted px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-12 xl:px-12 2xl:px-16"
    >
        <div
            class="mx-auto flex w-full max-w-5xl flex-col gap-6 xl:max-w-6xl 2xl:max-w-7xl"
        >
            <!-- Brand -->
            <div class="flex items-center justify-center py-1">
                <div
                    class="flex items-center gap-2.5"
                >
                    <div
                        class="flex aspect-square size-9 items-center justify-center rounded-xl brand-gradient shadow-md sm:size-10 lg:size-11"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="size-5 sm:size-5.5 lg:size-6"
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
                    </div>

                    <span
                        class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl"
                    >
                        Faculty<span class="brand-gradient-text">HUB</span>
                    </span>
                </div>
            </div>

            <!-- Card -->
            <div
                class="w-full overflow-hidden rounded-2xl border bg-background shadow-sm lg:rounded-3xl"
            >
                <!-- Header -->
                <div
                    class="px-5 pt-7 pb-6 text-center sm:px-8 sm:pt-8 lg:px-12 lg:pt-10 lg:pb-8 xl:px-16 xl:pt-12"
                >
                    <div
                        class="mx-auto mb-3 flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
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
                                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                            />
                            <circle
                                cx="9"
                                cy="7"
                                r="4"
                            />
                            <path d="M19 8v6" />
                            <path d="M22 11h-6" />
                        </svg>
                    </div>

                    <h1
                        class="text-xl font-semibold tracking-tight sm:text-2xl lg:text-3xl"
                    >
                        Create your student account
                    </h1>

                    <p
                        class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground lg:text-base"
                    >
                        Enter your student information to create your
                        Faculty-LMS account.
                    </p>
                </div>

                <!-- Form -->
                <div
                    class="border-t px-5 py-7 sm:px-8 sm:py-8 lg:px-12 lg:py-10 xl:px-16 xl:py-12"
                >
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="[
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing }"
                        class="mx-auto w-full max-w-4xl"
                    >
                        <!-- Student Information -->
                        <section>
                            <div class="mb-5 flex items-center gap-3">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary"
                                >
                                    01
                                </div>

                                <div>
                                    <h2
                                        class="text-sm font-semibold sm:text-base"
                                    >
                                        Student Information
                                    </h2>

                                    <p
                                        class="text-xs text-muted-foreground sm:text-sm"
                                    >
                                        Provide your official student details.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <!-- Student ID -->
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="student_no">
                                        Student ID
                                    </Label>

                                    <Input
                                        id="student_no"
                                        type="text"
                                        required
                                        autofocus
                                        :tabindex="1"
                                        autocomplete="off"
                                        name="student_no"
                                        placeholder="e.g. 2020-00015"
                                        class="h-11"
                                    />

                                    <InputError
                                        :message="errors.student_no"
                                    />
                                </div>

                                <!-- First Name -->
                                <div class="grid gap-2">
                                    <Label for="first_name">
                                        First Name
                                    </Label>

                                    <Input
                                        id="first_name"
                                        type="text"
                                        required
                                        :tabindex="2"
                                        autocomplete="given-name"
                                        name="first_name"
                                        placeholder="Juan"
                                        class="h-11"
                                    />

                                    <InputError
                                        :message="errors.first_name"
                                    />
                                </div>

                                <!-- Last Name -->
                                <div class="grid gap-2">
                                    <Label for="last_name">
                                        Last Name
                                    </Label>

                                    <Input
                                        id="last_name"
                                        type="text"
                                        required
                                        :tabindex="3"
                                        autocomplete="family-name"
                                        name="last_name"
                                        placeholder="Dela Cruz"
                                        class="h-11"
                                    />

                                    <InputError
                                        :message="errors.last_name"
                                    />
                                </div>

                                <!-- Email -->
                                <div class="grid gap-2 sm:col-span-2">
                                    <Label for="email">
                                        Email Address
                                    </Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        :tabindex="4"
                                        autocomplete="email"
                                        name="email"
                                        placeholder="student@example.com"
                                        class="h-11"
                                    />

                                    <InputError :message="errors.email" />
                                </div>

                                <!-- Course -->
                                <div class="grid gap-2">
                                    <Label for="course">
                                        Course / Program
                                    </Label>

                                    <Input
                                        id="course"
                                        type="text"
                                        required
                                        :tabindex="5"
                                        autocomplete="organization-title"
                                        name="course"
                                        placeholder="BS Information Systems"
                                        class="h-11"
                                    />

                                    <InputError :message="errors.course" />
                                </div>

                                <!-- Year Level -->
                                <div class="grid gap-2">
                                    <Label for="year_level">
                                        Year Level
                                    </Label>

                                    <Select
                                        name="year_level"
                                        required
                                    >
                                        <SelectTrigger
                                            id="year_level"
                                            class="h-11"
                                            :tabindex="6"
                                        >
                                            <SelectValue
                                                placeholder="Select year level"
                                            />
                                        </SelectTrigger>

                                        <SelectContent>
                                            <SelectItem value="1">
                                                1st Year
                                            </SelectItem>

                                            <SelectItem value="2">
                                                2nd Year
                                            </SelectItem>

                                            <SelectItem value="3">
                                                3rd Year
                                            </SelectItem>

                                            <SelectItem value="4">
                                                4th Year
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <InputError
                                        :message="errors.year_level"
                                    />
                                </div>
                            </div>
                        </section>

                        <!-- Divider -->
                        <div class="my-8 border-t sm:my-10"></div>

                        <!-- Account Security -->
                        <section>
                            <div class="mb-5 flex items-center gap-3">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary"
                                >
                                    02
                                </div>

                                <div>
                                    <h2
                                        class="text-sm font-semibold sm:text-base"
                                    >
                                        Account Security
                                    </h2>

                                    <p
                                        class="text-xs text-muted-foreground sm:text-sm"
                                    >
                                        Create a secure password for your
                                        account.
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <!-- Password -->
                                <div class="grid gap-2">
                                    <Label for="password">
                                        Password
                                    </Label>

                                    <PasswordInput
                                        id="password"
                                        required
                                        :tabindex="7"
                                        autocomplete="new-password"
                                        name="password"
                                        placeholder="Create a password"
                                    />

                                    <InputError
                                        :message="errors.password"
                                    />
                                </div>

                                <!-- Confirm Password -->
                                <div class="grid gap-2">
                                    <Label for="password_confirmation">
                                        Confirm Password
                                    </Label>

                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        :tabindex="8"
                                        autocomplete="new-password"
                                        name="password_confirmation"
                                        placeholder="Repeat your password"
                                    />

                                    <InputError
                                        :message="
                                            errors.password_confirmation
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <!-- Actions -->
                        <div
                            class="mt-8 border-t pt-6 sm:mt-10 sm:pt-8"
                        >
                            <Button
                                type="submit"
                                class="h-11 w-full sm:h-12"
                                :disabled="processing"
                                :aria-busy="processing"
                                :tabindex="9"
                                data-test="register-user-button"
                            >
                                <Spinner
                                    v-if="processing"
                                    class="mr-2"
                                />

                                {{
                                    processing
                                        ? 'Creating account...'
                                        : 'Create account'
                                }}
                            </Button>

                            <p
                                class="mt-4 text-center text-sm text-muted-foreground"
                            >
                                Already have an account?

                                <TextLink
                                    :href="login()"
                                    class="font-medium underline underline-offset-4"
                                    :tabindex="10"
                                >
                                    Log in
                                </TextLink>
                            </p>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>