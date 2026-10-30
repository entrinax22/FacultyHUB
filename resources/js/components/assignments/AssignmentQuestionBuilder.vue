<script setup lang="ts">
import { CheckCircle, Plus, Trash2 } from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AssignmentQuestion } from '@/lib/assignmentTypes';

const questions = defineModel<AssignmentQuestion[]>({ required: true });

const props = defineProps<{
    errors: Record<string, string>;
}>();

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

function removeQuestion(index: number) {
    questions.value.splice(index, 1);
}

function addChoice(questionIndex: number) {
    questions.value[questionIndex].choices.push({
        choice_text: '',
        is_correct: false,
    });
}

function removeChoice(questionIndex: number, choiceIndex: number) {
    questions.value[questionIndex].choices.splice(choiceIndex, 1);
}

function setCorrect(questionIndex: number, choiceIndex: number) {
    questions.value[questionIndex].choices.forEach((choice, index) => {
        choice.is_correct = index === choiceIndex;
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <Label class="text-base font-semibold">Questions</Label>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addQuestion"
            >
                <Plus class="mr-1.5 h-4 w-4" />
                Add Question
            </Button>
        </div>

        <div
            v-if="questions.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            No questions yet. Click "Add Question" to start.
        </div>

        <div
            v-for="(question, questionIndex) in questions"
            :key="question.id ?? questionIndex"
            class="space-y-3 rounded-xl border p-4"
        >
            <div class="flex items-start gap-3">
                <span
                    class="mt-2 shrink-0 text-sm font-semibold text-muted-foreground"
                >
                    Q{{ questionIndex + 1 }}
                </span>
                <div class="min-w-0 flex-1 space-y-2">
                    <Textarea
                        :id="'question_' + questionIndex"
                        v-model="question.question"
                        rows="2"
                        :placeholder="'Question ' + (questionIndex + 1)"
                        required
                        class="min-h-[60px]"
                        :aria-invalid="
                            Boolean(
                                props.errors[
                                    `questions.${questionIndex}.question`
                                ],
                            )
                        "
                    />
                    <InputError
                        :message="
                            props.errors[`questions.${questionIndex}.question`]
                        "
                    />
                    <div class="flex items-center gap-2">
                        <Label class="text-xs text-muted-foreground"
                            >Points:</Label
                        >
                        <Input
                            v-model="question.points"
                            type="number"
                            min="0.5"
                            step="0.5"
                            class="h-7 w-20 text-sm"
                        />
                    </div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="shrink-0 text-destructive hover:text-destructive"
                    :aria-label="`Remove question ${questionIndex + 1}`"
                    @click="removeQuestion(questionIndex)"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>

            <div class="ml-0 space-y-2 sm:ml-8">
                <div
                    v-for="(choice, choiceIndex) in question.choices"
                    :key="choice.id ?? choiceIndex"
                    class="flex items-center gap-2"
                >
                    <button
                        type="button"
                        :aria-pressed="choice.is_correct"
                        :aria-label="`Mark choice ${choiceIndex + 1} of question ${questionIndex + 1} as correct`"
                        :title="
                            choice.is_correct
                                ? 'Correct answer'
                                : 'Set as correct'
                        "
                        class="shrink-0 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="setCorrect(questionIndex, choiceIndex)"
                    >
                        <CheckCircle
                            class="h-5 w-5 transition-colors"
                            :class="
                                choice.is_correct
                                    ? 'text-green-500'
                                    : 'text-muted-foreground/30 hover:text-muted-foreground'
                            "
                        />
                    </button>
                    <Input
                        v-model="choice.choice_text"
                        :placeholder="`Choice ${choiceIndex + 1}`"
                        :aria-label="'Choice ' + (choiceIndex + 1)"
                        class="min-w-0 flex-1"
                        required
                    />
                    <InputError
                        :message="
                            props.errors[
                                `questions.${questionIndex}.choices.${choiceIndex}.choice_text`
                            ]
                        "
                    />
                    <Button
                        v-if="question.choices.length > 2"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="shrink-0 text-muted-foreground"
                        :aria-label="`Remove choice ${choiceIndex + 1} from question ${questionIndex + 1}`"
                        @click="removeChoice(questionIndex, choiceIndex)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-xs"
                    @click="addChoice(questionIndex)"
                >
                    <Plus class="mr-1 h-3.5 w-3.5" />
                    Add Choice
                </Button>
            </div>
        </div>
    </div>
</template>
