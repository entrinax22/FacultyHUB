<script setup lang="ts">
import axios from 'axios';
import { FileUp, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import type { AssignmentDraft } from '@/lib/assignmentTypes';
import { showApiError, showApiToast } from '@/lib/flashToast';

const props = defineProps<{ sectionId: string }>();
const emit = defineEmits<{ apply: [draft: AssignmentDraft] }>();

const pdfInput = ref<HTMLInputElement | null>(null);
const importingPdf = ref(false);
const pdfDraft = ref<AssignmentDraft | null>(null);
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
        const response = await axios.post(
            `/sections/${props.sectionId}/assignments/import-pdf`,
            payload,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    Accept: 'application/json',
                },
            },
        );
        const data = response.data?.data ?? response.data;
        pdfDraft.value = {
            title: data.title ?? '',
            type: data.type ?? 'essay',
            category: data.category ?? null,
            language: data.language ?? null,
            instructions: data.instructions ?? '',
            rubric: data.rubric ?? '',
            questions: data.questions ?? [],
        };
        showApiToast(response);
        toast.success(
            'PDF converted. Review and edit the draft before applying it.',
        );
    } catch (error) {
        console.error('PDF import error:', error);
        showApiError(error);
    } finally {
        importingPdf.value = false;
        input.value = '';
    }
}

function applyDraft() {
    if (!pdfDraft.value) {
        return;
    }

    emit('apply', pdfDraft.value);
    pdfDraft.value = null;
}
</script>

<template>
    <section class="rounded-lg border border-dashed bg-muted/20 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-medium">Import from PDF</h2>
                <p class="text-sm text-muted-foreground">
                    AI will create a draft for you to review before applying it.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="importingPdf"
                @click="pdfInput?.click()"
            >
                <LoaderCircle
                    v-if="importingPdf"
                    class="mr-2 h-4 w-4 animate-spin"
                />
                <FileUp v-else class="mr-2 h-4 w-4" />
                {{ importingPdf ? 'Converting…' : 'Upload PDF' }}
            </Button>
            <input
                ref="pdfInput"
                type="file"
                accept="application/pdf,.pdf"
                class="hidden"
                @change="importPdf"
            />
        </div>

        <div
            v-if="pdfDraft"
            class="mt-4 space-y-4 rounded-md border bg-card p-4"
        >
            <div>
                <h3 class="font-medium">Review extracted draft</h3>
                <p class="text-xs text-muted-foreground">
                    Review the detected assignment type and edit the generated
                    content before applying it.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label>Detected type</Label>
                    <Select v-model="pdfDraft.type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="essay">Essay</SelectItem>
                            <SelectItem value="mcq">Multiple Choice</SelectItem>
                            <SelectItem value="code"
                                >Code Assignment</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Category</Label>
                    <Select v-model="pdfDraft.category">
                        <SelectTrigger
                            ><SelectValue placeholder="None"
                        /></SelectTrigger>
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
                        <SelectTrigger
                            ><SelectValue placeholder="Select language"
                        /></SelectTrigger>
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
            </div>

            <div class="grid gap-1.5">
                <Label for="pdf_draft_title">Draft title</Label>
                <Input id="pdf_draft_title" v-model="pdfDraft.title" />
            </div>
            <div class="grid gap-1.5">
                <Label for="pdf_draft_instructions">Draft instructions</Label>
                <Textarea
                    id="pdf_draft_instructions"
                    v-model="pdfDraft.instructions"
                    rows="6"
                    class="min-h-[140px]"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="pdf_draft_rubric">Draft rubric</Label>
                <Textarea
                    id="pdf_draft_rubric"
                    v-model="pdfDraft.rubric"
                    rows="4"
                    class="min-h-[100px]"
                />
            </div>

            <div v-if="pdfDraft.type === 'mcq'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <Label>Generated questions</Label>
                    <span class="text-xs text-muted-foreground">
                        Green checks are AI-predicted answers. Review them
                        carefully.
                    </span>
                </div>
                <div
                    v-for="(question, questionIndex) in pdfDraft.questions"
                    :key="questionIndex"
                    class="space-y-2 rounded-md border p-3"
                >
                    <div class="flex gap-2">
                        <span
                            class="pt-2 text-xs font-semibold text-muted-foreground"
                            >Q{{ questionIndex + 1 }}</span
                        >
                        <Textarea
                            v-model="question.question"
                            rows="2"
                            class="min-h-[60px] flex-1"
                        />
                        <Input
                            v-model="question.points"
                            type="number"
                            min="0.5"
                            step="0.5"
                            class="w-20"
                        />
                    </div>
                    <div
                        v-for="(choice, choiceIndex) in question.choices"
                        :key="choiceIndex"
                        class="flex items-center gap-2 pl-6"
                    >
                        <Checkbox v-model:checked="choice.is_correct" />
                        <Input v-model="choice.choice_text" class="flex-1" />
                    </div>
                </div>
                <p
                    v-if="pdfDraft.questions.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No questions were detected. You can add them after applying
                    the draft.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" @click="pdfDraft = null"
                    >Discard draft</Button
                >
                <Button type="button" @click="applyDraft"
                    >Apply to assignment</Button
                >
            </div>
        </div>
    </section>
</template>
