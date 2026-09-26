<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

import {
    ArrowLeft,
    BarChart2,
    CheckCircle2,
    ClipboardList,
    Clock,
    Edit,
    FileText,
    Loader2,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';

import BaseTable from '@/components/BaseTable.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import { showApiError, showApiToast } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Choice = {
    id?: string;
    choice_text: string;
    is_correct: boolean;
    order?: number;
};

type Question = {
    id?: string;
    question: string;
    points: number;
    order?: number;
    choices: Choice[];
};

type Module = {
    id: string;
    title: string;
};

type GradingComponent = {
    id: string;
    name: string;
    period: string | null;
    weight_percentage: number;
};

type Section = {
    id: string;
    name: string;
    schedule?: string | null;
    subject: {
        id?: string;
        code: string;
        name: string;
    } | null;
    semester: {
        id?: string;
        name: string;
        school_year: string;
    } | null;
};

type Assignment = {
    id: string;
    title: string;
    instructions: string;
    type: string;
    period: string | null;
    category: string | null;

    component_id: string | null;

    grading_component: GradingComponent | null;

    due_date: string | null;
    max_score: number;
    passing_score: number | null;
    is_published: boolean;

    rubric: string | null;
    language: string | null;
    answer_release_at: string | null;

    module: Module | null;
    duration_minutes: number | null;
    proctoring_enabled: boolean;

    submissions_count?: number;

    questions: Question[];

    section: Section | null;
};

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
        | Record<string, string | number | boolean>
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
            {
                title: 'View Assignment',
                href: '#',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Assignment Helpers
|--------------------------------------------------------------------------
*/

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not set';
    }

    return new Date(value).toLocaleString();
}

function typeLabel(type: string): string {
    return (
        {
            essay: 'Essay',
            mcq: 'Multiple Choice',
            code: 'Code Assignment',
        } as Record<string, string>
    )[type] ?? type;
}

function categoryLabel(category: string | null): string {
    if (!category) {
        return 'None';
    }

    return (
        {
            quiz: 'Quiz',
            exam: 'Exam',
            activity: 'Activity',
            project: 'Project',
        } as Record<string, string>
    )[category] ?? category;
}

function periodLabel(period: string | null): string {
    if (!period) {
        return 'None';
    }

    return (
        {
            midterm: 'Midterm',
            finals: 'Finals',
        } as Record<string, string>
    )[period] ?? period;
}

function moduleTitle(): string {
    return props.assignment.module?.title ?? 'None';
}

function componentName(): string {
    const component = props.assignment.grading_component;

    if (!component) {
        return 'None';
    }

    return `${component.name} (${component.weight_percentage}%)`;
}

/*
|--------------------------------------------------------------------------
| Submissions State
|--------------------------------------------------------------------------
*/

const submissions = ref<Submission[]>([]);

const pagination = ref<Pagination | null>(null);

const search = ref('');

const loading = ref(false);

const error = ref<string | null>(null);

const processingRelease = ref(false);

const processingPlagiarism = ref(false);

const expandedSubmissionId = ref<string | null>(null);

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

            updateCounts();
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
| Plagiarism
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
| Proctoring
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
| Submission Counts
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

onMounted(() => {
    loadSubmissions();
});
</script>

