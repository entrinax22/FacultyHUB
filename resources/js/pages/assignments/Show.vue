<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

import {
    BarChart2,
    CheckCircle2,
    Loader2,
    Search,
} from 'lucide-vue-next';

import BaseTable from '@/components/BaseTable.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Grade = {
    raw_score: number;
    max_score: number;
    is_released: boolean;
};

type AiFeedback = {
    score: number;
};

type ProctoringEvent = {
    id: string;
    event_type: string;
    metadata:
        | Record<
              string,
              string | number | boolean
          >
        | null;
    created_at: string;
};

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
};

type Submission = {
    id: string;
    status: string;
    submitted_at: string;
    student: Student;
    grade: Grade | null;
    ai_feedback: AiFeedback | null;
    proctoring_events: ProctoringEvent[];
    proctoring_events_count: number;
    proctoring_alerts_count: number;
};

type Assignment = {
    id: string;
    title: string;
    type: string;
    max_score: number;
    is_published: boolean;
    due_date: string | null;
    answer_release_at: string | null;
    section: {
        id: string;
        name: string;
        subject: {
            code: string;
        };
    };
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
};

type SubmissionsResponse = {
    success: boolean;
    message: string;
    data: Submission[];
    pagination: Pagination;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    assignment: Assignment;
    plagiarismRan: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sections',
                href: '/sections',
            },
            {
                title: 'Assignments',
                href: '#',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const submissions = ref<Submission[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');
const loading = ref(false);
const error = ref<string | null>(null);

const processingRelease = ref(false);
const processingPlagiarism = ref(false);

const expandedSubmissionId =
    ref<string | null>(null);

/*
|--------------------------------------------------------------------------
| Table Columns
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: 'student',
        label: 'Student',
    },
    {
        key: 'submitted_at',
        label: 'Submitted',
    },
    {
        key: 'status',
        label: 'Status',
        class: 'text-center',
    },
    {
        key: 'ai_score',
        label: 'AI Score',
        class: 'text-center',
    },
    {
        key: 'final_score',
        label: 'Final Score',
        class: 'text-center',
    },
    {
        key: 'proctoring_alerts',
        label: 'Exam Alerts',
        class: 'text-center',
    },
    {
        key: 'actions',
        label: 'Actions',
        class: 'text-right',
        headerClass: 'text-right',
    },
];

/*
|--------------------------------------------------------------------------
| Status Variants
|--------------------------------------------------------------------------
*/

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'outline'
> = {
    pending: 'secondary',
    grading: 'outline',
    graded: 'default',
    approved: 'default',
};

/*
|--------------------------------------------------------------------------
| Load Submissions
|--------------------------------------------------------------------------
*/

async function loadSubmissions(
    page = 1,
    perPage?: number,
) {
    loading.value = true;
    error.value = null;

    try {
        const response =
            await axios.get<SubmissionsResponse>(
                `/assignments/${props.assignment.id}/submissions/data`,
                {
                    params: {
                        page,
                        per_page:
                            perPage ??
                            pagination.value?.per_page ??
                            20,
                        search:
                            search.value || undefined,
                    },
                },
            );

        if (response.data.success) {
            submissions.value =
                response.data.data;

            pagination.value =
                response.data.pagination;
        } else {
            error.value =
                response.data.message ||
                'Failed to load submissions.';
        }
    } catch (err: any) {
        console.error(
            'LOAD SUBMISSIONS ERROR:',
            err,
        );

        error.value =
            err.response?.data?.message ||
            'Failed to load submissions. Please try again.';
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

function applyFilters() {
    loadSubmissions(1);
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function changePage(page: number) {
    if (!pagination.value) {
        return;
    }

    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page
    ) {
        return;
    }

    loadSubmissions(page);
}

function changePerPage(perPage: number) {
    loadSubmissions(1, perPage);
}

/*
|--------------------------------------------------------------------------
| Release Grades
|--------------------------------------------------------------------------
*/

async function releaseAll() {
    if (
        !confirm(
            'Release all grades to students?',
        )
    ) {
        return;
    }

    processingRelease.value = true;

    try {
        const response = await axios.post(
            '/grades/release',
            {
                assignment_id:
                    props.assignment.id,
            },
        );

        showApiToast(response);

        await loadSubmissions(
            pagination.value?.current_page ?? 1,
        );
    } catch (error) {
        console.error(
            'RELEASE GRADES ERROR:',
            error,
        );

        showApiError(error);
    } finally {
        processingRelease.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Run Plagiarism
|--------------------------------------------------------------------------
*/

async function runPlagiarism() {
    processingPlagiarism.value = true;

    try {
        const response = await axios.post(
            `/assignments/${props.assignment.id}/plagiarism/run`,
        );

        showApiToast(response);
    } catch (error) {
        console.error(
            'RUN PLAGIARISM ERROR:',
            error,
        );

        showApiError(error);
    } finally {
        processingPlagiarism.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Proctoring Alerts
|--------------------------------------------------------------------------
*/

function toggleAlerts(
    submissionId: string,
) {
    expandedSubmissionId.value =
        expandedSubmissionId.value ===
        submissionId
            ? null
            : submissionId;
}

function eventLabel(
    eventType: string,
): string {
    return (
        {
            tab_hidden: 'Left exam tab',
            tab_visible: 'Returned to exam tab',
            window_resized: 'Window resized',
            camera_permission_denied:
                'Camera permission denied',
            exam_started: 'Exam started',
            heartbeat: 'Connection heartbeat',
            fullscreen_entered:
                'Fullscreen enabled',
            fullscreen_exited:
                'Fullscreen exited',
            copy_detected: 'Content copied',
            paste_detected: 'Content pasted',
            cut_detected: 'Content cut',
            context_menu_used:
                'Context menu used',
            print_screen_suspected:
                'Possible screenshot shortcut detected',
        } as Record<string, string>
    )[eventType] ?? eventType;
}

function eventMetadata(
    event: ProctoringEvent,
): string {
    if (
        event.event_type !==
            'window_resized' ||
        !event.metadata
    ) {
        return '';
    }

    return `${event.metadata.width ?? '?'} × ${
        event.metadata.height ?? '?'
    }`;
}

/*
|--------------------------------------------------------------------------
| Computed Counts
|--------------------------------------------------------------------------
*/

const approvedCount = ref(0);
const gradedCount = ref(0);

function updateCounts() {
    approvedCount.value =
        submissions.value.filter(
            (submission) =>
                submission.status ===
                'approved',
        ).length;

    gradedCount.value =
        submissions.value.filter(
            (submission) =>
                [
                    'graded',
                    'approved',
                ].includes(
                    submission.status,
                ),
        ).length;
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    await loadSubmissions();
    updateCounts();
});
</script>

<template>
    <Head :title="assignment.title" />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
    >

        <!-- Header -->
        <div
            class="flex items-start justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold"
                >
                    {{ assignment.title }}
                </h1>

                <p
                    class="text-sm text-muted-foreground"
                >
                    {{ assignment.section.subject.code }}
                    ·
                    {{ assignment.section.name }}

                    <span
                        v-if="assignment.due_date"
                    >
                        · Due
                        {{
                            new Date(
                                assignment.due_date,
                            ).toLocaleString()
                        }}
                    </span>
                </p>
            </div>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="`/assignments/${assignment.id}/edit`"
                    >
                        Edit
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div
            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
        >
            <div
                class="rounded-xl border p-3 text-center"
            >
                <p
                    class="text-2xl font-bold"
                >
                    {{ pagination?.total ?? 0 }}
                </p>

                <p
                    class="text-xs text-muted-foreground"
                >
                    Submitted
                </p>
            </div>

            <div
                class="rounded-xl border p-3 text-center"
            >
                <p
                    class="text-2xl font-bold"
                >
                    {{ gradedCount }}
                </p>

                <p
                    class="text-xs text-muted-foreground"
                >
                    Graded
                </p>
            </div>

            <div
                class="rounded-xl border p-3 text-center"
            >
                <p
                    class="text-2xl font-bold"
                >
                    {{ approvedCount }}
                </p>

                <p
                    class="text-xs text-muted-foreground"
                >
                    Approved
                </p>
            </div>

            <div
                class="rounded-xl border p-3 text-center"
            >
                <p
                    class="text-2xl font-bold"
                >
                    {{ assignment.max_score }}
                </p>

                <p
                    class="text-xs text-muted-foreground"
                >
                    Max Score
                </p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2">

            <Button
                v-if="approvedCount > 0"
                variant="outline"
                size="sm"
                :disabled="
                    processingRelease ||
                    loading
                "
                @click="releaseAll"
            >
                <Loader2
                    v-if="processingRelease"
                    class="mr-1.5 h-4 w-4 animate-spin"
                />

                <CheckCircle2
                    v-else
                    class="mr-1.5 h-4 w-4"
                />

                Release All Grades
            </Button>

            <Button
                v-if="
                    assignment.type === 'essay' &&
                    (pagination?.total ?? 0) >= 2
                "
                variant="outline"
                size="sm"
                :disabled="
                    processingPlagiarism
                "
                @click="runPlagiarism"
            >
                <Loader2
                    v-if="processingPlagiarism"
                    class="mr-1.5 h-4 w-4 animate-spin"
                />

                <BarChart2
                    v-else
                    class="mr-1.5 h-4 w-4"
                />

                {{
                    plagiarismRan
                        ? 'Re-run'
                        : 'Run'
                }}
                Plagiarism Check
            </Button>

            <Button
                v-if="plagiarismRan"
                variant="ghost"
                size="sm"
                as-child
            >
                <Link
                    :href="`/assignments/${assignment.id}/plagiarism`"
                >
                    View Plagiarism Report
                </Link>
            </Button>
        </div>

        <!-- Submissions Table -->
        <BaseTable
            :columns="columns"
            :data="submissions"
            :pagination="
                pagination ?? undefined
            "
            empty-text="No submissions yet."
            :per-page-options="[
                10,
                20,
                25,
                50,
                100,
            ]"
            :loading="loading"
            @update:page="changePage"
            @update:per-page="
                changePerPage
            "
        >

            <!-- Toolbar -->
            <template #toolbar>
                <div
                    class="flex w-full flex-wrap gap-3"
                >
                    <div
                        class="relative min-w-48 flex-1"
                    >
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            v-model="search"
                            placeholder="Search student..."
                            class="pl-9"
                            @keydown.enter="
                                applyFilters
                            "
                        />
                    </div>

                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="applyFilters"
                    >
                        Search
                    </Button>
                </div>
            </template>

            <!-- Student -->
            <template #cell-student="{ row }">
                <div
                    class="flex flex-col"
                >
                    <Link
                        :href="`/submissions/${row.id}/grade`"
                        class="font-medium hover:underline"
                    >
                        {{ row.student.last_name }},
                        {{ row.student.first_name }}
                    </Link>

                    <span
                        class="font-mono text-xs text-muted-foreground"
                    >
                        {{ row.student.student_no }}
                    </span>
                </div>
            </template>

            <!-- Submitted -->
            <template
                #cell-submitted_at="{ row }"
            >
                <span
                    class="text-xs text-muted-foreground"
                >
                    {{
                        new Date(
                            row.submitted_at,
                        ).toLocaleString()
                    }}
                </span>
            </template>

            <!-- Status -->
            <template #cell-status="{ row }">
                <div
                    class="flex justify-center"
                >
                    <Badge
                        :variant="
                            statusVariant[
                                row.status
                            ] ??
                            'secondary'
                        "
                        class="capitalize"
                    >
                        <Loader2
                            v-if="
                                row.status ===
                                'grading'
                            "
                            class="mr-1 h-3 w-3 animate-spin"
                        />

                        {{ row.status }}
                    </Badge>
                </div>
            </template>

            <!-- AI Score -->
            <template #cell-ai_score="{ row }">
                <div
                    class="text-center text-muted-foreground"
                >
                    <span
                        v-if="row.ai_feedback"
                    >
                        {{
                            row.ai_feedback.score
                        }}
                        /
                        {{
                            assignment.max_score
                        }}
                    </span>

                    <span v-else>—</span>
                </div>
            </template>

            <!-- Final Score -->
            <template
                #cell-final_score="{ row }"
            >
                <div class="text-center">
                    <span
                        v-if="row.grade"
                        class="font-semibold"
                    >
                        {{ row.grade.raw_score }}
                        /
                        {{ row.grade.max_score }}
                    </span>

                    <span
                        v-else
                        class="text-muted-foreground"
                    >
                        —
                    </span>
                </div>
            </template>

            <!-- Exam Alerts -->
            <template
                #cell-proctoring_alerts="{ row }"
            >
                <div class="text-center">
                    <Button
                        v-if="
                            row.proctoring_alerts_count >
                            0
                        "
                        variant="outline"
                        size="sm"
                        class="text-amber-700 dark:text-amber-300"
                        @click="
                            toggleAlerts(
                                row.id,
                            )
                        "
                    >
                        {{
                            row.proctoring_alerts_count
                        }}
                        alert{{
                            row.proctoring_alerts_count ===
                            1
                                ? ''
                                : 's'
                        }}
                    </Button>

                    <span
                        v-else
                        class="text-muted-foreground"
                    >
                        None
                    </span>
                </div>
            </template>

            <!-- Actions -->
            <template #cell-actions="{ row }">
                <div
                    class="flex justify-end gap-1"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="`/submissions/${row.id}/grade`"
                        >
                            {{
                                row.status ===
                                'approved'
                                    ? 'View'
                                    : 'Review'
                            }}
                        </Link>
                    </Button>
                </div>
            </template>

        </BaseTable>

        <!-- Expanded Proctoring Events -->
        <div
            v-if="expandedSubmissionId"
            class="rounded-xl border bg-amber-50/60 p-4 dark:bg-amber-950/20"
        >
            <template
                v-for="sub in submissions"
                :key="sub.id"
            >
                <div
                    v-if="
                        expandedSubmissionId ===
                        sub.id
                    "
                >
                    <div
                        class="flex items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="font-semibold"
                            >
                                Exam activity alerts
                            </p>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Showing the latest
                                recorded events for
                                this student.
                            </p>
                        </div>

                        <Badge
                            variant="destructive"
                        >
                            {{
                                sub.proctoring_alerts_count
                            }}
                            alerts
                        </Badge>
                    </div>

                    <div
                        class="mt-3 space-y-2"
                    >
                        <div
                            v-for="event in sub.proctoring_events"
                            :key="event.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <span
                                class="font-medium"
                            >
                                {{
                                    eventLabel(
                                        event.event_type,
                                    )
                                }}
                            </span>

                            <span
                                v-if="
                                    eventMetadata(
                                        event,
                                    )
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    eventMetadata(
                                        event,
                                    )
                                }}
                            </span>

                            <time
                                class="text-xs text-muted-foreground"
                                :datetime="
                                    event.created_at
                                "
                            >
                                {{
                                    new Date(
                                        event.created_at,
                                    ).toLocaleString()
                                }}
                            </time>
                        </div>

                        <p
                            v-if="
                                sub
                                    .proctoring_events
                                    .length === 0
                            "
                            class="text-sm text-muted-foreground"
                        >
                            No event details are
                            available.
                        </p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="text-sm text-destructive"
        >
            {{ error }}
        </div>
    </div>
</template>