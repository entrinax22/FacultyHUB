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
    id: string;
    choice_text: string;
    is_correct: boolean | null;
    is_selected: boolean;
};

type Question = {
    id: string;
    question: string;
    points: number;
    selected_choice_id: string | null;
    choices: Choice[];
};

type CriterionFeedback = {
    criterion: string;
    score: number;
    max: number;
    feedback: string;
};

type AiFeedbackJson = {
    overall_comment?: string | null;
    criterion_feedback?: CriterionFeedback[];
    correctness?: number;
    logic_quality?: number;
    code_quality?: number;
};

type AiFeedback = {
    score: number;
    feedback: string | null;
    feedback_json: AiFeedbackJson;
};

type Grade = {
    id?: string;
    raw_score: number;
    max_score: number;
    percentage: number;
    remarks: string | null;
    is_released: boolean;
};

type Assignment = {
    id: string;
    title: string;
    type: string;
    max_score: number;
    section: {
        id: string;
        name: string;
        subject: {
            id?: string;
            code: string;
            name?: string;
        };
    };
};

type Submission = {
    id: string;
    status: string;
    content: string | null;
    answers: Record<string, string | null> | null;
    submitted_at: string;
    questions: Question[];
    ai_feedback: AiFeedback | null;
    grade: Grade | null;
    assignment: Assignment;
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

/**
 * Whether this is an MCQ submission.
 */
const isMcq = props.submission.assignment.type === 'mcq';

/**
 * Whether this is an AI-graded submission.
 */
const isAiGraded =
    props.submission.assignment.type === 'essay' ||
    props.submission.assignment.type === 'code';

/**
 * AI score can exist even when the Grade record has not
 * been created yet.
 */
const aiScore = props.submission.ai_feedback?.score ?? null;

/**
 * Displayed percentage.
 *
 * MCQ:
 *   Uses the released Grade record.
 *
 * Essay / Code:
 *   Uses the Grade record when available.
 *   Otherwise uses the AI feedback score.
 */
function getDisplayedPercentage(): number | null {
    if (props.submission.grade?.is_released) {
        return props.submission.grade.percentage;
    }

    if (isAiGraded && aiScore !== null) {
        return aiScore;
    }

    return null;
}

/**
 * Displayed raw score.
 */
function getDisplayedRawScore(): number | null {
    if (props.submission.grade?.is_released) {
        return props.submission.grade.raw_score;
    }

    if (isAiGraded && aiScore !== null) {
        return aiScore;
    }

    return null;
}

/**
 * Displayed maximum score.
 */
function getDisplayedMaxScore(): number {
    if (props.submission.grade?.is_released) {
        return props.submission.grade.max_score;
    }

    return props.submission.assignment.max_score;
}

/**
 * Check whether a grade can currently be displayed.
 */
function hasDisplayedGrade(): boolean {
    return getDisplayedRawScore() !== null;
}

/**
 * Format percentage safely.
 */
function formatPercentage(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return Number.isInteger(value)
        ? `${value}%`
        : `${value.toFixed(1)}%`;
}

/**
 * Get the label for the displayed score.
 */
function getScoreLabel(): string {
    if (
        !props.submission.grade?.is_released &&
        isAiGraded &&
        aiScore !== null
    ) {
        return 'AI Score';
    }

    return 'Your Grade';
}

/**
 * Determine whether the current choice is selected.
 *
 * Backend already provides is_selected, so this is mainly
 * kept as a helper for template readability.
 */
function isSelected(choice: Choice): boolean {
    return choice.is_selected;
}

/**
 * Determine whether the choice should display as incorrect.
 *
 * Only possible when answers have been released.
 */
function isIncorrectSelected(choice: Choice): boolean {
    return (
        props.answersReleased &&
        choice.is_selected &&
        choice.is_correct === false
    );
}

/**
 * Determine whether the choice should display as correct.
 */
function isCorrectChoice(choice: Choice): boolean {
    return (
        props.answersReleased &&
        choice.is_correct === true
    );
}
</script>

<template>
    <Head
        :title="`Result — ${submission.assignment.title}`"
    />

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
                        :href="
                            `/my-sections/${submission.assignment.section.id}/assignments`
                        "
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
                        <span
                            class="font-medium text-foreground"
                        >
                            {{
                                submission.assignment.section
                                    .subject.code
                            }}
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

            <div
                class="rounded-xl border bg-card p-4 shadow-sm sm:p-5"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <!-- Grade -->

                    <div class="min-w-0">

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            {{ getScoreLabel() }}
                        </p>

                        <div
                            v-if="hasDisplayedGrade()"
                            class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1"
                        >
                            <span
                                class="text-3xl font-bold sm:text-4xl"
                            >
                                {{ getDisplayedRawScore() }}
                            </span>

                            <span
                                class="text-lg text-muted-foreground sm:text-xl"
                            >
                                /
                                {{ getDisplayedMaxScore() }}
                            </span>

                            <span
                                class="text-xs text-muted-foreground sm:text-sm"
                            >
                                {{
                                    formatPercentage(
                                        getDisplayedPercentage(),
                                    )
                                }}
                            </span>
                        </div>

                        <p
                            v-else
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{
                                submission.status === 'grading'
                                    ? 'Your submission is being graded.'
                                    : 'Grade not yet released.'
                            }}
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
                            v-if="
                                submission.status === 'approved'
                            "
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

                <!-- Grade Remarks -->

                <p
                    v-if="
                        submission.grade?.remarks &&
                        submission.grade.is_released
                    "
                    class="mt-4 rounded-lg bg-muted/30 p-3 text-sm leading-relaxed text-muted-foreground"
                >
                    {{ submission.grade.remarks }}
                </p>

                <!-- AI Score Notice -->

                <p
                    v-if="
                        !submission.grade?.is_released &&
                        isAiGraded &&
                        aiScore !== null
                    "
                    class="mt-4 rounded-lg bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                >
                    This score is based on the AI grading result.
                    Your final grade will be shown when it is
                    released.
                </p>
            </div>

            <!-- ========================================================= -->
            <!-- AI FEEDBACK -->
            <!-- ========================================================= -->

            <div
                v-if="submission.ai_feedback"
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

                <!-- AI Score -->

                <div
                    class="rounded-lg bg-muted/30 p-3"
                >
                    <div
                        class="flex items-center justify-between gap-3"
                    >
                        <span
                            class="text-sm font-medium"
                        >
                            AI Score
                        </span>

                        <span
                            class="text-lg font-bold"
                        >
                            {{ submission.ai_feedback.score }}
                            /
                            {{ submission.assignment.max_score }}
                        </span>
                    </div>
                </div>

                <!-- Feedback Text -->

                <p
                    v-if="
                        submission.ai_feedback.feedback
                    "
                    class="break-words text-sm leading-relaxed text-muted-foreground"
                >
                    {{ submission.ai_feedback.feedback }}
                </p>

                <!-- Overall Comment -->

                <p
                    v-if="
                        submission.ai_feedback.feedback_json
                            ?.overall_comment
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
                            ?.criterion_feedback?.length
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
                            <span
                                class="break-words font-medium"
                            >
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

                <!-- Code Metrics -->

                <div
                    v-if="
                        submission.assignment.type ===
                            'code'
                    "
                    class="grid grid-cols-1 gap-2 sm:grid-cols-3"
                >
                    <div
                        class="rounded-lg border p-3 text-center"
                    >
                        <p class="text-lg font-semibold">
                            {{
                                submission.ai_feedback
                                    .feedback_json
                                    ?.correctness ?? '—'
                            }}
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
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
                                    ?.logic_quality ?? '—'
                            }}
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
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
                                    ?.code_quality ?? '—'
                            }}
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Code Quality
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MCQ ANSWERS -->
            <!-- ========================================================= -->

            <div
                v-if="
                    isMcq &&
                    submission.questions?.length
                "
                class="min-w-0 space-y-3"
            >
                <div>
                    <p class="text-sm font-semibold">
                        Your Answers
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{
                            answersReleased
                                ? 'The correct answers are shown.'
                                : 'Your selected answers are shown. The answer key has not been released yet.'
                        }}
                    </p>
                </div>

                <div
                    v-for="(q, qi) in submission.questions"
                    :key="q.id"
                    class="min-w-0 space-y-3 rounded-xl border bg-card p-4 shadow-sm"
                >

                    <!-- Question -->

                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <p
                            class="min-w-0 break-words text-sm font-medium leading-relaxed"
                        >
                            {{ qi + 1 }}.
                            {{ q.question }}
                        </p>

                        <span
                            class="shrink-0 text-xs text-muted-foreground"
                        >
                            {{ q.points }} pts
                        </span>
                    </div>

                    <!-- Choices -->

                    <div class="space-y-1.5">
                        <div
                            v-for="c in q.choices"
                            :key="c.id"
                            class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2 text-sm"
                            :class="{
                                'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100':
                                    isCorrectChoice(c),

                                'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100':
                                    isIncorrectSelected(c),

                                'border-primary/30 bg-primary/5 text-slate-900 dark:text-slate-100':
                                    !answersReleased &&
                                    isSelected(c),

                                'border-muted':
                                    answersReleased &&
                                    !isCorrectChoice(c) &&
                                    !isIncorrectSelected(c),
                            }"
                        >

                            <!-- Selected Indicator -->

                            <span
                                class="w-4 shrink-0 text-xs font-semibold"
                            >
                                {{
                                    isSelected(c)
                                        ? '►'
                                        : ''
                                }}
                            </span>

                            <!-- Choice Text -->

                            <span
                                class="min-w-0 flex-1 break-words"
                            >
                                {{ c.choice_text }}
                            </span>

                            <!-- Selected Label -->

                            <span
                                v-if="isSelected(c)"
                                class="shrink-0 text-xs font-medium"
                            >
                                Your answer
                            </span>

                            <!-- Correct Label -->

                            <span
                                v-if="isCorrectChoice(c)"
                                class="shrink-0 text-xs font-medium text-green-600 dark:text-green-400"
                            >
                                Correct
                            </span>

                            <!-- Incorrect Label -->

                            <span
                                v-else-if="isIncorrectSelected(c)"
                                class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400"
                            >
                                Incorrect
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MCQ WITH NO QUESTIONS -->

            <div
                v-else-if="isMcq"
                class="rounded-xl border bg-card p-4 text-sm text-muted-foreground shadow-sm"
            >
                No question details are available for this
                submission.
            </div>

            <!-- ========================================================= -->
            <!-- ESSAY / CODE SUBMISSION -->
            <!-- ========================================================= -->

            <div
                v-if="
                    !isMcq &&
                    submission.content
                "
                class="min-w-0 space-y-2"
            >
                <p class="text-sm font-semibold">
                    Your Submission
                </p>

                <!-- Code -->

                <div
                    v-if="
                        submission.assignment.type ===
                        'code'
                    "
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
                    <p
                        class="whitespace-pre-wrap break-words"
                    >
                        {{ submission.content }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>