<template>
    <Head :title="`View - ${assignment.title}`" />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
    >
        <!-- ============================================================ -->
        <!-- Header -->
        <!-- ============================================================ -->

        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
                >
                    <ClipboardList
                        class="h-5 w-5 text-primary"
                    />
                </div>

                <div>
                    <div
                        class="flex flex-wrap items-center gap-2"
                    >
                        <h1
                            class="text-xl font-semibold"
                        >
                            {{ assignment.title }}
                        </h1>

                        <Badge
                            :variant="
                                assignment.is_published
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                assignment.is_published
                                    ? 'Published'
                                    : 'Draft'
                            }}
                        </Badge>
                    </div>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            assignment.section
                                ?.subject?.code ??
                            'No subject'
                        }}
                        ·
                        {{
                            assignment.section
                                ?.name ??
                            'No section'
                        }}
                        ·
                        {{
                            assignment.section
                                ?.subject?.name ??
                            'No subject'
                        }}
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        v-if="
                            assignment.section?.id
                        "
                        :href="`/sections/${assignment.section.id}/assignments`"
                    >
                        <ArrowLeft
                            class="mr-1.5 h-4 w-4"
                        />
                        Back
                    </Link>

                    <Link
                        v-else
                        href="/sections"
                    >
                        <ArrowLeft
                            class="mr-1.5 h-4 w-4"
                        />
                        Back
                    </Link>
                </Button>

                <Button
                    size="sm"
                    as-child
                >
                    <Link
                        :href="`/assignments/${assignment.id}/edit`"
                    >
                        <Edit
                            class="mr-1.5 h-4 w-4"
                        />
                        Edit Assignment
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Assignment Overview -->
        <!-- ============================================================ -->

        <div
            class="grid gap-4 lg:grid-cols-3"
        >
            <!-- Main Information -->

            <div
                class="space-y-7 rounded-xl border bg-card p-5 lg:col-span-2"
            >
                <!-- Assignment Header -->

                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Assignment
                    </p>

                    <h2
                        class="mt-1 text-lg font-semibold"
                    >
                        {{ assignment.title }}
                    </h2>
                </div>

                <!-- Instructions -->

                <div class="space-y-3">
                    <div
                        class="flex items-center gap-2"
                    >
                        <FileText
                            class="h-4 w-4 text-primary"
                        />

                        <h3
                            class="text-sm font-semibold"
                        >
                            Instructions
                        </h3>
                    </div>

                    <div class="pl-6">
                        <p
                            class="text-sm leading-7 whitespace-pre-wrap text-muted-foreground"
                        >
                            {{
                                assignment.instructions ||
                                'No instructions provided.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Rubric -->

                <div
                    v-if="
                        assignment.rubric &&
                        assignment.type !== 'mcq'
                    "
                    class="space-y-3"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <FileText
                            class="h-4 w-4 text-primary"
                        />

                        <h3
                            class="text-sm font-semibold"
                        >
                            Rubric / Grading Criteria
                        </h3>
                    </div>

                    <div class="pl-6">
                        <p
                            class="text-sm leading-7 whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ assignment.rubric }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Assignment Details -->

            <div
                class="space-y-4 rounded-xl border bg-card p-5"
            >
                <div>
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Details
                    </p>

                    <h2
                        class="mt-1 font-semibold"
                    >
                        Assignment Information
                    </h2>
                </div>

                <div class="space-y-3">
                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Type
                        </span>

                        <Badge variant="outline">
                            {{
                                typeLabel(
                                    assignment.type,
                                )
                            }}
                        </Badge>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Category
                        </span>

                        <span
                            class="text-sm font-medium capitalize"
                        >
                            {{
                                categoryLabel(
                                    assignment.category,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Period
                        </span>

                        <span
                            class="text-sm font-medium capitalize"
                        >
                            {{
                                periodLabel(
                                    assignment.period,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Max Score
                        </span>

                        <span
                            class="text-sm font-semibold"
                        >
                            {{ assignment.max_score }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Passing Score
                        </span>

                        <span
                            class="text-sm font-medium"
                        >
                            {{
                                assignment.passing_score ??
                                'Not set'
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Due Date
                        </span>

                        <span
                            class="text-right text-sm font-medium"
                        >
                            {{
                                formatDate(
                                    assignment.due_date,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        v-if="
                            assignment.type ===
                            'mcq'
                        "
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Answer Release
                        </span>

                        <span
                            class="text-right text-sm font-medium"
                        >
                            {{
                                formatDate(
                                    assignment.answer_release_at,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        v-if="
                            assignment.category ===
                                'exam' ||
                            assignment.proctoring_enabled
                        "
                        class="flex items-center justify-between gap-4"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Duration
                        </span>

                        <span
                            class="text-sm font-medium"
                        >
                            {{
                                assignment.duration_minutes
                                    ? `${assignment.duration_minutes} minutes`
                                    : 'Not set'
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Additional Settings -->
        <!-- ============================================================ -->

        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <!-- Grading Component -->

            <div
                class="rounded-xl border p-4"
            >
                <div
                    class="mb-2 flex items-center gap-2"
                >
                    <FileText
                        class="h-4 w-4 text-muted-foreground"
                    />

                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Grading Component
                    </span>
                </div>

                <p
                    class="text-sm font-medium"
                >
                    {{ componentName() }}
                </p>
            </div>

            <!-- Linked Module -->

            <div
                class="rounded-xl border p-4"
            >
                <div
                    class="mb-2 flex items-center gap-2"
                >
                    <FileText
                        class="h-4 w-4 text-muted-foreground"
                    />

                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Linked Module
                    </span>
                </div>

                <p
                    class="text-sm font-medium"
                >
                    {{ moduleTitle() }}
                </p>
            </div>

            <!-- Programming Language -->

            <div
                v-if="
                    assignment.type === 'code'
                "
                class="rounded-xl border p-4"
            >
                <div
                    class="mb-2 flex items-center gap-2"
                >
                    <FileText
                        class="h-4 w-4 text-muted-foreground"
                    />

                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Programming Language
                    </span>
                </div>

                <p
                    class="text-sm font-medium capitalize"
                >
                    {{
                        assignment.language ??
                        'Not set'
                    }}
                </p>
            </div>

            <!-- Exam Monitoring -->

            <div
                class="rounded-xl border p-4"
            >
                <div
                    class="mb-2 flex items-center gap-2"
                >
                    <ShieldCheck
                        class="h-4 w-4 text-muted-foreground"
                    />

                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Exam Monitoring
                    </span>
                </div>

                <p
                    class="text-sm font-medium"
                >
                    {{
                        assignment.proctoring_enabled
                            ? 'Enabled'
                            : 'Disabled'
                    }}
                </p>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MCQ Questions -->
        <!-- ============================================================ -->

        <div
            v-if="assignment.type === 'mcq'"
            class="space-y-4"
        >
            <div
                class="flex items-center justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-semibold"
                    >
                        Questions
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            assignment.questions
                                .length
                        }}
                        question{{
                            assignment.questions
                                .length === 1
                                ? ''
                                : 's'
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="
                    assignment.questions
                        .length === 0
                "
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No questions have been added.
            </div>

            <div
                v-for="(
                    question, questionIndex
                ) in assignment.questions"
                :key="
                    question.id ??
                    questionIndex
                "
                class="space-y-3 rounded-xl border bg-card p-5"
            >
                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div
                        class="flex gap-3"
                    >
                        <span
                            class="mt-0.5 text-sm font-semibold text-muted-foreground"
                        >
                            Q{{
                                questionIndex +
                                1
                            }}
                        </span>

                        <p
                            class="text-sm leading-relaxed font-medium whitespace-pre-wrap"
                        >
                            {{
                                question.question
                            }}
                        </p>
                    </div>

                    <Badge
                        variant="outline"
                        class="shrink-0"
                    >
                        {{ question.points }}
                        point{{
                            question.points ===
                            1
                                ? ''
                                : 's'
                        }}
                    </Badge>
                </div>

                <div
                    class="space-y-2 pl-7"
                >
                    <div
                        v-for="(
                            choice,
                            choiceIndex
                        ) in question.choices"
                        :key="
                            choice.id ??
                            choiceIndex
                        "
                        class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm"
                        :class="
                            choice.is_correct
                                ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
                                : ''
                        "
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs"
                        >
                            {{
                                String.fromCharCode(
                                    65 +
                                        choiceIndex,
                                )
                            }}
                        </span>

                        <span
                            class="flex-1"
                        >
                            {{
                                choice.choice_text
                            }}
                        </span>

                        <CheckCircle2
                            v-if="
                                choice.is_correct
                            "
                            class="h-4 w-4 text-green-600"
                        />

                        <span
                            v-if="
                                choice.is_correct
                            "
                            class="text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            Correct
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Non-MCQ Content -->
        <!-- ============================================================ -->

        <div
            v-else
            class="rounded-xl border bg-card p-5"
        >
            <div
                class="flex items-center gap-2"
            >
                <Clock
                    class="h-4 w-4 text-muted-foreground"
                />

                <h2
                    class="font-semibold"
                >
                    Submission Information
                </h2>
            </div>

            <div
                class="mt-4 grid gap-4 sm:grid-cols-2"
            >
                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Assignment Type
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            typeLabel(
                                assignment.type,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        assignment.type ===
                        'code'
                    "
                >
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Required Language
                    </p>

                    <p
                        class="mt-1 text-sm font-medium capitalize"
                    >
                        {{
                            assignment.language ??
                            'Not specified'
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Submission Statistics -->
        <!-- ============================================================ -->

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

        <!-- ============================================================ -->
        <!-- Submission Actions -->
        <!-- ============================================================ -->

        <div
            class="flex flex-wrap gap-2"
        >
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
                    assignment.type ===
                        'essay' &&
                    (pagination?.total ?? 0) >=
                        2
                "
                variant="outline"
                size="sm"
                :disabled="
                    processingPlagiarism
                "
                @click="runPlagiarism"
            >
                <Loader2
                    v-if="
                        processingPlagiarism
                    "
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

        <!-- ============================================================ -->
        <!-- Submissions -->
        <!-- ============================================================ -->

        <div class="space-y-4">
            <div>
                <h2
                    class="text-lg font-semibold"
                >
                    Student Submissions
                </h2>

                <p
                    class="text-sm text-muted-foreground"
                >
                    View and review submissions
                    for this assignment.
                </p>
            </div>

            <!-- ======================================================== -->
            <!-- Submissions Table -->
            <!-- ======================================================== -->

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
                <!-- ==================================================== -->
                <!-- Toolbar -->
                <!-- ==================================================== -->

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
                                v-model="
                                    search
                                "
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
                            @click="
                                applyFilters
                            "
                        >
                            Search
                        </Button>
                    </div>
                </template>

                <!-- ==================================================== -->
                <!-- Student -->
                <!-- ==================================================== -->

                <template
                    #cell-student="{
                        row,
                    }"
                >
                    <div
                        class="flex flex-col"
                    >
                        <Link
                            :href="`/submissions/${row.id}/grade`"
                            class="font-medium hover:underline"
                        >
                            {{
                                row.student
                                    .last_name
                            }},
                            {{
                                row.student
                                    .first_name
                            }}
                        </Link>

                        <span
                            class="font-mono text-xs text-muted-foreground"
                        >
                            {{
                                row.student
                                    .student_no
                            }}
                        </span>
                    </div>
                </template>

                <!-- ==================================================== -->
                <!-- Submitted -->
                <!-- ==================================================== -->

                <template
                    #cell-submitted_at="{
                        row,
                    }"
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

                <!-- ==================================================== -->
                <!-- Status -->
                <!-- ==================================================== -->

                <template
                    #cell-status="{ row }"
                >
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

                <!-- ==================================================== -->
                <!-- AI Score -->
                <!-- ==================================================== -->

                <template
                    #cell-ai_score="{ row }"
                >
                    <div
                        class="text-center text-muted-foreground"
                    >
                        <span
                            v-if="
                                row.ai_feedback
                            "
                        >
                            {{
                                row.ai_feedback
                                    .score
                            }}
                            /
                            {{
                                assignment.max_score
                            }}
                        </span>

                        <span v-else>
                            —
                        </span>
                    </div>
                </template>

                <!-- ==================================================== -->
                <!-- Final Score -->
                <!-- ==================================================== -->

                <template
                    #cell-final_score="{
                        row,
                    }"
                >
                    <div
                        class="text-center"
                    >
                        <span
                            v-if="row.grade"
                            class="font-semibold"
                        >
                            {{
                                row.grade
                                    .raw_score
                            }}
                            /
                            {{
                                row.grade
                                    .max_score
                            }}
                        </span>

                        <span
                            v-else
                            class="text-muted-foreground"
                        >
                            —
                        </span>
                    </div>
                </template>

                <!-- ==================================================== -->
                <!-- Exam Alerts -->
                <!-- ==================================================== -->

                <template
                    #cell-proctoring_alerts="{
                        row,
                    }"
                >
                    <div
                        class="text-center"
                    >
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

                <!-- ==================================================== -->
                <!-- Actions -->
                <!-- ==================================================== -->

                <template
                    #cell-actions="{ row }"
                >
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
        </div>

        <!-- ============================================================ -->
        <!-- Expanded Proctoring Events -->
        <!-- ============================================================ -->

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

        <!-- ============================================================ -->
        <!-- Error -->
        <!-- ============================================================ -->

        <div
            v-if="error"
            class="text-sm text-destructive"
        >
            {{ error }}
        </div>
    </div>
</template>