<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    CheckCircle2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Grade = {
    raw_score: number;
    max_score: number;
    is_released: boolean;
};

type Submission = {
    id: number;
    status: string;
    submitted_at: string;
    grade: Grade | null;
};

type Assignment = {
    id: number;
    title: string;
    type: string;
    max_score: number;
    due_date: string | null;
    is_published: boolean;
    my_submission: Submission | null;
    my_grade: Grade | null;
};

type Section = {
    id: number;
    name: string;
    subject: {
        code: string;
        name: string;
    };
    semester: {
        name: string;
        school_year: string;
    };
};

defineProps<{
    section: Section;
    assignments: Assignment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const typeLabel: Record<string, string> = {
    essay: 'Essay',
    mcq: 'Multiple Choice',
    code: 'Code',
};

function isPastDue(dueDate: string | null): boolean {
    if (!dueDate) {
        return false;
    }

    return new Date(dueDate) < new Date();
}
</script>

<template>
    <Head :title="`Assignments — ${section.name}`" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ============================================================= -->
        <!-- HEADER -->
        <!-- ============================================================= -->

        <div
            class="flex min-w-0 items-start gap-2 sm:gap-3"
        >
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 mt-0.5 shrink-0"
            >
                <Link
                    :href="`/my-sections/${section.id}`"
                    aria-label="Back to class"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>

            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">
                    Assignments
                </h1>

                <p
                    class="mt-1 truncate text-xs text-muted-foreground sm:text-sm"
                    :title="`${section.subject.code} · ${section.name} · ${section.semester.name}`"
                >
                    {{ section.subject.code }}
                    ·
                    {{ section.name }}
                    ·
                    {{ section.semester.name }}
                </p>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- EMPTY STATE -->
        <!-- ============================================================= -->

        <div
            v-if="assignments.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            No assignments posted yet.
        </div>

        <!-- ============================================================= -->
        <!-- ASSIGNMENTS -->
        <!-- ============================================================= -->

        <div
            v-else
            class="space-y-3"
        >
            <div
                v-for="a in assignments"
                :key="a.id"
                class="min-w-0 space-y-4 rounded-xl border bg-card p-4 transition-colors sm:p-5"
                :class="{
                    'border-red-200 bg-red-50/30':
                        isPastDue(a.due_date) && !a.my_submission,
                }"
            >
                <!-- ===================================================== -->
                <!-- ASSIGNMENT INFORMATION + STATUS -->
                <!-- ===================================================== -->

                <div
                    class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <!-- Assignment Details -->
                    <div class="min-w-0 flex-1">
                        <h3
                            class="break-words font-semibold sm:text-base"
                        >
                            {{ a.title }}
                        </h3>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted-foreground"
                        >
                            <Badge
                                variant="outline"
                                class="text-xs"
                            >
                                {{ typeLabel[a.type] }}
                            </Badge>

                            <span class="whitespace-nowrap">
                                Max: {{ a.max_score }} pts
                            </span>

                            <span
                                v-if="a.due_date"
                                class="flex flex-wrap items-center gap-1"
                                :class="
                                    isPastDue(a.due_date)
                                        ? 'text-red-600'
                                        : ''
                                "
                            >
                                <Clock class="h-3 w-3 shrink-0" />

                                <span>
                                    {{
                                        isPastDue(a.due_date)
                                            ? 'Due was'
                                            : 'Due'
                                    }}
                                </span>

                                <span>
                                    {{
                                        new Date(
                                            a.due_date,
                                        ).toLocaleString()
                                    }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- STATUS -->
                    <!-- ================================================= -->

                    <div
                        class="flex shrink-0 flex-row items-center justify-between gap-3 border-t pt-3 sm:min-w-[150px] sm:flex-col sm:items-end sm:border-0 sm:pt-0"
                    >
                        <!-- Submitted -->
                        <div
                            v-if="a.my_submission"
                            class="flex flex-col items-start gap-1 sm:items-end"
                        >
                            <Badge
                                :variant="
                                    a.my_submission.status === 'approved'
                                        ? 'default'
                                        : 'secondary'
                                "
                                class="capitalize"
                            >
                                <CheckCircle2
                                    v-if="
                                        a.my_submission.status ===
                                        'approved'
                                    "
                                    class="mr-1 h-3 w-3"
                                />

                                {{
                                    a.my_submission.status === 'grading'
                                        ? 'Being graded…'
                                        : a.my_submission.status
                                }}
                            </Badge>

                            <div
                                v-if="a.my_grade?.is_released"
                                class="text-sm font-bold"
                            >
                                {{ a.my_grade.raw_score }}
                                /
                                {{ a.my_grade.max_score }}
                            </div>
                        </div>

                        <!-- Past Due -->
                        <Badge
                            v-else-if="isPastDue(a.due_date)"
                            variant="destructive"
                        >
                            Past Due
                        </Badge>

                        <!-- Not Submitted -->
                        <Badge
                            v-else
                            variant="outline"
                        >
                            Not Submitted
                        </Badge>
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- ACTIONS -->
                <!-- ===================================================== -->

                <div
                    class="flex flex-col gap-2 border-t pt-4 sm:flex-row"
                >
                    <Button
                        v-if="
                            !a.my_submission &&
                            !isPastDue(a.due_date)
                        "
                        size="sm"
                        class="w-full sm:w-auto"
                        as-child
                    >
                        <Link
                            :href="`/assignments/${a.id}/submit`"
                        >
                            Submit Assignment
                        </Link>
                    </Button>

                    <Button
                        v-else-if="
                            a.my_submission &&
                            a.my_submission.status !== 'approved'
                        "
                        size="sm"
                        variant="outline"
                        class="w-full sm:w-auto"
                        as-child
                    >
                        <Link
                            :href="`/assignments/${a.id}/submit`"
                        >
                            Edit Submission
                        </Link>
                    </Button>

                    <Button
                        v-if="a.my_submission"
                        size="sm"
                        variant="ghost"
                        class="w-full sm:w-auto"
                        as-child
                    >
                        <Link
                            :href="`/submissions/${a.my_submission.id}`"
                        >
                            View Result
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>