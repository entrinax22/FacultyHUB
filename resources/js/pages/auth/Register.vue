<script setup lang="ts">
import { Form } from '@inertiajs/vue3';

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
    <Form
        v-bind="store.form()"
        :reset-on-success="[
            'password',
            'password_confirmation',
        ]"
        v-slot="{ errors, processing }"
        class="mx-auto w-full max-w-5xl"
    >
        <!-- Page Header -->
        <div class="mb-8">
            <div
                class="mb-3 inline-flex items-center rounded-full border bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground"
            >
                Student Registration
            </div>

            <h1
                class="text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                Create your account
            </h1>

            <p
                class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                Enter your student information to create your
                Faculty-LMS student account.
            </p>
        </div>

        <!-- Student Information -->
        <section>
            <div class="mb-6 flex items-start gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                >
                    01
                </div>

                <div>
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

            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
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
            <div class="mb-6 flex items-start gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                >
                    02
                </div>

                <div>
                    <h2 class="text-base font-semibold sm:text-lg">
                        Account Security
                    </h2>

                    <p
                        class="mt-0.5 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Create a secure password for your account.
                    </p>
                </div>
            </div>

            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
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
                        :message="errors.password_confirmation"
                    />
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="mt-8 border-t pt-6 sm:mt-10 sm:pt-8">
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
</template>