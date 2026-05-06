<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Bot, CheckCircle2, User } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Choice = { id: number; choice_text: string; is_correct: boolean };
type Question = { id: number; question: string; points: number; choices: Choice[] };
type AiFeedback = {
    score: number;
    feedback_json: {
        overall_comment?: string;
        criterion_feedback?: Array<{ criterion: string; score: number; max: number; feedback: string }>;
        correctness?: number;
        logic_quality?: number;
        code_quality?: number;
        inline_comments?: Array<{ line: number; comment: string }>;
    };
    model_used: string | null;
};
type Grade = { raw_score: number; max_score: number; remarks: string | null; is_released: boolean };
type Submission = {
    id: number;
    status: string;
    content: string | null;
    answers: Record<string, number> | null;
    submitted_at: string;
    student: { id: number; first_name: string; last_name: string; student_no: string };
    ai_feedback: AiFeedback | null;
    grade: Grade | null;
    assignment: {
        id: number; title: string; type: string; max_score: number; instructions: string;
        section: { id: number; name: string; subject: { code: string; name: string } };
        questions: Question[];
    };
};

const props = defineProps<{ submission: Submission }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Assignments', href: '#' },
            { title: 'Grade Submission', href: '#' },
        ],
    },
});

const form = useForm({
    raw_score: props.submission.grade?.raw_score ?? props.submission.ai_feedback?.score ?? 0,
    remarks: props.submission.grade?.remarks ?? '',
});

function approve() {
    form.post(`/submissions/${props.submission.id}/approve`);
}

// MCQ helpers
function getStudentAnswer(questionId: number): number | null {
    return props.submission.answers?.[questionId] ?? null;
}

function isCorrect(question: Question, studentChoiceId: number | null): boolean {
    if (!studentChoiceId) return false;
    return question.choices.find((c) => c.id === studentChoiceId)?.is_correct ?? false;
}
</script>

