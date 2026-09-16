<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Clock } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Choice = {
    id: number;
    choice_text: string;
};

type Question = {
    id: number;
    question: string;
    points: number;
    choices: Choice[];
};

type Existing = {
    id: number;
    content: string | null;
    answers: Record<string, number> | null;
    status: string;
};

type Assignment = {
    id: number;
    title: string;
    type: string;
    instructions: string;
    max_score: number;
    due_date: string | null;
    language: string | null;
    section: {
        id: number;
        name: string;
        subject: {
            code: string;
        };
    };
    questions: Question[];
};

type Exam = {
    duration_minutes: number | null;
    started_at: string | null;
    expires_at: string | null;
    terms_accepted_at: string | null;
    proctoring_enabled: boolean;
};

const props = defineProps<{
    assignment: Assignment;
    existing: Existing | null;
    exam: Exam | null;
}>();

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

// MCQ form
const mcqAnswers = ref<Record<number, number>>(
    props.assignment.type === 'mcq' && props.existing?.answers
        ? Object.fromEntries(
              Object.entries(props.existing.answers).map(([k, v]) => [
                  Number(k),
                  Number(v),
              ]),
          )
        : {},
);

const mcqForm = useForm({
    answers: mcqAnswers.value,
});

const remainingSeconds = ref(0);

const examStarted = computed(() =>
    Boolean(props.exam?.started_at && props.exam.expires_at),
);

const termsAccepted = ref(Boolean(props.exam?.terms_accepted_at));

const currentQuestionIndex = ref(0);

const currentQuestion = computed(
    () => props.assignment.questions[currentQuestionIndex.value],
);

const isLastQuestion = computed(
    () =>
        currentQuestionIndex.value ===
        props.assignment.questions.length - 1,
);

let timer: number | undefined;
let heartbeatTimer: number | undefined;
let lastResizeReport = 0;
let monitoringAttached = false;

const eventDeliveryError = ref(false);

const pendingEvents = ref<
    Array<{
        event_type: string;
        metadata: Record<string, number | string | boolean>;
    }>
>([]);

const allAnswered = computed(() =>
    props.assignment.questions.every(
        (q) => mcqAnswers.value[q.id] != null,
    ),
);

function submitEssayCode() {
    essayCodeForm.post(`/assignments/${props.assignment.id}/submit`);
}

function submitMcq() {
    mcqForm.answers = mcqAnswers.value;

    mcqForm.post(`/assignments/${props.assignment.id}/submit`);
}

const isPastDue = computed(
    () =>
        props.assignment.due_date &&
        new Date(props.assignment.due_date) < new Date(),
);

function startExam() {
    if (!termsAccepted.value) {
        return;
    }

    requestFullscreen();

    useForm({ terms_accepted: true }).post(
        `/assignments/${props.assignment.id}/start`,
    );
}

function previousQuestion() {
    currentQuestionIndex.value = Math.max(
        0,
        currentQuestionIndex.value - 1,
    );
}

function nextQuestion() {
    currentQuestionIndex.value = Math.min(
        props.assignment.questions.length - 1,
        currentQuestionIndex.value + 1,
    );
}

function updateRemainingTime() {
    if (!props.exam?.expires_at) {
        return;
    }

    remainingSeconds.value = Math.max(
        0,
        Math.floor(
            (new Date(props.exam.expires_at).getTime() - Date.now()) /
                1000,
        ),
    );
}

type MonitorEvent =
    | 'heartbeat'
    | 'tab_hidden'
    | 'tab_visible'
    | 'window_resized'
    | 'fullscreen_entered'
    | 'fullscreen_exited'
    | 'copy_detected'
    | 'paste_detected'
    | 'cut_detected'
    | 'context_menu_used'
    | 'print_screen_suspected'
    | 'camera_permission_denied';

function reportEvent(
    eventType: MonitorEvent,
    metadata: Record<string, number | string | boolean> = {},
) {
    if (
        !props.existing?.id ||
        !examStarted.value ||
        !props.exam?.proctoring_enabled
    ) {
        return;
    }

    pendingEvents.value.push({
        event_type: eventType,
        metadata,
    });

    void flushEvents();
}

async function flushEvents() {
    if (
        !props.existing?.id ||
        pendingEvents.value.length === 0
    ) {
        return;
    }

    const events = pendingEvents.value.splice(
        0,
        pendingEvents.value.length,
    );

    try {
        const response = await fetch(
            `/submissions/${props.existing.id}/proctoring-events`,
            {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ events }),
            },
        );

        if (!response.ok) {
            throw new Error(
                `Event request failed with HTTP ${response.status}`,
            );
        }

        eventDeliveryError.value = false;
    } catch {
        pendingEvents.value.unshift(...events);
        eventDeliveryError.value = true;
    }
}

