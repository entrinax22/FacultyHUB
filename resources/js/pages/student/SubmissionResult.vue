<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bot,
    CheckCircle2,
    Loader2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Choice = {
    id: number;
    choice_text: string;
    is_correct: boolean;
};

type Question = {
    id: number;
    question: string;
    points: number;
    choices: Choice[];
};

type AiFeedback = {
    score: number;
    feedback_json: {
        overall_comment?: string;
        criterion_feedback?: Array<{
            criterion: string;
            score: number;
            max: number;
            feedback: string;
        }>;
        correctness?: number;
        logic_quality?: number;
        code_quality?: number;
    };
};

type Grade = {
    raw_score: number;
    max_score: number;
    remarks: string | null;
    is_released: boolean;
};

type Submission = {
    id: number;
    status: string;
    content: string | null;
    answers: Record<string, number> | null;
    submitted_at: string;
    ai_feedback: AiFeedback | null;
    grade: Grade | null;
    assignment: {
        id: number;
        title: string;
        type: string;
        max_score: number;
        section: {
            id: number;
            name: string;
            subject: {
                code: string;
            };
        };
        questions: Question[];
    };
};

const props = defineProps<{
    submission: Submission;
    answersReleased: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'Submission Result',
                href: '#',
            },
        ],
    },
});

function getStudentChoice(questionId: number): number | null {
    return props.submission.answers?.[questionId] ?? null;
}
</script>