<template>
    <Head :title="`Grade — ${submission.student.last_name}, ${submission.student.first_name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-4xl">
        <!-- Header -->
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/assignments/${submission.assignment.id}`"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold">
                    {{ submission.student.last_name }}, {{ submission.student.first_name }}
                    <span class="ml-2 font-mono text-sm text-muted-foreground">{{ submission.student.student_no }}</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ submission.assignment.title }} · {{ submission.assignment.section.subject.code }}
                    · Submitted {{ new Date(submission.submitted_at).toLocaleString() }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left: submission content -->
            <div class="space-y-4">
                <h2 class="font-semibold">Student Submission</h2>

                <!-- Essay -->
                <div v-if="submission.assignment.type === 'essay'" class="rounded-xl border p-4 text-sm leading-relaxed whitespace-pre-wrap min-h-[200px]">
                    {{ submission.content || '(No content)' }}
                </div>

                <!-- Code -->
                <div v-else-if="submission.assignment.type === 'code'" class="rounded-xl border bg-zinc-950 p-4">
                    <pre class="text-sm text-green-400 overflow-x-auto whitespace-pre-wrap font-mono">{{ submission.content || '(No code)' }}</pre>
                </div>

                <!-- MCQ answers -->
                <div v-else-if="submission.assignment.type === 'mcq'" class="space-y-3">
                    <div
                        v-for="(q, qi) in submission.assignment.questions"
                        :key="q.id"
                        class="rounded-xl border p-4 space-y-2"
                    >
                        <p class="font-medium text-sm">{{ qi + 1 }}. {{ q.question }}</p>
                        <div class="space-y-1">
                            <div
                                v-for="c in q.choices"
                                :key="c.id"
                                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm"
                                :class="{
                                    'bg-green-50 border border-green-200': c.is_correct,
                                    'bg-red-50 border border-red-200': getStudentAnswer(q.id) === c.id && !c.is_correct,
                                    'border': getStudentAnswer(q.id) !== c.id && !c.is_correct,
                                }"
                            >
                                <span class="text-xs font-mono w-4">{{ getStudentAnswer(q.id) === c.id ? '►' : ' ' }}</span>
                                {{ c.choice_text }}
                                <span v-if="c.is_correct" class="ml-auto text-xs text-green-600 font-medium">Correct</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: AI feedback + approval form -->
            <div class="space-y-4">
                <!-- AI Feedback panel -->
                <div v-if="submission.ai_feedback" class="rounded-xl border p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <Bot class="h-4 w-4 text-blue-500" />
                        <span class="font-semibold text-sm">AI Assessment</span>
                        <Badge variant="outline" class="ml-auto text-xs">
                            Score: {{ submission.ai_feedback.score }} / {{ submission.assignment.max_score }}
                        </Badge>
                    </div>

                    <p v-if="submission.ai_feedback.feedback_json.overall_comment" class="text-sm text-muted-foreground">
                        {{ submission.ai_feedback.feedback_json.overall_comment }}
                    </p>

                    <!-- Essay rubric breakdown -->
                    <div v-if="submission.ai_feedback.feedback_json.criterion_feedback?.length" class="space-y-2">
                        <div
                            v-for="cf in submission.ai_feedback.feedback_json.criterion_feedback"
                            :key="cf.criterion"
                            class="rounded-lg bg-muted/30 p-3 text-sm"
                        >
                            <div class="flex justify-between font-medium">
                                <span>{{ cf.criterion }}</span>
                                <span>{{ cf.score }} / {{ cf.max }}</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">{{ cf.feedback }}</p>
                        </div>
                    </div>

                    <!-- Code metrics -->
                    <div v-if="submission.assignment.type === 'code'" class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg border p-2">
                            <p class="font-semibold">{{ submission.ai_feedback.feedback_json.correctness ?? '—' }}</p>
                            <p class="text-muted-foreground">Correctness</p>
                        </div>
                        <div class="rounded-lg border p-2">
                            <p class="font-semibold">{{ submission.ai_feedback.feedback_json.logic_quality ?? '—' }}</p>
                            <p class="text-muted-foreground">Logic</p>
                        </div>
                        <div class="rounded-lg border p-2">
                            <p class="font-semibold">{{ submission.ai_feedback.feedback_json.code_quality ?? '—' }}</p>
                            <p class="text-muted-foreground">Code Quality</p>
                        </div>
                    </div>

                    <!-- Inline code comments -->
                    <div v-if="submission.ai_feedback.feedback_json.inline_comments?.length" class="space-y-1">
                        <p class="text-xs font-medium text-muted-foreground">Inline Comments</p>
                        <div
                            v-for="ic in submission.ai_feedback.feedback_json.inline_comments"
                            :key="ic.line"
                            class="rounded-md border px-3 py-1.5 text-xs"
                        >
                            <span class="font-mono text-muted-foreground">Line {{ ic.line }}:</span> {{ ic.comment }}
                        </div>
                    </div>
                </div>

                <div v-else-if="submission.status === 'grading'" class="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                    AI grading in progress…
                </div>

                <!-- Faculty approval form -->
                <div class="rounded-xl border p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <User class="h-4 w-4" />
                        <span class="font-semibold text-sm">Faculty Decision</span>
                        <Badge v-if="submission.status === 'approved'" variant="default" class="ml-auto">Approved</Badge>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="raw_score">Final Score (max {{ submission.assignment.max_score }})</Label>
                        <Input
                            id="raw_score"
                            type="number"
                            v-model="form.raw_score"
                            :min="0"
                            :max="submission.assignment.max_score"
                            step="0.5"
                            required
                        />
                        <InputError :message="form.errors.raw_score" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="remarks">Remarks <span class="text-muted-foreground">(optional)</span></Label>
                        <textarea
                            id="remarks"
                            v-model="form.remarks"
                            rows="3"
                            class="flex min-h-[70px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Additional comments for the student..."
                        />
                    </div>

                    <Button class="w-full" :disabled="form.processing" @click="approve">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        {{ submission.status === 'approved' ? 'Update Grade' : 'Approve & Save Grade' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
