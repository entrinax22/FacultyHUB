<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, Clock } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';

type Choice = { id: number; choice_text: string };
type Question = { id: number; question: string; points: number; choices: Choice[] };
type Existing = { id: number; content: string | null; answers: Record<string, number> | null; status: string };
type Assignment = {
    id: number; title: string; type: string; instructions: string;
    max_score: number; due_date: string | null; language: string | null;
    section: { id: number; name: string; subject: { code: string } };
    questions: Question[];
};

const props = defineProps<{ assignment: Assignment; existing: Existing | null }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Submit Assignment', href: '#' },
        ],
    },
});

// Essay / Code form
const essayCodeForm = useForm({
    content: props.existing?.content ?? '',
});

// MCQ form — answers keyed by question_id → choice_id
const mcqAnswers = ref<Record<number, number>>(
    props.assignment.type === 'mcq' && props.existing?.answers
        ? Object.fromEntries(Object.entries(props.existing.answers).map(([k, v]) => [Number(k), Number(v)]))
        : {},
);

const mcqForm = useForm({ answers: mcqAnswers.value });

const allAnswered = computed(() =>
    props.assignment.questions.every((q) => mcqAnswers.value[q.id] != null),
);

function submitEssayCode() {
    essayCodeForm.post(`/assignments/${props.assignment.id}/submit`);
}

function submitMcq() {
    mcqForm.answers = mcqAnswers.value;
    mcqForm.post(`/assignments/${props.assignment.id}/submit`);
}

const isPastDue = computed(() => props.assignment.due_date && new Date(props.assignment.due_date) < new Date());
</script>

<template>
    <Head :title="`Submit — ${assignment.title}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-3xl">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/my-sections/${assignment.section.id}/assignments`">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold">{{ assignment.title }}</h1>
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <span>{{ assignment.section.subject.code }} · Max: {{ assignment.max_score }} pts</span>
                    <span v-if="assignment.due_date" class="flex items-center gap-1" :class="isPastDue ? 'text-red-600' : ''">
                        <Clock class="h-3.5 w-3.5" />
                        Due {{ new Date(assignment.due_date).toLocaleString() }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="rounded-xl border bg-muted/20 p-4">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Instructions</p>
            <p class="whitespace-pre-wrap text-sm">{{ assignment.instructions }}</p>
        </div>

        <!-- Past due warning -->
        <div v-if="isPastDue && !existing" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            This assignment is past its due date and can no longer be submitted.
        </div>

        <!-- Already approved -->
        <div v-else-if="existing?.status === 'approved'" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            Your submission has been graded and approved. You can no longer edit it.
            <Button variant="outline" size="sm" class="ml-3" as-child>
                <Link :href="`/submissions/${existing.id}`">View Result</Link>
            </Button>
        </div>

        <!-- ── ESSAY FORM ── -->
        <form v-else-if="assignment.type === 'essay'" @submit.prevent="submitEssayCode" class="space-y-4">
            <div class="grid gap-1.5">
                <label class="text-sm font-medium">Your Essay</label>
                <textarea
                    v-model="essayCodeForm.content"
                    rows="16"
                    required
                    minlength="10"
                    placeholder="Write your essay here…"
                    class="flex min-h-[300px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-sans leading-relaxed"
                />
                <InputError :message="essayCodeForm.errors.content" />
            </div>
            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="essayCodeForm.processing">
                    {{ existing ? 'Update Submission' : 'Submit Essay' }}
                </Button>
                <p class="text-xs text-muted-foreground">Your essay will be graded by AI and reviewed by your faculty.</p>
            </div>
        </form>

        <!-- ── CODE FORM ── -->
        <form v-else-if="assignment.type === 'code'" @submit.prevent="submitEssayCode" class="space-y-4">
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">Your Code</label>
                    <Badge variant="outline" class="text-xs">{{ assignment.language ?? 'any' }}</Badge>
                </div>
                <textarea
                    v-model="essayCodeForm.content"
                    rows="20"
                    required
                    placeholder="# Write your code here…"
                    class="flex min-h-[350px] w-full rounded-md border border-input bg-zinc-950 px-3 py-2 text-sm shadow-sm text-green-400 placeholder:text-zinc-600 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"
                    spellcheck="false"
                />
                <InputError :message="essayCodeForm.errors.content" />
            </div>
            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="essayCodeForm.processing">
                    {{ existing ? 'Update Submission' : 'Submit Code' }}
                </Button>
                <p class="text-xs text-muted-foreground">Your code will be evaluated by AI and reviewed by your faculty.</p>
            </div>
        </form>

        <!-- ── MCQ FORM ── -->
        <div v-else-if="assignment.type === 'mcq'" class="space-y-5">
            <div
                v-for="(q, qi) in assignment.questions"
                :key="q.id"
                class="rounded-xl border p-4 space-y-3"
            >
                <p class="font-medium text-sm">{{ qi + 1 }}. {{ q.question }}
                    <span class="ml-1 text-xs text-muted-foreground">({{ q.points }} pt{{ q.points !== 1 ? 's' : '' }})</span>
                </p>
                <div class="space-y-2">
                    <label
                        v-for="c in q.choices"
                        :key="c.id"
                        class="flex items-center gap-3 rounded-lg border px-4 py-2.5 cursor-pointer transition-colors"
                        :class="mcqAnswers[q.id] === c.id ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'"
                    >
                        <input
                            type="radio"
                            :name="`q_${q.id}`"
                            :value="c.id"
                            v-model="mcqAnswers[q.id]"
                            class="accent-primary"
                        />
                        <span class="text-sm">{{ c.choice_text }}</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    :disabled="!allAnswered || mcqForm.processing"
                    @click="submitMcq"
                >
                    {{ existing ? 'Update Answers' : 'Submit Quiz' }}
                </Button>
                <p v-if="!allAnswered" class="text-xs text-muted-foreground">Answer all questions to submit.</p>
            </div>
        </div>
    </div>
</template>
