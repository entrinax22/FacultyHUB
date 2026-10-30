<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ClipboardList } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import AssignmentDraftImport from '@/components/assignments/AssignmentDraftImport.vue';
import AssignmentQuestionBuilder from '@/components/assignments/AssignmentQuestionBuilder.vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import FormSection from '@/components/FormSection.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import SectionBackLink from '@/components/SectionBackLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AssignmentDraft,
    AssignmentQuestion,
} from '@/lib/assignmentTypes';
import { showApiError, showApiToast } from '@/lib/flashToast';

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
    };
    semester: {
        id?: string;
        name: string;
        school_year: string;
    };
};

type Assignment = {
    id: string;
    title: string;
    instructions: string;
    type: string;
    period: string | null;
    category: string | null;
    component_id: string | null;
    due_date: string | null;
    max_score: number;
    passing_score: number | null;
    is_published: boolean;
    rubric: string | null;
    language: string | null;
    answer_release_at: string | null;
    module_id: string | null;
    duration_minutes: number | null;
    proctoring_enabled: boolean;
    allow_question_skipping: boolean;
    questions: AssignmentQuestion[];
};

type AssignmentForm = {
    title: string;
    instructions: string;
    type: string;
    period: string;
    category: string;
    component_id: string;
    due_date: string;
    max_score: number | string;
    passing_score: number | string;
    is_published: boolean;
    rubric: string;
    language: string;
    answer_release_at: string;
    module_id: string;
    duration_minutes: number | string;
    proctoring_enabled: boolean;
    allow_question_skipping: boolean;
    questions: AssignmentQuestion[];
};

const props = defineProps<{
    section: Section;
    modules: Module[];
    components: GradingComponent[];
    assignment?: Assignment;
}>();

const backToAssignments =
    new URLSearchParams(usePage().url.split('?')[1] ?? '').get('back') ===
    'assignments';
const backToAssignment = Boolean(props.assignment && !backToAssignments);
const formBackHref =
    props.assignment && backToAssignment
        ? `/assignments/${props.assignment.id}`
        : `/sections/${props.section.id}/assignments`;

