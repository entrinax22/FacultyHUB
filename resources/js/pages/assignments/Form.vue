<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle, ClipboardList, FileUp, LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Choice = { id?: number; choice_text: string; is_correct: boolean };
type Question = { id?: number; question: string; points: number; choices: Choice[] };

type Module = { id: number; title: string };
type GradingComponent = { id: number; name: string; period: string | null; weight_percentage: number };
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};
type Assignment = {
    id: number; title: string; instructions: string; type: string;
    period: string | null; category: string | null; component_id: number | null;
    due_date: string | null; max_score: number; passing_score: number | null;
    is_published: boolean; rubric: string | null; language: string | null;
    answer_release_at: string | null; module_id: number | null; duration_minutes: number | null; proctoring_enabled: boolean;
    questions: Question[];
};
type PdfDraft = {
    title: string;
    type: 'essay' | 'mcq' | 'code';
    category: 'quiz' | 'exam' | 'activity' | 'project' | null;
    language: string | null;
    instructions: string;
    rubric: string;
    questions: Question[];
};

const props = defineProps<{ section: Section; modules: Module[]; components: GradingComponent[]; assignment?: Assignment }>();

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
    period: props.assignment?.period ?? 'none',
    category: props.assignment?.category ?? 'none',
    component_id: props.assignment?.component_id?.toString() ?? 'none',
    due_date: props.assignment?.due_date?.slice(0, 16) ?? '',
    max_score: props.assignment?.max_score ?? 100,
    passing_score: props.assignment?.passing_score ?? '',
    is_published: props.assignment?.is_published ?? false,
    rubric: props.assignment?.rubric ?? '',
    language: props.assignment?.language ?? 'python',
    answer_release_at: props.assignment?.answer_release_at?.slice(0, 16) ?? '',
    module_id: props.assignment?.module_id?.toString() ?? 'none',
    duration_minutes: props.assignment?.duration_minutes ?? '',
    proctoring_enabled: props.assignment?.proctoring_enabled ?? false,
    questions: [] as Question[],
});

// Reset component when category is cleared
watch(() => form.category, (val) => {
    if (val === 'none') {
form.component_id = 'none';
}
});

// Filter components to match the selected period (or show all if no period set)
const filteredComponents = computed(() => {
    if (form.period === 'none' || !form.period) {
return props.components;
}

    return props.components.filter(c => c.period === form.period || c.period === null);
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
const pdfInput = ref<HTMLInputElement | null>(null);
const importingPdf = ref(false);
const pdfDraft = ref<PdfDraft | null>(null);

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

    form.transform((data) => ({
        ...data,
        proctoring_enabled: Boolean(data.proctoring_enabled),
    }));

    if (form.period === 'none') {
form.period = '';
}

    if (form.category === 'none') {
form.category = '';
}

    if (form.component_id === 'none') {
form.component_id = '';
}

    if (form.module_id === 'none') {
form.module_id = '';
}

    if (props.assignment) {
        form.put(`/assignments/${props.assignment.id}`);
    } else {
        form.post(`/sections/${props.section.id}/assignments`);
    }
}

async function importPdf(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
return;
}

    if (file.type !== 'application/pdf') {
        toast.error('Only PDF files can be uploaded.');
        input.value = '';

        return;
    }

    const payload = new FormData();
    payload.append('document', file);
    importingPdf.value = true;

    try {
        const response = await fetch(`/sections/${props.section.id}/assignments/import-pdf`, {
            method: 'POST',
            body: payload,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message ?? 'The PDF could not be processed.');
        }

        pdfDraft.value = result;
        toast.success('PDF converted. Review and edit the draft before applying it.');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The PDF could not be processed.');
    } finally {
        importingPdf.value = false;
        input.value = '';
    }
}

