<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

import { ArrowLeft, Bot, CheckCircle2, Loader2, User } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

import { onMounted, ref } from 'vue';

import { showApiError, showApiToast } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Choice = {
    id: string;
    choice_text: string;
    is_correct: boolean;
    is_selected: boolean;
};

type Question = {
    id: string;
    question: string | null;
    points: string | number;
    selected_choice_id: string | null;
    choices: Choice[];
};

type InlineComment = {
    line: number;
    comment: string;
};

type AiFeedbackContent = {
    score: number;
    correctness: number;
    code_quality: number;
    logic_quality: number;
    inline_comments: InlineComment[];
    overall_comment: string;
};

type AiFeedback = {
    id: string;
    score: number;
    feedback: AiFeedbackContent | null;
};

type Grade = {
    id: string;
    raw_score: number;
    max_score: number;
    percentage: number | null;
    status: string | null;
    remarks: string | null;
    is_released: boolean;
};

type Student = {
    id: string;
    first_name: string;
    last_name: string;
    student_no: string;
};

type Subject = {
    id: string;
    code: string;
    name: string;
};

type Section = {
    id: string;
    name: string;
    subject: Subject | null;
};

type Assignment = {
    id: string;
    title: string;
    type: string;
    max_score: number;
    section: Section | null;
};

type Submission = {
    id: string;
    status: string;
    content: string | null;
    submitted_at: string;

    student: Student | null;

    assignment: Assignment | null;

    /*
     * Questions are returned directly under submission.
     */
    questions: Question[];

    grade: Grade | null;

    ai_feedback: AiFeedback | null;
};

