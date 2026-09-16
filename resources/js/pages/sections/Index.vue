<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    Pencil,
    Trash2,
    Users,
    BookOpen,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Section = {
    id: number;
    name: string;
    schedule: string | null;
    room: string | null;
    enrollments_count: number;
    semester: {
        id: number;
        name: string;
        school_year: string;
        is_active: boolean;
    };
    subject: {
        id: number;
        code: string;
        name: string;
    };
    faculty: {
        id: number;
        name: string;
    };
};

type Semester = {
    id: number;
    name: string;
    school_year: string;
    is_active: boolean;
};

const props = defineProps<{
    sections: Section[];
    semesters: Semester[];
    selectedSemesterId: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Sections', href: '/sections' },
        ],
    },
});

function deleteSection(id: number) {
    if (
        confirm(
            'Delete this section? All enrollments will also be removed.',
        )
    ) {
        router.delete(`/sections/${id}`);
    }
}

function changeSemester(value: unknown) {
    if (typeof value !== 'string') {
        return;
    }

    router.get(
        '/sections',
        { semester_id: value },
        {
            preserveState: true,
            replace: true,
        },
    );
}
</script>

<template>
    <Head title="Sections" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <!-- Title -->
            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">
                    Sections
                </h1>

                <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                    Manage class sections per semester
                </p>
            </div>

            <!-- Filters / Actions -->
            <div
                class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto"
            >
                <!-- Semester -->
                <Select
                    :model-value="
                        props.selectedSemesterId?.toString() ?? ''
                    "
                    @update:model-value="changeSemester"
                >
                    <SelectTrigger class="h-9 w-full sm:w-56">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="semester in props.semesters"
                            :key="semester.id"
                            :value="semester.id.toString()"
                        >
                            {{ semester.name }}
                            {{ semester.school_year }}

                            <span
                                v-if="semester.is_active"
                                class="ml-1 text-xs text-green-600"
                            >
                                (Active)
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- New Section -->
                <Button
                    as-child
                    class="w-full sm:w-auto"
                >
                    <Link href="/sections/create">
                        <Plus class="mr-2 h-4 w-4 shrink-0" />
                        New Section
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- EMPTY STATE -->
        <!-- ========================================================= -->
        <div
            v-if="sections.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            <div>
                <p>No sections yet.</p>

                <p class="mt-1">
                    Create your first section to get started.
                </p>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SECTION GRID -->
        <!-- ========================================================= -->
        <div
            v-else
            class="grid min-w-0 grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="section in sections"
                :key="section.id"
                class="flex min-w-0 flex-col rounded-xl border bg-card p-4 transition-colors hover:bg-muted/20"
            >
                <!-- Section Header -->
                <div
                    class="flex min-w-0 items-start justify-between gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <h3
                            class="truncate font-semibold"
                            :title="section.name"
                        >
                            {{ section.name }}
                        </h3>

                        <p
                            class="mt-1 truncate text-sm text-muted-foreground"
                            :title="`${section.subject.code} — ${section.subject.name}`"
                        >
                            {{ section.subject.code }}
                            —
                            {{ section.subject.name }}
                        </p>
                    </div>

                    <Badge
                        :variant="
                            section.semester.is_active
                                ? 'default'
                                : 'secondary'
                        "
                        class="shrink-0 text-xs"
                    >
                        {{ section.semester.name }}
                    </Badge>
                </div>

                <!-- Section Information -->
                <div
                    class="mt-4 space-y-2 text-xs text-muted-foreground"
                >
                    <!-- Schedule -->
                    <div
                        v-if="section.schedule"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <BookOpen
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        />

                        <span class="break-words">
                            {{ section.schedule }}
                        </span>
                    </div>

                    <!-- Room -->
                    <div
                        v-if="section.room"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <span class="w-3.5 shrink-0 text-center">
                            •
                        </span>

                        <span class="break-words">
                            Room {{ section.room }}
                        </span>
                    </div>

                    <!-- Students -->
                    <div class="flex items-center gap-2">
                        <Users class="h-3.5 w-3.5 shrink-0" />

                        <span>
                            {{ section.enrollments_count }}
                            student{{
                                section.enrollments_count !== 1
                                    ? 's'
                                    : ''
                            }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div
                    class="mt-5 grid grid-cols-[1fr_auto_auto] gap-2 border-t pt-4"
                >
                    <!-- View -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-w-0"
                        as-child
                    >
                        <Link
                            :href="`/sections/${section.id}`"
                            class="truncate"
                        >
                            View Class
                        </Link>
                    </Button>

                    <!-- Edit -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-9 p-0"
                        as-child
                    >
                        <Link
                            :href="`/sections/${section.id}/edit`"
                            :aria-label="`Edit ${section.name}`"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </Link>
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-9 p-0 text-destructive hover:text-destructive"
                        :aria-label="`Delete ${section.name}`"
                        @click="deleteSection(section.id)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>