function handleFullscreenChange() {
    reportEvent(
        document.fullscreenElement
            ? 'fullscreen_entered'
            : 'fullscreen_exited',
    );
}

function requestFullscreen() {
    if (
        !document.fullscreenElement &&
        document.documentElement.requestFullscreen
    ) {
        document.documentElement
            .requestFullscreen()
            .catch(() => undefined);
    }
}

function handleWindowBlur() {
    reportEvent('tab_hidden', {
        source: 'window_blur',
    });
}

function handleWindowFocus() {
    reportEvent('tab_visible', {
        source: 'window_focus',
    });
}

function handlePageHide() {
    reportEvent('tab_hidden', {
        source: 'pagehide',
    });

    void flushEvents();
}

function handleVisibilityChange() {
    reportEvent(
        document.hidden ? 'tab_hidden' : 'tab_visible',
    );
}

function handleResize() {
    const now = Date.now();

    if (now - lastResizeReport < 1000) {
        return;
    }

    lastResizeReport = now;

    reportEvent('window_resized', {
        width: window.innerWidth,
        height: window.innerHeight,
    });
}

function handleCopy() {
    reportEvent('copy_detected', {
        source: 'clipboard',
    });
}

function handlePaste() {
    reportEvent('paste_detected', {
        source: 'clipboard',
    });
}

function handleCut() {
    reportEvent('cut_detected', {
        source: 'clipboard',
    });
}

function handleContextMenu(event: MouseEvent) {
    reportEvent('context_menu_used', {
        x: event.clientX,
        y: event.clientY,
    });
}

function handleKeyDown(event: KeyboardEvent) {
    if (event.key === 'PrintScreen') {
        reportEvent('print_screen_suspected', {
            source: 'keyboard',
        });
    }
}

function formatRemainingTime() {
    return `${String(
        Math.floor(remainingSeconds.value / 60),
    ).padStart(2, '0')}:${String(
        remainingSeconds.value % 60,
    ).padStart(2, '0')}`;
}

function attachMonitoring() {
    if (
        monitoringAttached ||
        !examStarted.value ||
        !props.exam?.proctoring_enabled
    ) {
        return;
    }

    monitoringAttached = true;

    updateRemainingTime();

    timer = window.setInterval(
        updateRemainingTime,
        1000,
    );

    heartbeatTimer = window.setInterval(
        () =>
            reportEvent('heartbeat', {
                visible: !document.hidden,
                fullscreen: Boolean(
                    document.fullscreenElement,
                ),
            }),
        15000,
    );

    document.addEventListener(
        'visibilitychange',
        handleVisibilityChange,
    );

    document.addEventListener(
        'fullscreenchange',
        handleFullscreenChange,
    );

    document.addEventListener('copy', handleCopy);
    document.addEventListener('paste', handlePaste);
    document.addEventListener('cut', handleCut);
    document.addEventListener(
        'contextmenu',
        handleContextMenu,
    );
    document.addEventListener('keydown', handleKeyDown);

    window.addEventListener('resize', handleResize);
    window.addEventListener('blur', handleWindowBlur);
    window.addEventListener('focus', handleWindowFocus);
    window.addEventListener('pagehide', handlePageHide);

    reportEvent('heartbeat', {
        visible: !document.hidden,
        fullscreen: Boolean(document.fullscreenElement),
    });
}

function detachMonitoring() {
    if (!monitoringAttached) {
        return;
    }

    monitoringAttached = false;

    if (timer) {
        window.clearInterval(timer);
        timer = undefined;
    }

    if (heartbeatTimer) {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = undefined;
    }

    document.removeEventListener(
        'visibilitychange',
        handleVisibilityChange,
    );

    document.removeEventListener(
        'fullscreenchange',
        handleFullscreenChange,
    );

    document.removeEventListener('copy', handleCopy);
    document.removeEventListener('paste', handlePaste);
    document.removeEventListener('cut', handleCut);

    document.removeEventListener(
        'contextmenu',
        handleContextMenu,
    );

    document.removeEventListener('keydown', handleKeyDown);

    window.removeEventListener('resize', handleResize);
    window.removeEventListener('blur', handleWindowBlur);
    window.removeEventListener('focus', handleWindowFocus);
    window.removeEventListener('pagehide', handlePageHide);

    void flushEvents();
}