<template>
    <Head :title="`Result — ${submission.assignment.title}`" />

    <div
        class="flex min-h-full w-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <div class="w-full max-w-3xl space-y-5 sm:space-y-6">
            <!-- ========================================================= -->
            <!-- HEADER -->
            <!-- ========================================================= -->

            <div class="flex min-w-0 items-start gap-2 sm:gap-3">
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="-ml-2 mt-0.5 shrink-0"
                >
                    <Link
                        :href="`/my-sections/${submission.assignment.section.id}/assignments`"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        <span class="sr-only">
                            Back to assignments
                        </span>
                    </Link>
                </Button>

                <div class="min-w-0 flex-1">
                    <h1
                        class="break-words text-xl font-semibold sm:text-2xl"
                    >
                        {{ submission.assignment.title }}
                    </h1>

                    <p
                        class="mt-1 break-words text-xs text-muted-foreground sm:text-sm"
                    >
                        <span class="font-medium text-foreground">
                            {{ submission.assignment.section.subject.code }}
                        </span>
                        ·
                        {{ submission.assignment.section.name }}
                    </p>

                    <p
                        class="mt-0.5 break-words text-xs text-muted-foreground"
                    >
                        Submitted
                        {{
                            new Date(
                                submission.submitted_at,
                            ).toLocaleString()
                        }}
                    </p>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- GRADE CARD -->
            <!-- ========================================================= -->

            <div class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Grade -->
                    <div class="min-w-0">
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Your Grade
                        </p>

                        <div
                            v-if="submission.grade?.is_released"
                            class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                        >
                            <span class="text-3xl font-bold sm:text-4xl">
                                {{ submission.grade.raw_score }}
                            </span>

                            <span
                                class="text-lg text-muted-foreground sm:text-xl"
                            >
                                /
                                {{ submission.grade.max_score }}
                            </span>

                            <span
                                class="text-xs text-muted-foreground sm:text-sm"
                            >
                                ({{
                                    (
                                        (submission.grade.raw_score /
                                            submission.grade
                                                .max_score) *
                                        100
                                    ).toFixed(1)
                                }}%)
                            </span>
                        </div>

                        <p
                            v-else
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Grade not yet released.
                        </p>
                    </div>

                    <!-- Status -->
                    <Badge
                        :variant="
                            submission.status === 'approved'
                                ? 'default'
                                : 'secondary'
                        "
                        class="w-fit shrink-0 px-3 py-1 text-sm capitalize"
                    >
                        <CheckCircle2
                            v-if="submission.status === 'approved'"
                            class="mr-1.5 h-4 w-4 shrink-0"
                        />

                        <Loader2
                            v-else-if="
                                submission.status === 'grading'
                            "
                            class="mr-1.5 h-4 w-4 shrink-0 animate-spin"
                        />

                        {{
                            submission.status === 'grading'
                                ? 'Being graded…'
                                : submission.status
                        }}
                    </Badge>
                </div>

                <!-- Remarks -->
                <p
                    v-if="
                        submission.grade?.remarks &&
                        submission.grade.is_released
                    "
                    class="mt-4 rounded-lg bg-muted/30 p-3 text-sm leading-relaxed text-muted-foreground"
                >
                    {{ submission.grade.remarks }}
                </p>
            </div>

            <!-- ========================================================= -->
            <!-- AI FEEDBACK -->
            <!-- ========================================================= -->

            <div
                v-if="
                    submission.ai_feedback &&
                    submission.grade?.is_released &&
                    submission.assignment.type !== 'mcq'
                "
                class="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-5"
            >
                <!-- AI Header -->
                <div class="flex items-center gap-2">
                    <Bot
                        class="h-4 w-4 shrink-0 text-blue-500"
                    />

                    <span class="text-sm font-semibold">
                        AI Feedback
                    </span>
                </div>

                <!-- Overall comment -->
                <p
                    v-if="
                        submission.ai_feedback.feedback_json
                            .overall_comment
                    "
                    class="break-words text-sm leading-relaxed text-muted-foreground"
                >
                    {{
                        submission.ai_feedback.feedback_json
                            .overall_comment
                    }}
                </p>

                <!-- Criteria -->
                <div
                    v-if="
                        submission.ai_feedback.feedback_json
                            .criterion_feedback?.length
                    "
                    class="space-y-2"
                >
                    <div
                        v-for="cf in submission.ai_feedback
                            .feedback_json.criterion_feedback"
                        :key="cf.criterion"
                        class="rounded-lg bg-muted/30 p-3 text-sm"
                    >
                        <div
                            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="break-words font-medium">
                                {{ cf.criterion }}
                            </span>

                            <span
                                class="shrink-0 text-xs font-medium sm:text-sm"
                            >
                                {{ cf.score }} / {{ cf.max }}
                            </span>
                        </div>

                        <p
                            class="mt-1 break-words text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ cf.feedback }}
                        </p>
                    </div>
                </div>

                <!-- Code metrics -->
                <div
                    v-if="submission.assignment.type === 'code'"
                    class="grid grid-cols-1 gap-2 sm:grid-cols-3"
                >
                    <div
                        class="rounded-lg border p-3 text-center"
                    >
                        <p class="text-lg font-semibold">
                            {{
                                submission.ai_feedback
                                    .feedback_json
                                    .correctness ?? '—'
                            }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            Correctness
                        </p>
                    </div>

                    <div
                        class="rounded-lg border p-3 text-center"
                    >
                        <p class="text-lg font-semibold">
                            {{
                                submission.ai_feedback
                                    .feedback_json
                                    .logic_quality ?? '—'
                            }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            Logic
                        </p>
                    </div>

                    <div
                        class="rounded-lg border p-3 text-center"
                    >
                        <p class="text-lg font-semibold">
                            {{
                                submission.ai_feedback
                                    .feedback_json
                                    .code_quality ?? '—'
                            }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            Code Quality
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MCQ ANSWERS -->
            <!-- ========================================================= -->

            <div
                v-if="submission.assignment.type === 'mcq'"
                class="min-w-0 space-y-3"
            >
                <p class="text-sm font-semibold">
                    Your Answers
                </p>

                <div
                    v-for="(q, qi) in submission.assignment.questions"
                    :key="q.id"
                    class="min-w-0 space-y-3 rounded-xl border bg-card p-4 shadow-sm"
                >
                    <!-- Question -->
                    <p
                        class="break-words text-sm font-medium leading-relaxed"
                    >
                        {{ qi + 1 }}. {{ q.question }}
                    </p>

                    <!-- Choices -->
                    <div class="space-y-1.5">
                        <div
                            v-for="c in q.choices"
                            :key="c.id"
                            class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                            :class="{
                                'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100':
                                    answersReleased &&
                                    c.is_correct,

                                'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100':
                                    answersReleased &&
                                    getStudentChoice(q.id) ===
                                        c.id &&
                                    !c.is_correct,

                                'border-primary/30 bg-primary/5 text-slate-900 dark:text-slate-100':
                                    !answersReleased &&
                                    getStudentChoice(q.id) ===
                                        c.id,
                            }"
                        >
                            <!-- Selected indicator -->
                            <span
                                class="w-4 shrink-0 text-xs"
                            >
                                {{
                                    getStudentChoice(q.id) ===
                                    c.id
                                        ? '►'
                                        : ''
                                }}
                            </span>

                            <!-- Choice text -->
                            <span
                                class="min-w-0 flex-1 break-words"
                            >
                                {{ c.choice_text }}
                            </span>

                            <!-- Correct -->
                            <span
                                v-if="
                                    answersReleased &&
                                    c.is_correct
                                "
                                class="shrink-0 text-xs font-medium text-green-600"
                            >
                                Correct
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    v-if="!answersReleased"
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    Answer key will be shown after your faculty
                    releases it.
                </p>
            </div>

            <!-- ========================================================= -->
            <!-- ESSAY / CODE SUBMISSION -->
            <!-- ========================================================= -->

            <div
                v-if="
                    submission.assignment.type !== 'mcq' &&
                    submission.content
                "
                class="min-w-0 space-y-2"
            >
                <p class="text-sm font-semibold">
                    Your Submission
                </p>

                <!-- Code -->
                <div
                    v-if="submission.assignment.type === 'code'"
                    class="min-w-0 overflow-hidden rounded-xl border bg-zinc-950 p-3 sm:p-4"
                >
                    <pre
                        class="max-w-full overflow-x-auto whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-green-400 sm:text-sm"
                    >{{ submission.content }}</pre>
                </div>

                <!-- Essay -->
                <div
                    v-else
                    class="min-w-0 rounded-xl border bg-card p-4 text-sm leading-relaxed text-muted-foreground sm:p-5"
                >
                    <p class="whitespace-pre-wrap break-words">
                        {{ submission.content }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>