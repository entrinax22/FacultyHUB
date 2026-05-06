<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Plus, Trash2, CheckCircle, ClipboardList } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Choice = { id?: number; choice_text: string; is_correct: boolean };
type Question = { id?: number; question: string; points: number; choices: Choice[] };

type Module = { id: number; title: string };
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};
type Assignment = {
    id: number; title: string; instructions: string; type: string;
    due_date: string | null; max_score: number; passing_score: number | null;
    is_published: boolean; rubric: string | null; language: string | null;
    answer_release_at: string | null; module_id: number | null;
    questions: Question[];
};

const props = defineProps<{ section: Section; modules: Module[]; assignment?: Assignment }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const form = useForm({
    title: props.assignment?.title ?? '',
    instructions: props.assignment?.instructions ?? '',
    type: props.assignment?.type ?? 'essay',
    due_date: props.assignment?.due_date?.slice(0, 16) ?? '',
    max_score: props.assignment?.max_score ?? 100,
    passing_score: props.assignment?.passing_score ?? '',
    is_published: props.assignment?.is_published ?? false,
    rubric: props.assignment?.rubric ?? '',
    language: props.assignment?.language ?? 'python',
    answer_release_at: props.assignment?.answer_release_at?.slice(0, 16) ?? '',
    module_id: props.assignment?.module_id?.toString() ?? '',
    questions: [] as Question[],
});

// MCQ question state (managed separately, merged on submit)
const questions = ref<Question[]>(
    props.assignment?.questions?.map((q) => ({
        id: q.id,
        question: q.question,
        points: q.points,
        choices: q.choices.map((c) => ({ id: c.id, choice_text: c.choice_text, is_correct: c.is_correct })),
    })) ?? [],
);

function addQuestion() {
    questions.value.push({
        question: '',
        points: 1,
        choices: [
            { choice_text: '', is_correct: true },
            { choice_text: '', is_correct: false },
        ],
    });
}

function removeQuestion(qi: number) {
    questions.value.splice(qi, 1);
}

function addChoice(qi: number) {
    questions.value[qi].choices.push({ choice_text: '', is_correct: false });
}

function removeChoice(qi: number, ci: number) {
    questions.value[qi].choices.splice(ci, 1);
}

function setCorrect(qi: number, ci: number) {
    questions.value[qi].choices.forEach((c, i) => (c.is_correct = i === ci));
}

const codeLanguages = ['python', 'javascript', 'java', 'cpp', 'csharp', 'php', 'ruby', 'go'];

function submit() {
    form.questions = questions.value;
    if (props.assignment) {
        form.put(`/assignments/${props.assignment.id}`);
    } else {
        form.post(`/sections/${props.section.id}/assignments`);
    }
}
</script>

