<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { GraduationCap } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
};

const props = defineProps<{
    student?: Student;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Students',
                href: '/students',
            },
        ],
    },
});

const isEdit = !!props.student;

const form = ref({
    student_no: props.student?.student_no ?? '',
    first_name: props.student?.first_name ?? '',
    last_name: props.student?.last_name ?? '',
    email: props.student?.email ?? '',
    course: props.student?.course ?? '',
    year_level: props.student?.year_level?.toString() ?? '1',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

function clearError(field: string) {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function submit() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        const response = isEdit
            ? await axios.put(
                `/students/update/${props.student!.id}`,
                form.value,
            )
            : await axios.post(
                '/students/store',
                form.value,
            );

        showApiToast(response);

        window.location.href = '/students';
    } catch (error: any) {
        console.error('Failed to save student:', error);

        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            showApiError(error);
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head
        :title="
            isEdit
                ? 'Edit Student'
                : 'Add Student'
        "
    />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <GraduationCap class="h-5 w-5 text-primary" />
            </div>

            <div>
                <h1 class="text-xl font-semibold">
                    {{
                        isEdit
                            ? 'Edit Student'
                            : 'Add Student'
                    }}
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{
                        isEdit
                            ? 'Update student information'
                            : 'Register a new student'
                    }}
                </p>
            </div>
        </div>

        <!-- Form -->
        <form
            @submit.prevent="submit"
            class="max-w-xl space-y-5 rounded-xl border bg-card p-6 shadow-sm"
        >

            <!-- Student Number -->
            <div class="grid gap-1.5">
                <Label for="student_no">
                    Student Number
                </Label>

                <Input
                    id="student_no"
                    v-model="form.student_no"
                    placeholder="e.g. 2023-00001"
                    required
                    :disabled="processing"
                    @input="clearError('student_no')"
                />

                <InputError
                    :message="errors.student_no"
                />
            </div>

            <!-- First Name + Last Name -->
            <div class="grid grid-cols-2 gap-4">

                <div class="grid gap-1.5">
                    <Label for="first_name">
                        First Name
                    </Label>

                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        placeholder="Juan"
                        required
                        :disabled="processing"
                        @input="clearError('first_name')"
                    />

                    <InputError
                        :message="errors.first_name"
                    />
                </div>

                <div class="grid gap-1.5">
                    <Label for="last_name">
                        Last Name
                    </Label>

                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        placeholder="Dela Cruz"
                        required
                        :disabled="processing"
                        @input="clearError('last_name')"
                    />

                    <InputError
                        :message="errors.last_name"
                    />
                </div>

            </div>

            <!-- Email -->
            <div class="grid gap-1.5">
                <Label for="email">
                    Email Address
                </Label>

                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    placeholder="juan@school.edu.ph"
                    required
                    :disabled="processing"
                    @input="clearError('email')"
                />

                <InputError
                    :message="errors.email"
                />
            </div>

            <!-- Course + Year Level -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Course -->
                <div class="grid gap-1.5">
                    <Label for="course">
                        Course / Program
                    </Label>

                    <Input
                        id="course"
                        v-model="form.course"
                        placeholder="e.g. BSIT"
                        required
                        :disabled="processing"
                        @input="clearError('course')"
                    />

                    <InputError
                        :message="errors.course"
                    />
                </div>

                <!-- Year Level -->
                <div class="grid gap-1.5">
                    <Label>
                        Year Level
                    </Label>

                    <Select
                        v-model="form.year_level"
                        :disabled="processing"
                        @update:model-value="clearError('year_level')"
                    >
                        <SelectTrigger>
                            <SelectValue
                                placeholder="Select year"
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

                            <SelectItem value="5">
                                5th Year
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <InputError
                        :message="errors.year_level"
                    />
                </div>

            </div>

            <!-- Actions -->
            <div class="flex gap-3 border-t pt-5">

                <Button
                    type="submit"
                    :disabled="processing"
                >
                    {{
                        processing
                            ? 'Saving...'
                            : isEdit
                                ? 'Update Student'
                                : 'Add Student'
                    }}
                </Button>

                <Button
                    variant="outline"
                    type="button"
                    as-child
                    :disabled="processing"
                >
                    <Link href="/students">
                        Cancel
                    </Link>
                </Button>

            </div>

        </form>
    </div>
</template>