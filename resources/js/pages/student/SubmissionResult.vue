<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bot, CheckCircle2, Clock, Loader2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
    };
};
type Grade = { raw_score: number; max_score: number; remarks: string | null; is_released: boolean };
type Submission = {
    id: number;
    status: string;
    content: string | null;
    answers: Record<string, number> | null;
    submitted_at: string;
    ai_feedback: AiFeedback | null;
    grade: Grade | null;
    assignment: {
        id: number; title: string; type: string; max_score: number;
        section: { id: number; name: string; subject: { code: string } };
        questions: Question[];
    };
};

const props = defineProps<{ submission: Submission; answersReleased: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Submission Result', href: '#' },
        ],
    },
});

function getStudentChoice(questionId: number): number | null {
    return props.submission.answers?.[questionId] ?? null;
}
</script>

<template>
    <Head :title="`Result — ${submission.assignment.title}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-3xl">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/my-sections/${submission.assignment.section.id}/assignments`">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold">{{ submission.assignment.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ submission.assignment.section.subject.code }} · {{ submission.assignment.section.name }}
                    · Submitted {{ new Date(submission.submitted_at).toLocaleString() }}
                </p>
            </div>
        </div>

        <!-- Grade card -->
        <div class="rounded-xl border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-muted-foreground uppercase tracking-wide">Your Grade</p>
                    <div v-if="submission.grade?.is_released" class="mt-1">
                        <span class="text-4xl font-bold">{{ submission.grade.raw_score }}</span>
                        <span class="text-xl text-muted-foreground"> / {{ submission.grade.max_score }}</span>
                        <span class="ml-3 text-sm text-muted-foreground">
                            ({{ ((submission.grade.raw_score / submission.grade.max_score) * 100).toFixed(1) }}%)
                        </span>
                    </div>
                    <p v-else class="mt-1 text-sm text-muted-foreground">Grade not yet released.</p>
                </div>
                <Badge
                    :variant="submission.status === 'approved' ? 'default' : 'secondary'"
                    class="capitalize text-sm px-3 py-1"
                >
                    <CheckCircle2 v-if="submission.status === 'approved'" class="mr-1.5 h-4 w-4" />
                    <Loader2 v-else-if="submission.status === 'grading'" class="mr-1.5 h-4 w-4 animate-spin" />
                    {{ submission.status === 'grading' ? 'Being graded…' : submission.status }}
                </Badge>
            </div>

            <p v-if="submission.grade?.remarks && submission.grade.is_released" class="mt-3 rounded-lg bg-muted/30 p-3 text-sm text-muted-foreground">
                {{ submission.grade.remarks }}
            </p>
        </div>

        <!-- AI feedback (essay/code) -->
        <div
            v-if="submission.ai_feedback && submission.grade?.is_released && submission.assignment.type !== 'mcq'"
            class="rounded-xl border p-4 space-y-3"
        >
            <div class="flex items-center gap-2">
                <Bot class="h-4 w-4 text-blue-500" />
                <span class="font-semibold text-sm">AI Feedback</span>
            </div>

            <p v-if="submission.ai_feedback.feedback_json.overall_comment" class="text-sm text-muted-foreground">
                {{ submission.ai_feedback.feedback_json.overall_comment }}
            </p>

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
        </div>

        <!-- MCQ: answer key revealed after release -->
        <div v-if="submission.assignment.type === 'mcq'" class="space-y-3">
            <p class="font-semibold">Your Answers</p>
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
                        class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm border"
                        :class="{
                            'bg-green-50 border-green-200': answersReleased && c.is_correct,
                            'bg-red-50 border-red-200': answersReleased && getStudentChoice(q.id) === c.id && !c.is_correct,
                            'bg-primary/5 border-primary/30': !answersReleased && getStudentChoice(q.id) === c.id,
                        }"
                    >
                        <span class="text-xs w-4">{{ getStudentChoice(q.id) === c.id ? '►' : ' ' }}</span>
                        {{ c.choice_text }}
                        <span v-if="answersReleased && c.is_correct" class="ml-auto text-xs text-green-600 font-medium">Correct</span>
                    </div>
                </div>
            </div>
            <p v-if="!answersReleased" class="text-xs text-muted-foreground">Answer key will be shown after your faculty releases it.</p>
        </div>

        <!-- Essay / Code preview -->
        <div v-if="submission.assignment.type !== 'mcq' && submission.content" class="space-y-2">
            <p class="font-semibold text-sm">Your Submission</p>
            <div v-if="submission.assignment.type === 'code'" class="rounded-xl border bg-zinc-950 p-4">
                <pre class="text-sm text-green-400 overflow-x-auto whitespace-pre-wrap font-mono">{{ submission.content }}</pre>
            </div>
            <div v-else class="rounded-xl border p-4 text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                {{ submission.content }}
            </div>
        </div>
    </div>
</template>