<template>
    <Head :title="assignment ? 'Edit Assignment' : 'New Assignment'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <ClipboardList class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ assignment ? 'Edit Assignment' : 'New Assignment' }}</h1>
                <p class="text-sm text-muted-foreground">{{ section.subject.code }} · {{ section.name }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-3xl rounded-xl border bg-card p-6 shadow-sm space-y-6">
            <!-- Basic fields -->
            <div class="grid gap-1.5">
                <Label for="title">Assignment Title</Label>
                <Input id="title" v-model="form.title" placeholder="e.g. Midterm Essay" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label>Type</Label>
                    <Select v-model="form.type" :disabled="!!assignment">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="essay">Essay</SelectItem>
                            <SelectItem value="mcq">Multiple Choice</SelectItem>
                            <SelectItem value="code">Code Assignment</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>
                <div v-if="modules.length" class="grid gap-1.5">
                    <Label>Linked Module <span class="text-muted-foreground">(optional)</span></Label>
                    <Select v-model="form.module_id">
                        <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">None</SelectItem>
                            <SelectItem v-for="m in modules" :key="m.id" :value="m.id.toString()">{{ m.title }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Code language picker -->
            <div v-if="form.type === 'code'" class="grid gap-1.5 max-w-xs">
                <Label>Programming Language</Label>
                <Select v-model="form.language">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="lang in codeLanguages" :key="lang" :value="lang">{{ lang }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Instructions -->
            <div class="grid gap-1.5">
                <Label for="instructions">
                    {{ form.type === 'mcq' ? 'Quiz Instructions' : 'Problem Description / Instructions' }}
                </Label>
                <textarea
                    id="instructions"
                    v-model="form.instructions"
                    rows="5"
                    required
                    class="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    placeholder="Describe the assignment clearly..."
                />
                <InputError :message="form.errors.instructions" />
            </div>

            <!-- Rubric (essay & code) -->
            <div v-if="form.type !== 'mcq'" class="grid gap-1.5">
                <Label for="rubric">
                    Rubric / Grading Criteria <span class="text-muted-foreground">(used by AI grader)</span>
                </Label>
                <textarea
                    id="rubric"
                    v-model="form.rubric"
                    rows="4"
                    class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    placeholder="e.g. Content (40%), Organization (30%), Grammar (30%)"
                />
            </div>

            <!-- Scoring -->
            <div class="grid grid-cols-3 gap-4">
                <div class="grid gap-1.5">
                    <Label for="max_score">Max Score</Label>
                    <Input id="max_score" type="number" v-model="form.max_score" min="1" required />
                    <InputError :message="form.errors.max_score" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="passing_score">Passing Score <span class="text-muted-foreground">(opt.)</span></Label>
                    <Input id="passing_score" type="number" v-model="form.passing_score" min="0" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="due_date">Due Date <span class="text-muted-foreground">(opt.)</span></Label>
                    <Input id="due_date" type="datetime-local" v-model="form.due_date" />
                    <InputError :message="form.errors.due_date" />
                </div>
            </div>

            <!-- Answer release (MCQ) -->
            <div v-if="form.type === 'mcq'" class="grid gap-1.5 max-w-sm">
                <Label for="answer_release_at">Release Answer Key After <span class="text-muted-foreground">(opt.)</span></Label>
                <Input id="answer_release_at" type="datetime-local" v-model="form.answer_release_at" />
            </div>

            <!-- MCQ Question Builder -->
            <div v-if="form.type === 'mcq'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <Label class="text-base font-semibold">Questions</Label>
                    <Button type="button" variant="outline" size="sm" @click="addQuestion">
                        <Plus class="mr-1.5 h-4 w-4" /> Add Question
                    </Button>
                </div>

                <div v-if="questions.length === 0" class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No questions yet. Click "Add Question" to start.
                </div>

                <div v-for="(q, qi) in questions" :key="qi" class="rounded-xl border p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-2 shrink-0 text-sm font-semibold text-muted-foreground">Q{{ qi + 1 }}</span>
                        <div class="flex-1 space-y-2">
                            <textarea
                                v-model="q.question"
                                rows="2"
                                :placeholder="`Question ${qi + 1}`"
                                required
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <div class="flex items-center gap-2">
                                <Label class="text-xs text-muted-foreground">Points:</Label>
                                <Input type="number" v-model="q.points" min="0.5" step="0.5" class="w-20 h-7 text-sm" />
                            </div>
                        </div>
                        <Button type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive shrink-0" @click="removeQuestion(qi)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Choices -->
                    <div class="ml-8 space-y-2">
                        <div v-for="(c, ci) in q.choices" :key="ci" class="flex items-center gap-2">
                            <button
                                type="button"
                                :title="c.is_correct ? 'Correct answer' : 'Set as correct'"
                                @click="setCorrect(qi, ci)"
                                class="shrink-0"
                            >
                                <CheckCircle class="h-5 w-5 transition-colors" :class="c.is_correct ? 'text-green-500' : 'text-muted-foreground/30 hover:text-muted-foreground'" />
                            </button>
                            <Input v-model="c.choice_text" :placeholder="`Choice ${ci + 1}`" class="flex-1" required />
                            <Button
                                v-if="q.choices.length > 2"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="shrink-0 text-muted-foreground"
                                @click="removeChoice(qi, ci)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <Button type="button" variant="ghost" size="sm" class="text-xs" @click="addChoice(qi)">
                            <Plus class="mr-1 h-3.5 w-3.5" /> Add Choice
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Publish + Submit -->
            <div class="flex items-center gap-3">
                <Checkbox id="is_published" v-model:checked="form.is_published" />
                <Label for="is_published" class="cursor-pointer">Publish immediately (visible to students)</Label>
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ assignment ? 'Update Assignment' : 'Create Assignment' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/sections/${section.id}/assignments`">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
