<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { CalendarDays } from 'lucide-vue-next';

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

type Semester = {
    id: string;
    name: string;
    school_year: string;
    start_date: string;
    end_date: string;
};

type SemesterForm = {
    name: string;
    school_year: string;
    start_date: string;
    end_date: string;
};

const props = defineProps<{
    semester?: Semester;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Semesters', href: '/admin/semesters' },
        ],
    },
});

const semesterNames = [
    '1st Semester',
    '2nd Semester',
    'Summer',
];

const form = ref<SemesterForm>({
    name: props.semester?.name ?? '',
    school_year: props.semester?.school_year ?? '',
    start_date: props.semester?.start_date ?? '',
    end_date: props.semester?.end_date ?? '',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

function clearErrors() {
    errors.value = {};
}

function getValidationErrors(error: any) {
    if (error.response?.status === 422) {
        errors.value =
            error.response.data.errors ?? {};
    }
}

async function submit() {
    clearErrors();

    processing.value = true;

    try {
        let response;

        if (props.semester) {
            response = await axios.put(
                `/admin/semesters/update/${props.semester.id}`,
                form.value,
            );
        } else {
            response = await axios.post(
                '/admin/semesters/store',
                form.value,
            );
        }

        showApiToast(response);

        if (response.data.success) {
            window.location.href =
                '/admin/semesters';
        }
    } catch (error: any) {
        console.error(
            'Failed to save semester:',
            error,
        );

        getValidationErrors(error);

        if (error.response?.status !== 422) {
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
            semester
                ? 'Edit Semester'
                : 'New Semester'
        "
    />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
    >
        <!-- Page header -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <CalendarDays
                    class="h-5 w-5 text-primary"
                />
            </div>

            <div>
                <h1 class="text-xl font-semibold">
                    {{
                        semester
                            ? 'Edit Semester'
                            : 'New Semester'
                    }}
                </h1>

                <p
                    class="text-sm text-muted-foreground"
                >
                    {{
                        semester
                            ? 'Update semester details'
                            : 'Create a new academic semester'
                    }}
                </p>
            </div>
        </div>

        <!-- Form card -->
        <form
            class="max-w-xl space-y-5 rounded-xl border bg-card p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <!-- Semester Name -->
            <div class="grid gap-1.5">
                <Label>
                    Semester Name
                </Label>

                <Select
                    v-model="form.name"
                    required
                >
                    <SelectTrigger>
                        <SelectValue
                            placeholder="Select semester"
                        />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="name in semesterNames"
                            :key="name"
                            :value="name"
                        >
                            {{ name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <InputError
                    :message="errors.name"
                />
            </div>

            <!-- School Year -->
            <div class="grid gap-1.5">
                <Label for="school_year">
                    School Year
                </Label>

                <Input
                    id="school_year"
                    v-model="form.school_year"
                    placeholder="e.g. 2025-2026"
                    maxlength="20"
                    required
                />

                <InputError
                    :message="errors.school_year"
                />
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="start_date">
                        Start Date
                    </Label>

                    <Input
                        id="start_date"
                        v-model="form.start_date"
                        type="date"
                        required
                    />

                    <InputError
                        :message="errors.start_date"
                    />
                </div>

                <div class="grid gap-1.5">
                    <Label for="end_date">
                        End Date
                    </Label>

                    <Input
                        id="end_date"
                        v-model="form.end_date"
                        type="date"
                        required
                    />

                    <InputError
                        :message="errors.end_date"
                    />
                </div>
            </div>

            <!-- Actions -->
            <div
                class="flex gap-3 border-t pt-5"
            >
                <Button
                    type="submit"
                    :disabled="processing"
                >
                    {{
                        processing
                            ? 'Saving...'
                            : semester
                              ? 'Update Semester'
                              : 'Create Semester'
                    }}
                </Button>

                <Button
                    variant="outline"
                    as-child
                >
                    <Link
                        href="/admin/semesters"
                    >
                        Cancel
                    </Link>
                </Button>
            </div>
        </form>
    </div>
</template>