watch(
    [
        examStarted,
        () => props.existing?.id,
        () => props.exam?.proctoring_enabled,
    ],
    ([started]) => {
        if (started) {
            attachMonitoring();
        } else {
            detachMonitoring();
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (examStarted.value) {
        updateRemainingTime();
    }
});

onBeforeUnmount(() => {
    detachMonitoring();
});
</script>

<template>
    <Head :title="`Submit — ${assignment.title}`" />

    <div
        class="flex min-h-full w-full min-w-0 flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- Header -->
        <div
            class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start"
        >
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 shrink-0 self-start"
            >
                <Link
                    :href="`/my-sections/${assignment.section.id}/assignments`"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span class="sr-only">Back</span>
                </Link>
            </Button>

            <div class="min-w-0 flex-1">
                <h1
                    class="break-words text-lg font-semibold sm:text-xl"
                >
                    {{ assignment.title }}
                </h1>

                <div
                    class="mt-1 flex min-w-0 flex-col gap-1 text-xs text-muted-foreground sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-2 sm:text-sm"
                >
                    <span class="break-words">
                        {{ assignment.section.subject.code }}
                        · Max: {{ assignment.max_score }} pts
                    </span>

                    <span
                        v-if="assignment.due_date"
                        class="flex min-w-0 items-start gap-1"
                        :class="
                            isPastDue
                                ? 'text-red-600'
                                : ''
                        "
                    >
                        <Clock
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        />

                        <span class="break-words">
                            Due
                            {{
                                new Date(
                                    assignment.due_date,
                                ).toLocaleString()
                            }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div
            class="rounded-xl border bg-muted/20 p-4 sm:p-5"
        >
            <p
                class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground"
            >
                Instructions
            </p>

            <p
                class="whitespace-pre-wrap break-words text-sm leading-relaxed"
            >
                {{ assignment.instructions }}
            </p>
        </div>

        <!-- Timed Exam -->
        <div
            v-if="exam"
            class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950 sm:p-5"
        >
            <div class="min-w-0">
                <p class="font-semibold">
                    Timed exam
                </p>

                <p class="mt-1 text-xs leading-relaxed sm:text-sm">
                    Accept the terms before questions are revealed.
                    Tab changes and window resizing are recorded for
                    faculty review.
                </p>
            </div>

            <!-- Accept Terms -->
            <div
                v-if="!examStarted"
                class="space-y-4 rounded-lg border border-amber-300 bg-white/70 p-3 sm:p-4"
            >
                <label
                    class="flex items-start gap-3 text-sm"
                >
                    <input
                        v-model="termsAccepted"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
                    />

                    <span class="leading-relaxed">
                        I understand that this attempt is timed and
                        that tab changes and window resizing may be
                        recorded for faculty review.
                    </span>
                </label>

                <Button
                    type="button"
                    class="w-full sm:w-auto"
                    :disabled="!termsAccepted"
                    @click="startExam"
                >
                    Accept Terms and Start Exam
                </Button>
            </div>

            <!-- Active Exam -->
            <div
                v-else
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">
                        Exam attempt active
                    </p>

                    <p
                        v-if="exam.proctoring_enabled"
                        class="mt-1 text-xs leading-relaxed text-amber-800"
                    >
                        Monitoring active: tab and window activity
                        is being recorded.
                    </p>

                    <p
                        v-if="eventDeliveryError"
                        class="mt-1 text-xs font-medium leading-relaxed text-red-700"
                    >
                        Monitoring connection interrupted. Keep this
                        page open and notify your instructor.
                    </p>
                </div>

                <div
                    class="shrink-0 rounded-lg border border-amber-200 bg-white/50 px-4 py-3 text-center sm:min-w-[130px]"
                >
                    <p
                        class="text-[10px] font-semibold uppercase tracking-wide sm:text-xs"
                    >
                        Time remaining
                    </p>

                    <p
                        class="mt-0.5 font-mono text-2xl font-bold"
                        :class="
                            remainingSeconds < 60
                                ? 'text-red-700'
                                : ''
                        "
                    >
                        {{ formatRemainingTime() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Past Due Warning -->
        <div
            v-if="isPastDue && !existing"
            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-relaxed text-red-700"
        >
            This assignment is past its due date and can no longer
            be submitted.
        </div>

        <!-- Already Approved -->
        <div
            v-else-if="existing?.status === 'approved'"
            class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-relaxed text-green-700"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>
                    Your submission has been graded and approved.
                    You can no longer edit it.
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full shrink-0 sm:w-auto"
                    as-child
                >
                    <Link
                        :href="`/submissions/${existing.id}`"
                    >
                        View Result
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ESSAY FORM -->
        <form
            v-else-if="
                assignment.type === 'essay' &&
                (!exam || examStarted)
            "
            class="space-y-4"
            @submit.prevent="submitEssayCode"
        >
            <div class="grid gap-1.5">
                <label class="text-sm font-medium">
                    Your Essay
                </label>

                <textarea
                    v-model="essayCodeForm.content"
                    rows="16"
                    required
                    minlength="10"
                    placeholder="Write your essay here…"
                    class="min-h-[300px] w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:min-h-[350px]"
                ></textarea>

                <InputError
                    :message="essayCodeForm.errors.content"
                />
            </div>

            <div
                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center"
            >
                <Button
                    type="submit"
                    class="w-full sm:w-auto"
                    :disabled="essayCodeForm.processing"
                >
                    {{
                        existing
                            ? 'Update Submission'
                            : 'Submit Essay'
                    }}
                </Button>

                <p
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    Your essay will be graded by AI and reviewed by
                    your faculty.
                </p>
            </div>
        </form>

        <!-- CODE FORM -->
        <form
            v-else-if="
                assignment.type === 'code' &&
                (!exam || examStarted)
            "
            class="space-y-4"
            @submit.prevent="submitEssayCode"
        >
            <div class="grid gap-1.5">
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <label class="text-sm font-medium">
                        Your Code
                    </label>

                    <Badge
                        variant="outline"
                        class="shrink-0 text-xs"
                    >
                        {{ assignment.language ?? 'any' }}
                    </Badge>
                </div>

                <textarea
                    v-model="essayCodeForm.content"
                    rows="20"
                    required
                    placeholder="# Write your code here…"
                    spellcheck="false"
                    class="min-h-[350px] w-full resize-y rounded-md border border-input bg-zinc-950 px-3 py-2 font-mono text-xs leading-relaxed text-green-400 shadow-sm placeholder:text-zinc-600 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:min-h-[450px] sm:text-sm"
                ></textarea>

                <InputError
                    :message="essayCodeForm.errors.content"
                />
            </div>

            <div
                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center"
            >
                <Button
                    type="submit"
                    class="w-full sm:w-auto"
                    :disabled="essayCodeForm.processing"
                >
                    {{
                        existing
                            ? 'Update Submission'
                            : 'Submit Code'
                    }}
                </Button>

                <p
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    Your code will be evaluated by AI and reviewed
                    by your faculty.
                </p>
            </div>
        </form>

        <!-- MCQ FORM -->
        <div
            v-else-if="
                assignment.type === 'mcq' &&
                (!exam || examStarted)
            "
            class="min-w-0 space-y-5"
        >
            <div
                v-if="currentQuestion"
                class="min-w-0 space-y-4 rounded-xl border p-4 sm:p-5"
            >
                <!-- Question Header -->
                <div
                    class="flex flex-col gap-1 border-b pb-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Question
                        {{ currentQuestionIndex + 1 }}
                        of
                        {{ assignment.questions.length }}
                    </p>

                    <span
                        class="text-xs text-muted-foreground"
                    >
                        {{ currentQuestion.points }}
                        point{{
                            currentQuestion.points !== 1
                                ? 's'
                                : ''
                        }}
                    </span>
                </div>

                <!-- Question -->
                <p
                    class="break-words text-sm font-medium leading-relaxed sm:text-base"
                >
                    {{ currentQuestion.question }}
                </p>

                <!-- Choices -->
                <div class="space-y-2">
                    <label
                        v-for="c in currentQuestion.choices"
                        :key="c.id"
                        class="flex min-w-0 cursor-pointer items-start gap-3 rounded-lg border px-3 py-3 text-slate-900 transition-colors dark:text-slate-100 sm:px-4"
                        :class="
                            mcqAnswers[
                                currentQuestion.id
                            ] === c.id
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted/40'
                        "
                    >
                        <input
                            type="radio"
                            :name="`q_${currentQuestion.id}`"
                            :value="c.id"
                            v-model="
                                mcqAnswers[
                                    currentQuestion.id
                                ]
                            "
                            class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
                        />

                        <span
                            class="min-w-0 break-words text-sm leading-relaxed"
                        >
                            {{ c.choice_text }}
                        </span>
                    </label>
                </div>

                <!-- Navigation -->
                <div
                    class="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        :disabled="currentQuestionIndex === 0"
                        @click="previousQuestion"
                    >
                        Previous
                    </Button>

                    <Button
                        v-if="!isLastQuestion"
                        type="button"
                        class="w-full sm:w-auto"
                        @click="nextQuestion"
                    >
                        Next
                    </Button>

                    <Button
                        v-else
                        type="button"
                        class="w-full sm:w-auto"
                        :disabled="
                            !allAnswered ||
                            mcqForm.processing
                        "
                        @click="submitMcq"
                    >
                        {{
                            existing
                                ? 'Update Answers'
                                : 'Submit Quiz'
                        }}
                    </Button>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No questions are available for this assignment.
            </div>

            <div
                v-if="!allAnswered"
                class="rounded-lg bg-muted/30 px-3 py-2 text-xs leading-relaxed text-muted-foreground"
            >
                Answer every question before submitting.
            </div>
        </div>
    </div>
</template>