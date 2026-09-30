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
        class="min-h-svh bg-muted px-4 py-6 sm:px-6 lg:px-10 lg:py-10 xl:px-12"
    >
        <div
            class="mx-auto flex w-full max-w-7xl items-center justify-center"
        >
            <div
                class="w-full overflow-hidden rounded-3xl border bg-background shadow-xl"
            >
                <div
                    class="px-6 py-8 sm:px-10 sm:py-10 lg:px-16 lg:py-12 xl:px-20"
                >
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="[
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing }"
                        class="mx-auto flex w-full max-w-5xl flex-col gap-8"
                    >
                        <!-- Header -->
                        <div class="space-y-3">
                            <div
                                class="inline-flex items-center rounded-full border bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground"
                            >
                                Student Registration
                            </div>

                            <div>
                                <h1
                                    class="text-2xl font-semibold tracking-tight sm:text-3xl"
                                >
                                    Create your account
                                </h1>

                                <p
                                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
                                >
                                    Enter your student information to create
                                    your Faculty-LMS student account.
                                </p>
                            </div>
                        </div>

                        <!-- Student Information -->
                        <section class="space-y-6">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                                >
                                    01
                                </div>

                                <div class="min-w-0 pt-0.5">
                                    <h2 class="text-base font-semibold sm:text-lg">
                                        Student Information
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                    >
                                        Provide your official student details.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-5 sm:grid-cols-2 lg:gap-6"
                            >
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

                                    <p
                                        class="text-xs leading-relaxed text-muted-foreground"
                                    >
                                        Enter your official student number.
                                    </p>

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
                        <div class="border-t"></div>

                        <!-- Account Security -->
                        <section class="space-y-6">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                                >
                                    02
                                </div>

                                <div class="min-w-0 pt-0.5">
                                    <h2 class="text-base font-semibold sm:text-lg">
                                        Account Security
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                    >
                                        Create a secure password for your
                                        account.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-5 sm:grid-cols-2 lg:gap-6"
                            >
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

                        <!-- Submit -->
                        <div class="space-y-4 pt-1">
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
                                class="text-center text-sm leading-relaxed text-muted-foreground"
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