<script setup lang="ts">
import { CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type AssignmentQuestion = {
    id?: string;
    question: string;
    points: number;
    choices: {
        id?: string;
        choice_text: string;
        is_correct: boolean;
    }[];
};

const props = defineProps<{
    questions: AssignmentQuestion[];
}>();

const currentIndex = ref(0);

async function showQuestion(index: number) {
    if (index < 0 || index >= props.questions.length) {
        return;
    }

    currentIndex.value = index;
    await nextTick();
    document
        .getElementById(`assignment-question-${index + 1}`)
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
    <section class="space-y-4" aria-labelledby="assignment-questions-heading">
        <div class="flex items-center justify-between">
            <div>
                <h2
                    id="assignment-questions-heading"
                    class="text-lg font-semibold"
                >
                    Questions
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ questions.length }}
                    question{{ questions.length === 1 ? '' : 's' }}
                </p>
            </div>
        </div>

        <p
            v-if="questions.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            No questions have been added.
        </p>

        <div
            v-else
            class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]"
        >
            <Card
                :id="`assignment-question-${currentIndex + 1}`"
                tabindex="-1"
                :aria-labelledby="`assignment-question-title-${currentIndex + 1}`"
                class="order-2 min-w-0 scroll-mt-6 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:order-1"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between gap-3 px-4 sm:px-6"
                >
                    <CardTitle
                        :id="`assignment-question-title-${currentIndex + 1}`"
                        class="text-base leading-relaxed"
                        aria-live="polite"
                    >
                        Question {{ currentIndex + 1 }} of
                        {{ questions.length }}
                    </CardTitle>
                    <Badge variant="outline" class="shrink-0">
                        {{ questions[currentIndex].points }}
                        point{{
                            questions[currentIndex].points === 1 ? '' : 's'
                        }}
                    </Badge>
                </CardHeader>

                <CardContent class="space-y-5 px-4 sm:px-6">
                    <p
                        class="max-w-4xl text-sm leading-relaxed font-medium break-words whitespace-pre-wrap"
                    >
                        {{ questions[currentIndex].question }}
                    </p>

                    <ol class="space-y-2" aria-label="Answer choices">
                        <li
                            v-for="(choice, choiceIndex) in questions[
                                currentIndex
                            ].choices"
                            :key="choice.id ?? choiceIndex"
                            class="flex min-w-0 items-start gap-3 rounded-lg border px-3 py-2.5 text-sm"
                            :class="
                                choice.is_correct
                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/30'
                                    : ''
                            "
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs"
                            >
                                {{ String.fromCharCode(65 + choiceIndex) }}
                            </span>
                            <span class="min-w-0 flex-1 break-words">
                                {{ choice.choice_text }}
                            </span>
                            <span
                                v-if="choice.is_correct"
                                class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400"
                            >
                                <CheckCircle2
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Correct answer
                            </span>
                        </li>
                    </ol>

                    <div
                        class="flex items-center justify-between gap-3 border-t pt-4"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="currentIndex === 0"
                            aria-label="Previous question"
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
                            aria-label="Next question"
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
                    <CardTitle class="text-sm">Question navigator</CardTitle>
                    <p class="text-xs text-muted-foreground" aria-live="polite">
                        Question {{ currentIndex + 1 }} of
                        {{ questions.length }} selected
                    </p>
                </CardHeader>
                <CardContent class="px-4 sm:px-5">
                    <nav aria-label="Assignment question navigator">
                        <div
                            class="grid max-h-64 grid-cols-5 gap-2 overflow-y-auto pr-1 sm:grid-cols-8 lg:grid-cols-4"
                        >
                            <Button
                                v-for="(question, index) in questions"
                                :key="question.id ?? index"
                                type="button"
                                variant="outline"
                                class="h-10 w-full min-w-0 p-0"
                                :class="
                                    currentIndex === index
                                        ? 'border-primary bg-primary text-primary-foreground ring-2 ring-primary ring-offset-2'
                                        : ''
                                "
                                :aria-current="
                                    currentIndex === index ? 'step' : undefined
                                "
                                :aria-label="`Go to question ${index + 1}${currentIndex === index ? ', selected' : ''}`"
                                @click="showQuestion(index)"
                            >
                                {{ index + 1 }}
                            </Button>
                        </div>
                    </nav>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
