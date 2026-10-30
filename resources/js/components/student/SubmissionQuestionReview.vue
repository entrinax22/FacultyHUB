<script setup lang="ts">
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleX,
} from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Question = {
    id: string;
    question: string;
    points: number;
    choices: {
        id: string;
        choice_text: string;
        is_correct: boolean | null;
        is_selected: boolean;
    }[];
};

const props = defineProps<{
    questions: Question[];
    answersReleased: boolean;
}>();

const currentIndex = ref(0);

function selectedChoice(question: Question) {
    return question.choices.find((choice) => choice.is_selected);
}

function reviewState(question: Question) {
    const selected = selectedChoice(question);

    if (!props.answersReleased || !selected) {
        return selected ? 'answered' : 'unanswered';
    }

    return selected.is_correct === true
        ? 'correct'
        : selected.is_correct === false
          ? 'incorrect'
          : 'answered';
}

function stateLabel(question: Question) {
    const state = reviewState(question);

    if (!props.answersReleased) {
        return state === 'answered' ? 'Answered' : 'Not answered';
    }

    return state === 'correct'
        ? 'Correct'
        : state === 'incorrect'
          ? 'Incorrect'
          : state === 'answered'
            ? 'Answered'
            : 'Not answered';
}

async function showQuestion(index: number) {
    if (index < 0 || index >= props.questions.length) {
        return;
    }

    currentIndex.value = index;
    await nextTick();
    document
        .getElementById(`submission-question-${index + 1}`)
        ?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
            block: 'start',
        });
}
</script>

<template>
    <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <Card
            v-if="questions[currentIndex]"
            :id="`submission-question-${currentIndex + 1}`"
            tabindex="-1"
            :aria-labelledby="`submission-question-title-${currentIndex + 1}`"
            class="order-2 min-w-0 scroll-mt-6 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:order-1"
        >
            <CardHeader
                class="flex flex-row items-start justify-between gap-3 px-4 sm:px-6"
            >
                <CardTitle
                    :id="`submission-question-title-${currentIndex + 1}`"
                    class="text-base leading-relaxed"
                    aria-live="polite"
                >
                    Question {{ currentIndex + 1 }} of {{ questions.length }}
                </CardTitle>
                <Badge variant="secondary" class="shrink-0">
                    {{ questions[currentIndex].points }} pts
                </Badge>
            </CardHeader>

            <CardContent class="space-y-5 px-4 sm:px-6">
                <p class="max-w-4xl text-sm leading-relaxed break-words">
                    {{ questions[currentIndex].question }}
                </p>

                <div class="space-y-2">
                    <div
                        v-for="choice in questions[currentIndex].choices"
                        :key="choice.id"
                        class="flex min-w-0 items-start gap-2 rounded-lg border px-3 py-2.5 text-sm"
                        :class="{
                            'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100':
                                props.answersReleased &&
                                choice.is_correct === true,
                            'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100':
                                props.answersReleased &&
                                choice.is_selected &&
                                choice.is_correct === false,
                            'border-primary/30 bg-primary/5 text-slate-900 dark:text-slate-100':
                                !props.answersReleased && choice.is_selected,
                            'border-muted':
                                props.answersReleased &&
                                choice.is_correct !== true &&
                                !(
                                    choice.is_selected &&
                                    choice.is_correct === false
                                ),
                        }"
                    >
                        <span
                            class="w-4 shrink-0 text-xs font-semibold"
                            aria-hidden="true"
                        >
                            {{ choice.is_selected ? 'â–º' : '' }}
                        </span>
                        <span class="min-w-0 flex-1 break-words">
                            {{ choice.choice_text }}
                        </span>
                        <span
                            v-if="choice.is_selected"
                            class="shrink-0 text-xs font-medium"
                        >
                            Your answer
                        </span>
                        <span
                            v-if="
                                props.answersReleased &&
                                choice.is_correct === true
                            "
                            class="shrink-0 text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            Correct
                        </span>
                        <span
                            v-else-if="
                                props.answersReleased &&
                                choice.is_selected &&
                                choice.is_correct === false
                            "
                            class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400"
                        >
                            Incorrect
                        </span>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between gap-3 border-t pt-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentIndex === 0"
                        @click="showQuestion(currentIndex - 1)"
                    >
                        <ChevronLeft class="mr-1 size-4" />
                        Previous
                    </Button>
                    <span
                        class="text-sm text-muted-foreground"
                        aria-hidden="true"
                    >
                        {{ currentIndex + 1 }} / {{ questions.length }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentIndex === questions.length - 1"
                        @click="showQuestion(currentIndex + 1)"
                    >
                        Next
                        <ChevronRight class="ml-1 size-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card
            class="order-1 min-w-0 lg:sticky lg:top-4 lg:order-2 lg:self-start"
        >
            <CardHeader class="px-4 pb-3 sm:px-5">
                <CardTitle class="text-sm">Questions</CardTitle>
                <p class="text-xs text-muted-foreground" aria-live="polite">
                    {{ currentIndex + 1 }} of {{ questions.length }} selected
                </p>
            </CardHeader>
            <CardContent class="space-y-3 px-4 sm:px-5">
                <nav aria-label="Question navigator">
                    <div
                        class="grid max-h-64 grid-cols-5 gap-2 overflow-y-auto pr-1 sm:grid-cols-8 lg:grid-cols-4"
                    >
                        <Button
                            v-for="(question, index) in questions"
                            :key="question.id"
                            type="button"
                            variant="outline"
                            class="relative h-10 w-full min-w-0 p-0"
                            :class="{
                                'border-emerald-700 bg-emerald-700 text-white ring-2 ring-primary ring-offset-2 dark:border-emerald-300 dark:bg-emerald-300 dark:text-emerald-950':
                                    currentIndex === index,
                                'border-green-500 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200':
                                    props.answersReleased &&
                                    currentIndex !== index &&
                                    reviewState(question) === 'correct',
                                'border-red-500 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200':
                                    props.answersReleased &&
                                    currentIndex !== index &&
                                    reviewState(question) === 'incorrect',
                                'border-primary/40 bg-primary/5':
                                    !props.answersReleased &&
                                    reviewState(question) === 'answered',
                            }"
                            :aria-current="
                                currentIndex === index ? 'step' : undefined
                            "
                            :aria-label="`Question ${index + 1}, ${stateLabel(question)}${currentIndex === index ? ', selected' : ''}`"
                            @click="showQuestion(index)"
                        >
                            {{ index + 1 }}
                            <CheckCircle2
                                v-if="
                                    props.answersReleased &&
                                    reviewState(question) === 'correct'
                                "
                                class="absolute -top-1 -right-1 size-4 rounded-full bg-card text-green-600"
                                aria-hidden="true"
                            />
                            <CircleX
                                v-else-if="
                                    props.answersReleased &&
                                    reviewState(question) === 'incorrect'
                                "
                                class="absolute -top-1 -right-1 size-4 rounded-full bg-card text-red-600"
                                aria-hidden="true"
                            />
                            <span
                                v-else-if="
                                    !props.answersReleased &&
                                    reviewState(question) === 'answered'
                                "
                                class="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-primary"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                </nav>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    {{
                        props.answersReleased
                            ? 'Correctness is shown because the answer key has been released.'
                            : 'Markers show answered questions only. Correctness is hidden until answers are released.'
                    }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