type GradingResponse = {
    success: boolean;
    message: string;
    data: {
        submission: Submission;
    };
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    submissionId: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Assignments',
                href: '#',
            },
            {
                title: 'Grade Submission',
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

const submission = ref<Submission | null>(null);

const loading = ref(true);

const rawScore = ref<number>(0);

const remarks = ref('');

const errors = ref<Record<string, string>>({});

const processing = ref(false);

/*
|--------------------------------------------------------------------------
| Load Submission
|--------------------------------------------------------------------------
*/

async function loadSubmission() {
    loading.value = true;

    try {
        const response = await axios.get<GradingResponse>(
            `/submissions/${props.submissionId}/grade/data`,
        );

        submission.value = response.data.data.submission;

        rawScore.value =
            submission.value.grade?.raw_score ??
            submission.value.ai_feedback?.score ??
            0;

        remarks.value = submission.value.grade?.remarks ?? '';
    } catch (error) {
        submission.value = null;

        console.error('Failed to load grading data:', error);

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Approve / Save Grade
|--------------------------------------------------------------------------
*/

async function approve() {
    if (!submission.value) {
        return;
    }

    errors.value = {};
    processing.value = true;

    try {
        const response = await axios.post(
            `/submissions/${submission.value.id}/approve`,
            {
                raw_score: rawScore.value,
                remarks: remarks.value,
            },
        );

        showApiToast(response);

        await loadSubmission();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            errors.value = error.response.data?.errors ?? {};
        } else {
            console.error('Submission approval failed:', error);

            showApiError(error);
        }
    } finally {
        processing.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| MCQ Helpers
|--------------------------------------------------------------------------
*/

/**
 * Get the student's selected choice.
 *
 * The backend now returns `is_selected` directly
 * on each choice, so there is no need to search
 * through submission.answers.
 */
function getStudentAnswer(question: Question): Choice | null {
    return question.choices.find((choice) => choice.is_selected) ?? null;
}

/**
 * Determine whether the question has a recorded
 * student answer.
 */
function hasStudentAnswer(question: Question): boolean {
    return getStudentAnswer(question) !== null;
}

/**
 * Determine whether this choice was selected
 * by the student.
 */
function isStudentChoice(choice: Choice): boolean {
    return choice.is_selected;
}

/**
 * Determine whether this choice was selected
 * by the student and is incorrect.
 */
function isIncorrectStudentChoice(choice: Choice): boolean {
    return choice.is_selected && !choice.is_correct;
}

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSubmission();
});
</script>

<template>
    <Head
        :title="
            submission
                ? `Grade — ${submission.student?.last_name}, ${submission.student?.first_name}`
                : 'Grade Submission'
        "
    />

    <!-- ================================================================ -->
    <!-- Loading -->
    <!-- ================================================================ -->

    <div v-if="loading" class="flex min-h-[400px] items-center justify-center">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 class="h-4 w-4 animate-spin" />

            Loading submission...
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- Failed to Load -->
    <!-- ================================================================ -->

    <div
        v-else-if="!submission"
        class="flex min-h-[400px] items-center justify-center"
    >
        <div class="text-center">
            <p class="font-medium">Unable to load submission.</p>

            <p class="mt-1 text-sm text-muted-foreground">
                The submission could not be found.
            </p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- Grading Page -->
    <!-- ================================================================ -->

    <div v-else class="flex h-full max-w-4xl flex-1 flex-col gap-6 p-4">
        <!-- ============================================================ -->
        <!-- Header -->
        <!-- ============================================================ -->

        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="mt-0.5 -ml-2">
                <Link
                    :href="
                        submission.assignment
                            ? `/assignments/${submission.assignment.id}`
                            : '#'
                    "
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>

            <div>
                <h1 class="text-xl font-semibold">
                    {{ submission.student?.last_name }},
                    {{ submission.student?.first_name }}

                    <span class="ml-2 font-mono text-sm text-muted-foreground">
                        {{ submission.student?.student_no }}
                    </span>
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ submission.assignment?.title }}

                    <span v-if="submission.assignment?.section?.subject">
                        ·
                        {{ submission.assignment.section.subject.code }}
                    </span>

                    · Submitted
                    {{ new Date(submission.submitted_at).toLocaleString() }}
                </p>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- Main Content -->
        <!-- ============================================================ -->

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- ======================================================== -->
            <!-- Left: Student Submission -->
            <!-- ======================================================== -->

            <div class="space-y-4">
                <h2 class="font-semibold">Student Submission</h2>

                <!-- ==================================================== -->
                <!-- Essay -->
                <!-- ==================================================== -->

                <div
                    v-if="submission.assignment?.type === 'essay'"
                    class="min-h-[200px] rounded-xl border p-4 text-sm leading-relaxed whitespace-pre-wrap"
                >
                    {{ submission.content || '(No content)' }}
                </div>

                <!-- ==================================================== -->
                <!-- Code -->
                <!-- ==================================================== -->

                <div
                    v-else-if="submission.assignment?.type === 'code'"
                    class="rounded-xl border bg-zinc-950 p-4"
                >
                    <pre
                        class="overflow-x-auto font-mono text-sm whitespace-pre-wrap text-green-400"
                        >{{ submission.content || '(No code)' }}</pre
                    >
                </div>

                <!-- ==================================================== -->
                <!-- MCQ -->
                <!-- ==================================================== -->

                <div
                    v-else-if="submission.assignment?.type === 'mcq'"
                    class="space-y-3"
                >
                    <!-- No Questions -->

                    <div
                        v-if="submission.questions.length === 0"
                        class="rounded-xl border p-6 text-center text-sm text-muted-foreground"
                    >
                        No questions were found for this submission.
                    </div>

                    <!-- Questions -->

                    <div
                        v-for="(
                            question, questionIndex
                        ) in submission.questions"
                        :key="question.id"
                        class="space-y-3 rounded-xl border p-4"
                    >
                        <!-- Question Header -->

                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium">
                                {{ questionIndex + 1 }}.

                                {{ question.question || '(No question text)' }}
                            </p>

                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ question.points }}
                                pt{{ Number(question.points) === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <!-- Student Answer Status -->

                        <div
                            v-if="!hasStudentAnswer(question)"
                            class="rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground"
                        >
                            No recorded student answer.
                        </div>

                        <!-- Choices -->

                        <div v-if="question.choices.length" class="space-y-1">
                            <div
                                v-for="choice in question.choices"
                                :key="choice.id"
                                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                :class="{
                                    'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100':
                                        choice.is_correct,

                                    'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100':
                                        isIncorrectStudentChoice(choice),

                                    'border-slate-200 text-slate-900 dark:border-slate-700 dark:text-slate-100':
                                        !choice.is_correct &&
                                        !choice.is_selected,
                                }"
                            >
                                <!-- Student Selection Indicator -->

                                <span class="w-4 shrink-0 font-mono text-xs">
                                    {{ isStudentChoice(choice) ? '►' : '' }}
                                </span>

                                <!-- Choice Text -->

                                <span class="flex-1">
                                    {{ choice.choice_text }}
                                </span>

                                <!-- Correct Label -->

                                <span
                                    v-if="choice.is_correct"
                                    class="text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Correct
                                </span>

                                <!-- Student Answer Label -->

                                <span
                                    v-if="choice.is_selected"
                                    class="text-xs font-medium"
                                    :class="
                                        choice.is_correct
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    Student Answer
                                </span>
                            </div>
                        </div>

                        <!-- No Choices -->

                        <div
                            v-else
                            class="rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground"
                        >
                            No choices found for this question.
                        </div>
                    </div>
                </div>

                <!-- ==================================================== -->
                <!-- Unknown Assignment Type -->
                <!-- ==================================================== -->

                <div
                    v-else
                    class="rounded-xl border p-6 text-center text-sm text-muted-foreground"
                >
                    Unsupported assignment type.
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- Right: AI + Faculty -->
            <!-- ======================================================== -->

            <div class="space-y-4">
                <!-- ==================================================== -->
                <!-- AI Feedback -->
                <!-- ==================================================== -->

                <div
                    v-if="submission.ai_feedback"
                    class="space-y-4 rounded-xl border p-4"
                >
                    <!-- Header -->

                    <div class="flex items-center gap-2">
                        <Bot class="h-4 w-4 text-blue-500" />

                        <span class="text-sm font-semibold">
                            AI Assessment
                        </span>

                        <Badge variant="outline" class="ml-auto text-xs">
                            Score:
                            {{ submission.ai_feedback.score }}
                            /
                            {{ submission.assignment?.max_score }}
                        </Badge>
                    </div>

                    <!-- AI Scores -->

                    <div
                        v-if="submission.ai_feedback.feedback"
                        class="grid grid-cols-2 gap-2"
                    >
                        <!-- Correctness -->

                        <div class="rounded-lg border bg-muted/30 p-3">
                            <p class="text-xs text-muted-foreground">
                                Correctness
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    submission.ai_feedback.feedback.correctness
                                }}%
                            </p>
                        </div>

                        <!-- Code Quality -->

                        <div class="rounded-lg border bg-muted/30 p-3">
                            <p class="text-xs text-muted-foreground">
                                Code Quality
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    submission.ai_feedback.feedback
                                        .code_quality
                                }}%
                            </p>
                        </div>

                        <!-- Logic Quality -->

                        <div class="rounded-lg border bg-muted/30 p-3">
                            <p class="text-xs text-muted-foreground">
                                Logic Quality
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{
                                    submission.ai_feedback.feedback
                                        .logic_quality
                                }}%
                            </p>
                        </div>

                        <!-- Overall Score -->

                        <div class="rounded-lg border bg-muted/30 p-3">
                            <p class="text-xs text-muted-foreground">
                                Overall Score
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{ submission.ai_feedback.feedback.score }}%
                            </p>
                        </div>
                    </div>

                    <!-- Overall Comment -->

                    <div
                        v-if="submission.ai_feedback.feedback?.overall_comment"
                        class="rounded-lg bg-muted/50 p-3"
                    >
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground"
                        >
                            Overall Feedback
                        </p>

                        <p class="text-sm leading-relaxed">
                            {{
                                submission.ai_feedback.feedback.overall_comment
                            }}
                        </p>
                    </div>

                    <!-- Inline Comments -->

                    <div
                        v-if="
                            submission.ai_feedback.feedback?.inline_comments
                                ?.length
                        "
                        class="space-y-2"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Inline Comments
                        </p>

                        <div
                            v-for="(comment, index) in submission.ai_feedback
                                .feedback.inline_comments"
                            :key="index"
                            class="rounded-lg border bg-muted/30 px-3 py-2 text-sm leading-relaxed"
                        >
                            <div class="flex items-start gap-3">
                                <Badge variant="outline" class="shrink-0">
                                    Line {{ comment.line }}
                                </Badge>

                                <p class="text-sm leading-relaxed">
                                    {{ comment.comment }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- No Inline Comments -->

                    <div
                        v-else-if="
                            submission.ai_feedback.feedback &&
                            submission.ai_feedback.feedback.inline_comments
                                ?.length === 0
                        "
                        class="text-xs text-muted-foreground"
                    >
                        No inline comments were provided.
                    </div>

                    <!-- No Feedback Content -->

                    <p
                        v-if="!submission.ai_feedback.feedback"
                        class="text-sm text-muted-foreground"
                    >
                        No AI feedback was provided.
                    </p>
                </div>

                <!-- ==================================================== -->
                <!-- AI Grading in Progress -->
                <!-- ==================================================== -->

                <div
                    v-else-if="submission.status === 'grading'"
                    class="rounded-xl border p-6 text-center text-sm text-muted-foreground"
                >
                    AI grading in progress…
                </div>

                <!-- ==================================================== -->
                <!-- Faculty Approval -->
                <!-- ==================================================== -->

                <div class="space-y-4 rounded-xl border p-4">
                    <div class="flex items-center gap-2">
                        <User class="h-4 w-4" />

                        <span class="text-sm font-semibold">
                            Faculty Decision
                        </span>

                        <Badge
                            v-if="submission.status === 'approved'"
                            variant="default"
                            class="ml-auto"
                        >
                            Approved
                        </Badge>
                    </div>

                    <!-- Existing Grade Information -->

                    <div
                        v-if="submission.grade"
                        class="rounded-lg bg-muted/50 p-3"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                Current Grade
                            </span>

                            <span class="font-semibold">
                                {{ submission.grade.raw_score }}
                                /
                                {{ submission.grade.max_score }}
                            </span>
                        </div>

                        <div
                            v-if="submission.grade.percentage !== null"
                            class="mt-1 flex items-center justify-between text-xs"
                        >
                            <span class="text-muted-foreground">
                                Percentage
                            </span>

                            <span> {{ submission.grade.percentage }}% </span>
                        </div>
                    </div>

                    <!-- Score -->

                    <div class="grid gap-1.5">
                        <Label for="raw_score">
                            Final Score (max
                            {{ submission.assignment?.max_score }})
                        </Label>

                        <Input
                            id="raw_score"
                            v-model.number="rawScore"
                            type="number"
                            :min="0"
                            :max="submission.assignment?.max_score"
                            step="0.5"
                            required
                        />

                        <InputError :message="errors.raw_score" />
                    </div>

                    <!-- Remarks -->

                    <div class="grid gap-1.5">
                        <Label for="remarks">
                            Remarks

                            <span class="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>

                        <textarea
                            id="remarks"
                            v-model="remarks"
                            rows="3"
                            class="flex min-h-[70px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Additional comments for the student..."
                        ></textarea>

                        <InputError :message="errors.remarks" />
                    </div>

                    <!-- Approve / Update -->

                    <Button
                        type="button"
                        class="w-full"
                        :disabled="processing"
                        @click="approve"
                    >
                        <CheckCircle2
                            class="mr-2 h-4 w-4"
                            :class="{
                                'animate-pulse': processing,
                            }"
                        />

                        {{
                            processing
                                ? 'Saving...'
                                : submission.status === 'approved'
                                  ? 'Update Grade'
                                  : 'Approve & Save Grade'
                        }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