function toLocalDateTimeInput(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

function toUtcDateTime(value: string): string {
    return value ? new Date(value).toISOString() : '';
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const form = ref<AssignmentForm>({
    title: props.assignment?.title ?? '',
    instructions: props.assignment?.instructions ?? '',
    type: props.assignment?.type ?? 'essay',
    period: props.assignment?.period ?? 'none',
    category: props.assignment?.category ?? 'none',
    component_id: props.assignment?.component_id ?? 'none',
    due_date: toLocalDateTimeInput(props.assignment?.due_date),
    max_score: props.assignment?.max_score ?? 100,
    passing_score: props.assignment?.passing_score ?? '',
    is_published: props.assignment?.is_published ?? false,
    rubric: props.assignment?.rubric ?? '',
    language: props.assignment?.language ?? 'python',
    answer_release_at: toLocalDateTimeInput(
        props.assignment?.answer_release_at,
    ),
    module_id: props.assignment?.module_id ?? 'none',
    duration_minutes: props.assignment?.duration_minutes ?? '',
    proctoring_enabled: props.assignment?.proctoring_enabled ?? false,
    allow_question_skipping: props.assignment?.allow_question_skipping ?? false,
    questions: [],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const questions = ref<AssignmentQuestion[]>(
    props.assignment?.questions?.map((question) => ({
        id: question.id,
        question: question.question,
        points: question.points,
        choices: question.choices.map((choice) => ({
            id: choice.id,
            choice_text: choice.choice_text,
            is_correct: choice.is_correct,
        })),
    })) ?? [],
);

const codeLanguages = [
    'python',
    'javascript',
    'java',
    'cpp',
    'csharp',
    'php',
    'ruby',
    'go',
];

/*
|--------------------------------------------------------------------------
| Watchers
|--------------------------------------------------------------------------
*/

watch(
    () => form.value.period,
    (value) => {
        if (!value || value === 'none') {
            form.value.component_id = 'none';

            return;
        }

        const matchingComponents = props.components.filter(
            (component) =>
                component.period === value || component.period === null,
        );

        if (matchingComponents.length === 1) {
            form.value.component_id = matchingComponents[0].id;
        } else if (
            !matchingComponents.some(
                (component) => component.id === form.value.component_id,
            )
        ) {
            form.value.component_id = 'none';
        }
    },
);

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const filteredComponents = computed(() => {
    if (form.value.period === 'none' || !form.value.period) {
        return props.components;
    }

    return props.components.filter(
        (component) =>
            component.period === form.value.period || component.period === null,
    );
});

/*
|--------------------------------------------------------------------------
| Form Helpers
|--------------------------------------------------------------------------
*/

function preparePayload(): AssignmentForm {
    return {
        ...form.value,

        due_date: toUtcDateTime(form.value.due_date),

        answer_release_at: toUtcDateTime(form.value.answer_release_at),

        period: form.value.period === 'none' ? '' : form.value.period,

        category: form.value.category === 'none' ? '' : form.value.category,

        component_id:
            form.value.component_id === 'none' ? '' : form.value.component_id,

        module_id: form.value.module_id === 'none' ? '' : form.value.module_id,

        proctoring_enabled: Boolean(form.value.proctoring_enabled),
        allow_question_skipping: Boolean(form.value.allow_question_skipping),

        questions: questions.value,
    };
}

function clearErrors() {
    errors.value = {};
}

function handleValidationError(error: unknown): boolean {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
        errors.value = error.response.data?.errors ?? {};

        return true;
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function submit() {
    clearErrors();

    processing.value = true;

    const payload = preparePayload();

    try {
        if (props.assignment) {
            const response = await axios.put(
                `/assignments/${props.assignment.id}`,
                payload,
            );

            showApiToast(response);

            router.visit(`/assignments/${props.assignment.id}`);
        } else {
            const response = await axios.post(
                `/sections/${props.section.id}/assignments`,
                payload,
            );

            showApiToast(response);

            const assignmentId = response.data?.data?.id;

            if (assignmentId) {
                router.visit(`/assignments/${assignmentId}`);
            } else {
                router.visit(`/sections/${props.section.id}/assignments`);
            }
        }
    } catch (error) {
        if (!handleValidationError(error)) {
            console.error('Assignment submission error:', error);

            showApiError(error);
        }
    } finally {
        processing.value = false;
    }
}

function applyPdfDraft(draft: AssignmentDraft) {
    form.value.title = draft.title;

    if (!props.assignment) {
        form.value.type = draft.type;
    }

    form.value.category = draft.category ?? 'none';
    form.value.instructions = draft.instructions;
    form.value.rubric = draft.rubric;

    if (draft.language) {
        form.value.language = draft.language;
    }

    questions.value = draft.questions.map((question) => ({
        id: question.id,
        question: question.question,
        points: question.points,
        choices: question.choices.map((choice) => ({
            id: choice.id,
            choice_text: choice.choice_text,
            is_correct: choice.is_correct,
        })),
    }));

    toast.success('Draft applied. You can continue editing before saving.');
}
</script>

<template>
    <Head :title="assignment ? 'Edit Assignment' : 'New Assignment'" />

    <div class="flex min-h-full flex-1 flex-col gap-6 p-3 sm:p-4 lg:p-6">
        <SectionBackLink
            class="-mb-3"
            :href="formBackHref"
            :label="backToAssignment ? 'Back to Assignment' : 'Back to Assignments'"
        />
        <PageHeader
            :title="assignment ? 'Edit Assignment' : 'New Assignment'"
            :description="section.subject.code + ' · ' + section.name"
            :icon="ClipboardList"
        />
        <!-- Form -->
        <form
            @submit.prevent="submit"
            class="w-full max-w-5xl space-y-6 rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-6"
        >
            <FormSection
                title="Basic Information"
                description="Give the assignment a clear title and choose how it will be delivered."
                variant="divided"
            >
                <FormField
                    id="title"
                    label="Assignment Title"
                    :error="errors.title"
                    required
                    v-slot="{ id, describedBy }"
                >
                    <Input
                        :id="id"
                        v-model="form.title"
                        placeholder="e.g. Midterm Essay"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="Boolean(errors.title)"
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Assignment Classification"
                description="Set the type, category, and grading period. Assignment type cannot change after creation."
                variant="divided"
            >
                <div class="grid gap-5 md:grid-cols-3">
                    <FormField
                        id="type"
                        label="Type"
                        :error="errors.type"
                        required
                        v-slot="{ id, describedBy }"
                    >
                        <Select v-model="form.type" :disabled="!!assignment">
                            <SelectTrigger
                                :id="id"
                                :aria-describedby="describedBy"
                                :aria-invalid="Boolean(errors.type)"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="essay"> Essay </SelectItem>
                                <SelectItem value="mcq"
                                    >Multiple Choice</SelectItem
                                >
                                <SelectItem value="code"
                                    >Code Assignment</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        id="category"
                        label="Category"
                        hint="Optional"
                        :error="errors.category"
                        v-slot="{ id, describedBy }"
                    >
                        <Select v-model="form.category">
                            <SelectTrigger
                                :id="id"
                                :aria-describedby="describedBy"
                                :aria-invalid="Boolean(errors.category)"
                            >
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"> None </SelectItem>
                                <SelectItem value="quiz"> Quiz </SelectItem>
                                <SelectItem value="exam"> Exam </SelectItem>
                                <SelectItem value="activity">
                                    Activity
                                </SelectItem>
                                <SelectItem value="project">
                                    Project
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        id="period"
                        label="Period"
                        hint="Optional"
                        :error="errors.period"
                        v-slot="{ id, describedBy }"
                    >
                        <Select v-model="form.period">
                            <SelectTrigger
                                :id="id"
                                :aria-describedby="describedBy"
                                :aria-invalid="Boolean(errors.period)"
                            >
                                <SelectValue placeholder="No period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    No period
                                </SelectItem>
                                <SelectItem value="midterm">
                                    Midterm
                                </SelectItem>
                                <SelectItem value="finals"> Finals </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
            </FormSection>

            <!-- Schedule and availability -->
            <FormSection
                title="Schedule and Availability"
                description="Control timing, monitoring, and when students can review answers."
                variant="divided"
            >
                <FormField
                    v-if="
                        form.category === 'exam' ||
                        (form.type === 'mcq' && form.proctoring_enabled)
                    "
                    id="duration_minutes"
                    label="Exam Duration (minutes)"
                    hint="The timer starts when each student accepts the exam terms and presses Start Exam."
                    :error="errors.duration_minutes"
                    required
                    v-slot="{ id, describedBy }"
                >
                    <Input
                        :id="id"
                        v-model="form.duration_minutes"
                        type="number"
                        min="1"
                        max="600"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="Boolean(errors.duration_minutes)"
                    />
                </FormField>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="due_date"
                        label="Due Date"
                        hint="Optional"
                        :error="errors.due_date"
                        v-slot="{ id, describedBy }"
                    >
                        <Input
                            :id="id"
                            type="datetime-local"
                            v-model="form.due_date"
                            :aria-describedby="describedBy"
                            :aria-invalid="Boolean(errors.due_date)"
                        />
                    </FormField>

                    <FormField
                        v-if="form.type === 'mcq'"
                        id="answer_release_at"
                        label="Release Answer Key After"
                        hint="Optional"
                        :error="errors.answer_release_at"
                        v-slot="{ id, describedBy }"
                    >
                        <Input
                            :id="id"
                            type="datetime-local"
                            v-model="form.answer_release_at"
                            :aria-describedby="describedBy"
                            :aria-invalid="Boolean(errors.answer_release_at)"
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                v-if="
                    components.length || modules.length || form.type === 'code'
                "
                title="Assignment Context"
                description="Connect this assignment to a grading component, module, or programming language."
                variant="divided"
            >
                <!-- Grading Component -->
                <div v-if="components.length" class="grid min-w-0 gap-1.5">
                    <Label>
                        Grading Component

                        <span class="text-muted-foreground">
                            (adds an assessment item automatically)
                        </span>
                    </Label>

                    <Select v-model="form.component_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Select component…" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="none"> None </SelectItem>

                            <SelectItem
                                v-for="component in filteredComponents"
                                :key="component.id"
                                :value="component.id"
                            >
                                {{ component.name }}

                                <span
                                    class="ml-1 text-xs text-muted-foreground capitalize"
                                >
                                    (
                                    {{ component.period ?? 'general' }}
                                    ·
                                    {{ component.weight_percentage }}%)
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p
                        v-if="form.component_id !== 'none'"
                        class="text-xs text-muted-foreground"
                    >
                        An assessment item named "{{
                            form.title || 'this assignment'
                        }}" will be created under the selected component.
                    </p>

                    <InputError :message="errors.component_id" />
                </div>

                <!-- Linked Module -->
                <div v-if="modules.length" class="grid min-w-0 gap-1.5">
                    <Label>
                        Linked Module
                        <span class="text-muted-foreground"> (optional) </span>
                    </Label>

                    <Select v-model="form.module_id">
                        <SelectTrigger>
                            <SelectValue placeholder="None" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="none"> None </SelectItem>

                            <SelectItem
                                v-for="module in modules"
                                :key="module.id"
                                :value="module.id"
                            >
                                {{ module.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <InputError :message="errors.module_id" />
                </div>

                <!-- Code Language -->
                <div v-if="form.type === 'code'" class="grid min-w-0 gap-1.5">
                    <Label> Programming Language </Label>

                    <Select v-model="form.language">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem
                                v-for="language in codeLanguages"
                                :key="language"
                                :value="language"
                            >
                                {{ language }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </FormSection>

            <FormSection
                title="Instructions"
                description="Explain the task, expectations, and any information students need before submitting."
                variant="divided"
            >
                <FormField
                    id="instructions"
                    :label="
                        form.type === 'mcq'
                            ? 'Quiz Instructions'
                            : 'Problem Description / Instructions'
                    "
                    :error="errors.instructions"
                    required
                    v-slot="{ id, describedBy }"
                >
                    <RichTextEditor
                        :id="id"
                        v-model="form.instructions"
                        required
                        placeholder="Describe the assignment clearly..."
                        :aria-describedby="describedBy"
                        :aria-invalid="Boolean(errors.instructions)"
                    />
                </FormField>

                <AssignmentDraftImport
                    :section-id="section.id"
                    @apply="applyPdfDraft"
                />
            </FormSection>

            <FormSection
                title="Assessment and Grading"
                description="Set grading criteria, scores, and due dates for this assignment."
                variant="divided"
            >
                <!-- Rubric -->
                <FormField
                    v-if="form.type !== 'mcq'"
                    id="rubric"
                    label="Rubric / Grading Criteria"
                    hint="Used by the AI grader."
                    :error="errors.rubric"
                    v-slot="{ id, describedBy }"
                >
                    <RichTextEditor
                        :id="id"
                        v-model="form.rubric"
                        placeholder="e.g. Content (40%), Organization (30%), Grammar (30%)"
                        :aria-describedby="describedBy"
                        :aria-invalid="Boolean(errors.rubric)"
                    />
                </FormField>

                <!-- Scoring -->
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Max Score -->
                    <FormField
                        id="max_score"
                        label="Max Score"
                        :error="errors.max_score"
                        required
                        v-slot="{ id, describedBy }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            v-model="form.max_score"
                            min="1"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="Boolean(errors.max_score)"
                        />
                    </FormField>

                    <!-- Passing Score -->
                    <FormField
                        id="passing_score"
                        label="Passing Score"
                        hint="Optional"
                        :error="errors.passing_score"
                        v-slot="{ id, describedBy }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            v-model="form.passing_score"
                            min="0"
                            :aria-describedby="describedBy"
                            :aria-invalid="Boolean(errors.passing_score)"
                        />
                    </FormField>
                </div>
            </FormSection>

            <FormSection
                v-if="form.type === 'mcq'"
                title="Questions and Answer Configuration"
                description="Build the multiple-choice questions and mark one correct answer for each choice set."
                variant="divided"
            >
                <AssignmentQuestionBuilder
                    v-model="questions"
                    :errors="errors"
                />
            </FormSection>

            <FormSection
                title="Advanced Settings"
                description="Choose whether the assignment is published immediately."
                variant="divided"
            >
                <label
                    v-if="form.type === 'mcq' || form.category === 'exam'"
                    class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <input
                        id="proctoring_enabled"
                        v-model="form.proctoring_enabled"
                        name="proctoring_enabled"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-primary"
                    />

                    <span>
                        <span class="block text-sm font-medium">
                            Enable exam monitoring
                        </span>

                        <span
                            class="block text-xs text-amber-800 dark:text-amber-200"
                        >
                            Record tab changes and window resizing during the
                            student attempt.
                        </span>
                    </span>
                </label>

                <label
                    v-if="form.type === 'mcq' && form.proctoring_enabled"
                    class="flex items-start gap-3 rounded-md border p-3"
                >
                    <input
                        id="allow_question_skipping"
                        v-model="form.allow_question_skipping"
                        name="allow_question_skipping"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-primary"
                    />

                    <span>
                        <span class="block text-sm font-medium">
                            Allow students to skip questions
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            When disabled, students must answer the current
                            question before moving forward.
                        </span>
                    </span>
                </label>

                <!-- Publish -->
                <div class="flex items-center gap-3">
                    <Checkbox id="is_published" v-model="form.is_published" />

                    <Label for="is_published" class="cursor-pointer">
                        Publish immediately (visible to students)
                    </Label>
                </div>
            </FormSection>

            <FormActions
                :processing="processing"
                :submit-label="
                    assignment ? 'Update Assignment' : 'Create Assignment'
                "
                :processing-label="assignment ? 'Updating...' : 'Creating...'"
                :cancel-href="formBackHref"
            />
        </form>
    </div>
</template>