function applyPdfDraft() {
    if (!pdfDraft.value) {
return;
}

    form.title = pdfDraft.value.title;

    if (!props.assignment) {
        form.type = pdfDraft.value.type;
    }

    form.category = pdfDraft.value.category ?? 'none';
    form.instructions = pdfDraft.value.instructions;
    form.rubric = pdfDraft.value.rubric;

    if (pdfDraft.value.language) {
        form.language = pdfDraft.value.language;
    }

    questions.value = pdfDraft.value.questions;
    pdfDraft.value = null;
    toast.success('Draft applied. You can continue editing before saving.');
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

            <!-- Type / Category / Period -->
            <div class="grid grid-cols-3 gap-4">
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
                <div class="grid gap-1.5">
                    <Label>Category <span class="text-muted-foreground">(optional)</span></Label>
                    <Select v-model="form.category">
                        <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">None</SelectItem>
                            <SelectItem value="quiz">Quiz</SelectItem>
                            <SelectItem value="exam">Exam</SelectItem>
                            <SelectItem value="activity">Activity</SelectItem>
                            <SelectItem value="project">Project</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Period <span class="text-muted-foreground">(optional)</span></Label>
                    <Select v-model="form.period">
                        <SelectTrigger><SelectValue placeholder="No period" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No period</SelectItem>
                            <SelectItem value="midterm">Midterm</SelectItem>
                            <SelectItem value="finals">Finals</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.period" />
                </div>
            </div>

            <div v-if="form.category === 'exam' || (form.type === 'mcq' && form.proctoring_enabled)" class="grid gap-1.5 max-w-sm">
                <Label for="duration_minutes">Exam Duration (minutes)</Label>
                <Input id="duration_minutes" v-model="form.duration_minutes" type="number" min="1" max="600" required />
                <p class="text-xs text-muted-foreground">The timer starts when each student accepts the exam terms and presses Start Exam.</p>
                <InputError :message="form.errors.duration_minutes" />
            </div>

            <label v-if="form.type === 'mcq' || form.category === 'exam'" class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-950">
                <input
                    id="proctoring_enabled"
                    v-model="form.proctoring_enabled"
                    name="proctoring_enabled"
                    type="checkbox"
                    class="mt-0.5 size-4 accent-primary"
                />
                <span>
                    <span class="block text-sm font-medium">Enable exam monitoring</span>
                    <span class="block text-xs text-amber-800">Record tab changes and window resizing during the student attempt.</span>
                </span>
            </label>

            <!-- Grading Component (shown when a category is selected) -->
            <div v-if="form.category !== 'none' && components.length" class="grid gap-1.5 max-w-sm">
                <Label>
                    Grading Component
                    <span class="text-muted-foreground">(adds an assessment item automatically)</span>
                </Label>
                <Select v-model="form.component_id">
                    <SelectTrigger><SelectValue placeholder="Select component…" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">None</SelectItem>
                        <SelectItem v-for="c in filteredComponents" :key="c.id" :value="c.id.toString()">
                            {{ c.name }}
                            <span class="ml-1 text-xs text-muted-foreground capitalize">
                                ({{ c.period ?? 'general' }} · {{ c.weight_percentage }}%)
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.component_id !== 'none'" class="text-xs text-muted-foreground">
                    An assessment item named "{{ form.title || 'this assignment' }}" will be created under the selected component.
                </p>
                <InputError :message="form.errors.component_id" />
            </div>

            <!-- Linked Module -->
            <div v-if="modules.length" class="grid gap-1.5 max-w-sm">
                <Label>Linked Module <span class="text-muted-foreground">(optional)</span></Label>
                <Select v-model="form.module_id">
                    <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">None</SelectItem>
                        <SelectItem v-for="m in modules" :key="m.id" :value="m.id.toString()">{{ m.title }}</SelectItem>
                    </SelectContent>
                </Select>
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

            <section class="rounded-lg border border-dashed bg-muted/20 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-medium">Import from PDF</h2>
                        <p class="text-sm text-muted-foreground">AI will create a draft for you to review before applying it.</p>
                    </div>
                    <Button type="button" variant="outline" :disabled="importingPdf" @click="pdfInput?.click()">
                        <LoaderCircle v-if="importingPdf" class="mr-2 h-4 w-4 animate-spin" />
                        <FileUp v-else class="mr-2 h-4 w-4" />
                        {{ importingPdf ? 'Converting…' : 'Upload PDF' }}
                    </Button>
                    <input ref="pdfInput" type="file" accept="application/pdf,.pdf" class="hidden" @change="importPdf" />
                </div>

                <div v-if="pdfDraft" class="mt-4 space-y-4 rounded-md border bg-card p-4">
                    <div>
                        <h3 class="font-medium">Review extracted draft</h3>
                        <p class="text-xs text-muted-foreground">Review the detected assignment type and edit the generated content before applying it.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label>Detected type</Label>
                            <Select v-model="pdfDraft.type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="essay">Essay</SelectItem>
                                    <SelectItem value="mcq">Multiple Choice</SelectItem>
                                    <SelectItem value="code">Code Assignment</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Category</Label>
                            <Select v-model="pdfDraft.category">
                                <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">None</SelectItem>
                                    <SelectItem value="quiz">Quiz</SelectItem>
                                    <SelectItem value="exam">Exam</SelectItem>
                                    <SelectItem value="activity">Activity</SelectItem>
                                    <SelectItem value="project">Project</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="pdfDraft.type === 'code'" class="grid gap-1.5">
                            <Label>Language</Label>
                            <Select v-model="pdfDraft.language">
                                <SelectTrigger><SelectValue placeholder="Select language" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="language in codeLanguages" :key="language" :value="language">{{ language }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pdf_draft_title">Draft title</Label>
                        <Input id="pdf_draft_title" v-model="pdfDraft.title" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pdf_draft_instructions">Draft instructions</Label>
                        <textarea id="pdf_draft_instructions" v-model="pdfDraft.instructions" rows="6" class="flex min-h-[140px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pdf_draft_rubric">Draft rubric</Label>
                        <textarea id="pdf_draft_rubric" v-model="pdfDraft.rubric" rows="4" class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                    </div>
                    <div v-if="pdfDraft.type === 'mcq'" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Generated questions</Label>
                            <span class="text-xs text-muted-foreground">Green checks are AI-predicted answers. Review them carefully.</span>
                        </div>
                        <div v-for="(question, questionIndex) in pdfDraft.questions" :key="questionIndex" class="space-y-2 rounded-md border p-3">
                            <div class="flex gap-2">
                                <span class="pt-2 text-xs font-semibold text-muted-foreground">Q{{ questionIndex + 1 }}</span>
                                <textarea v-model="question.question" rows="2" class="flex min-h-[60px] flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                <Input v-model="question.points" type="number" min="0.5" step="0.5" class="w-20" />
                            </div>
                            <div v-for="(choice, choiceIndex) in question.choices" :key="choiceIndex" class="flex items-center gap-2 pl-6">
                                <Checkbox v-model:checked="choice.is_correct" />
                                <Input v-model="choice.choice_text" class="flex-1" />
                            </div>
                        </div>
                        <p v-if="pdfDraft.questions.length === 0" class="text-sm text-muted-foreground">No questions were detected. You can add them after applying the draft.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="ghost" @click="pdfDraft = null">Discard draft</Button>
                        <Button type="button" @click="applyPdfDraft">Apply to assignment</Button>
                    </div>
                </div>
            </section>

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
