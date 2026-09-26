<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    Clock,
    Code2,
    FileText,
    Flag,
    Maximize,
    ShieldCheck,
    Timer,
} from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    showApiError,
    showApiToast,
} from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Choice = {
    id: string;
    choice_text: string;
};

type Question = {
    id: string;
    question: string;
    points: number;
    choices: Choice[];
};

type Existing = {
    id: string;
    content: string | null;
    answers: Record<string, string | null> | null;
    status: string;
};

type Assignment = {
    id: string;
    title: string;
    type: string;
    instructions: string;
    max_score: number;
    due_date: string | null;
    language: string | null;
    section: {
        id: string;
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

type PendingEvent = {
    event_type: MonitorEvent;
    metadata: Record<
        string,
        number | string | boolean
    >;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    assignment: Assignment;
    existing: Existing | null;
    exam: Exam | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'Submit Assignment',
                href: '#',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Submission State
|--------------------------------------------------------------------------
*/

const essayContent = ref(
    props.existing?.content ?? '',
);

const mcqAnswers = ref<Record<string, string>>(
    props.assignment.type === 'mcq' &&
        props.existing?.answers
        ? Object.fromEntries(
              Object.entries(
                  props.existing.answers,
              ).filter(
                  (
                      entry,
                  ): entry is [
                      string,
                      string,
                  ] =>
                      entry[1] !== null,
              ),
          )
        : {},
);

const submitting = ref(false);
const startingExam = ref(false);

/*
|--------------------------------------------------------------------------
| Exam State
|--------------------------------------------------------------------------
*/

const examStartedAt = ref(
    props.exam?.started_at ?? null,
);

const examExpiresAt = ref(
    props.exam?.expires_at ?? null,
);

const termsAcceptedAt = ref(
    props.exam?.terms_accepted_at ?? null,
);

const termsAccepted = ref(
    Boolean(termsAcceptedAt.value),
);

const remainingSeconds = ref(0);

const examStarted = computed(() =>
    Boolean(
        examStartedAt.value &&
            examExpiresAt.value,
    ),
);

const examExpired = computed(
    () =>
        examStarted.value &&
        remainingSeconds.value <= 0,
);

/*
|--------------------------------------------------------------------------
| Submission ID
|--------------------------------------------------------------------------
*/

const submissionId = ref<string | null>(
    props.existing?.id ?? null,
);

/*
|--------------------------------------------------------------------------
| MCQ Navigation
|--------------------------------------------------------------------------
*/

const currentQuestionIndex = ref(0);

const currentQuestion = computed(
    () =>
        props.assignment.questions[
            currentQuestionIndex.value
        ],
);

const isLastQuestion = computed(
    () =>
        currentQuestionIndex.value ===
        props.assignment.questions.length - 1,
);

const allAnswered = computed(() =>
    props.assignment.questions.every(
        (question) =>
            mcqAnswers.value[question.id] != null,
    ),
);

/*
|--------------------------------------------------------------------------
| Due Date
|--------------------------------------------------------------------------
*/

const isPastDue = computed(
    () =>
        Boolean(
            props.assignment.due_date &&
                new Date(
                    props.assignment.due_date,
                ) < new Date(),
        ),
);

/*
|--------------------------------------------------------------------------
| Timer
|--------------------------------------------------------------------------
*/

let timer: number | undefined;

function updateRemainingTime() {
    if (!examExpiresAt.value) {
        remainingSeconds.value = 0;
        return;
    }

    remainingSeconds.value = Math.max(
        0,
        Math.floor(
            (
                new Date(
                    examExpiresAt.value,
                ).getTime() -
                Date.now()
            ) / 1000,
        ),
    );
}

function startTimer() {
    if (timer) {
        return;
    }

    updateRemainingTime();

    timer = window.setInterval(
        updateRemainingTime,
        1000,
    );
}

function stopTimer() {
    if (!timer) {
        return;
    }

    window.clearInterval(timer);
    timer = undefined;
}

function formatRemainingTime() {
    const minutes = Math.floor(
        remainingSeconds.value / 60,
    );

    const seconds =
        remainingSeconds.value % 60;

    return `${String(minutes).padStart(
        2,
        '0',
    )}:${String(seconds).padStart(2, '0')}`;
}

/*
|--------------------------------------------------------------------------
| Assignment Submission
|--------------------------------------------------------------------------
*/

async function submitEssayCode() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;

    try {
        const response = await axios.post(
            `/assignments/${props.assignment.id}/submit`,
            {
                content: essayContent.value,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        showApiToast(response);

        const encryptedSubmissionId =
            response.data?.data?.submission_id ??
            response.data?.submission_id;

        if (encryptedSubmissionId) {
            window.location.href =
                `/submissions/${encryptedSubmissionId}`;

            return;
        }

        window.location.reload();
    } catch (error) {
        showApiError(error);
    } finally {
        submitting.value = false;
    }
}

async function submitMcq() {
    if (
        submitting.value ||
        !allAnswered.value
    ) {
        return;
    }

    submitting.value = true;

    try {
        const response = await axios.post(
            `/assignments/${props.assignment.id}/submit`,
            {
                answers: {
                    ...mcqAnswers.value,
                },
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        showApiToast(response);

        const encryptedSubmissionId =
            response.data?.data?.submission_id ??
            response.data?.submission_id;

        if (encryptedSubmissionId) {
            window.location.href =
                `/submissions/${encryptedSubmissionId}`;

            return;
        }

        window.location.reload();
    } catch (error) {
        showApiError(error);
    } finally {
        submitting.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Start Exam
|--------------------------------------------------------------------------
*/

async function startExam() {
    if (
        !termsAccepted.value ||
        startingExam.value
    ) {
        return;
    }

    startingExam.value = true;

    requestFullscreen();

    try {
        const response = await axios.post(
            `/assignments/${props.assignment.id}/start`,
            {
                terms_accepted: true,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        showApiToast(response);

        if (
            props.exam?.proctoring_enabled
        ) {
            await checkCameraPermission();
        }

        window.location.reload();
    } catch (error) {
        showApiError(error);
    } finally {
        startingExam.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Camera Permission
|--------------------------------------------------------------------------
*/

async function checkCameraPermission() {
    if (
        !navigator.mediaDevices ||
        !navigator.mediaDevices.getUserMedia
    ) {
        return;
    }

    let stream:
        | MediaStream
        | undefined;

    try {
        stream =
            await navigator.mediaDevices.getUserMedia(
                {
                    video: true,
                    audio: false,
                },
            );

        stream
            .getTracks()
            .forEach((track) => track.stop());
    } catch {
        if (
            submissionId.value &&
            examStarted.value
        ) {
            reportEvent(
                'camera_permission_denied',
                {
                    source: 'getUserMedia',
                },
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| MCQ Navigation
|--------------------------------------------------------------------------
*/

function previousQuestion() {
    currentQuestionIndex.value =
        Math.max(
            0,
            currentQuestionIndex.value - 1,
        );
}

function nextQuestion() {
    currentQuestionIndex.value =
        Math.min(
            props.assignment.questions.length -
                1,
            currentQuestionIndex.value + 1,
        );
}

/*
|--------------------------------------------------------------------------
| Proctoring State
|--------------------------------------------------------------------------
*/

let heartbeatTimer:
    | number
    | undefined;

let flushTimer:
    | number
    | undefined;

let lastResizeReport = 0;

let monitoringAttached = false;

let flushingEvents = false;

const eventDeliveryError = ref(false);

const pendingEvents = ref<
    PendingEvent[]
>([]);

/*
|--------------------------------------------------------------------------
| Monitoring Helpers
|--------------------------------------------------------------------------
*/

const monitoringActive = computed(
    () =>
        monitoringAttached &&
        examStarted.value &&
        Boolean(
            props.exam?.proctoring_enabled,
        ),
);

function getMonitoringMetadata() {
    return {
        visible: !document.hidden,
        fullscreen: Boolean(
            document.fullscreenElement,
        ),
        timestamp: new Date().toISOString(),
    };
}

/*
|--------------------------------------------------------------------------
| Queue Event
|--------------------------------------------------------------------------
*/

function reportEvent(
    eventType: MonitorEvent,
    metadata: Record<
        string,
        number | string | boolean
    > = {},
) {
    if (
        !submissionId.value ||
        !examStarted.value ||
        !props.exam?.proctoring_enabled
    ) {
        return;
    }

    pendingEvents.value.push({
        event_type: eventType,
        metadata: {
            ...metadata,
            ...getMonitoringMetadata(),
        },
    });

    void flushEvents();
}

/*
|--------------------------------------------------------------------------
| Flush Monitoring Events
|--------------------------------------------------------------------------
*/

async function flushEvents() {
    if (
        flushingEvents ||
        !submissionId.value ||
        pendingEvents.value.length === 0
    ) {
        return;
    }

    flushingEvents = true;

    try {
        while (
            submissionId.value &&
            pendingEvents.value.length > 0
        ) {
            const events =
                pendingEvents.value.splice(
                    0,
                    pendingEvents.value.length,
                );

            try {
                const response =
                    await axios.post(
                        `/submissions/${submissionId.value}/proctoring-events`,
                        {
                            events,
                        },
                        {
                            headers: {
                                Accept:
                                    'application/json',
                            },
                            timeout: 10000,
                        },
                    );

                if (
                    response.data?.success ===
                    false
                ) {
                    throw new Error(
                        response.data?.message ??
                            'Failed to send monitoring events.',
                    );
                }

                eventDeliveryError.value =
                    false;
            } catch (error) {
                pendingEvents.value.unshift(
                    ...events,
                );

                eventDeliveryError.value =
                    true;

                console.error(
                    'PROCTORING EVENT ERROR:',
                    error,
                );

                break;
            }
        }
    } finally {
        flushingEvents = false;
    }
}

/*
|--------------------------------------------------------------------------
| Retry Monitoring Queue
|--------------------------------------------------------------------------
*/

function startFlushRetry() {
    if (flushTimer) {
        return;
    }

    flushTimer = window.setInterval(
        () => {
            if (
                pendingEvents.value.length >
                0
            ) {
                void flushEvents();
            }
        },
        5000,
    );
}

function stopFlushRetry() {
    if (!flushTimer) {
        return;
    }

    window.clearInterval(flushTimer);
    flushTimer = undefined;
}

/*
|--------------------------------------------------------------------------
| Fullscreen
|--------------------------------------------------------------------------
*/

function handleFullscreenChange() {
    if (
        document.fullscreenElement
    ) {
        reportEvent(
            'fullscreen_entered',
            {
                source: 'fullscreenchange',
            },
        );

        return;
    }

    reportEvent(
        'fullscreen_exited',
        {
            source: 'fullscreenchange',
        },
    );
}

function requestFullscreen() {
    if (
        document.fullscreenElement ||
        !document.documentElement.requestFullscreen
    ) {
        return;
    }

    document.documentElement
        .requestFullscreen()
        .catch(() => {});
}

/*
|--------------------------------------------------------------------------
| Window / Tab Monitoring
|--------------------------------------------------------------------------
*/

function handleWindowBlur() {
    reportEvent(
        'tab_hidden',
        {
            source: 'window_blur',
        },
    );
}

function handleWindowFocus() {
    reportEvent(
        'tab_visible',
        {
            source: 'window_focus',
        },
    );
}

function handlePageHide() {
    reportEvent(
        'tab_hidden',
        {
            source: 'pagehide',
        },
    );

    void flushEvents();
}

function handleVisibilityChange() {
    if (document.hidden) {
        reportEvent(
            'tab_hidden',
            {
                source: 'visibilitychange',
            },
        );

        return;
    }

    reportEvent(
        'tab_visible',
        {
            source: 'visibilitychange',
        },
    );
}

function handleResize() {
    const now = Date.now();

    if (
        now - lastResizeReport <
        1000
    ) {
        return;
    }

    lastResizeReport = now;

    reportEvent(
        'window_resized',
        {
            width: window.innerWidth,
            height: window.innerHeight,
        },
    );
}

/*
|--------------------------------------------------------------------------
| Clipboard Monitoring
|--------------------------------------------------------------------------
*/

function handleCopy() {
    reportEvent(
        'copy_detected',
        {
            source: 'clipboard',
        },
    );
}

function handlePaste() {
    reportEvent(
        'paste_detected',
        {
            source: 'clipboard',
        },
    );
}

function handleCut() {
    reportEvent(
        'cut_detected',
        {
            source: 'clipboard',
        },
    );
}

function handleContextMenu(
    event: MouseEvent,
) {
    reportEvent(
        'context_menu_used',
        {
            x: event.clientX,
            y: event.clientY,
        },
    );
}

function handleKeyDown(
    event: KeyboardEvent,
) {
    if (
        event.key === 'PrintScreen'
    ) {
        reportEvent(
            'print_screen_suspected',
            {
                source: 'keyboard',
            },
        );
    }
}

/*
|--------------------------------------------------------------------------
| Heartbeat
|--------------------------------------------------------------------------
*/

function sendHeartbeat() {
    if (
        !monitoringActive.value
    ) {
        return;
    }

    reportEvent(
        'heartbeat',
        {
            visible: !document.hidden,
            fullscreen: Boolean(
                document.fullscreenElement,
            ),
        },
    );
}

function startHeartbeat() {
    if (heartbeatTimer) {
        return;
    }

    sendHeartbeat();

    heartbeatTimer =
        window.setInterval(
            sendHeartbeat,
            15000,
        );
}

function stopHeartbeat() {
    if (!heartbeatTimer) {
        return;
    }

    window.clearInterval(
        heartbeatTimer,
    );

    heartbeatTimer = undefined;
}

/*
|--------------------------------------------------------------------------
| Monitoring Lifecycle
|--------------------------------------------------------------------------
*/

function attachMonitoring() {
    if (
        monitoringAttached ||
        !examStarted.value ||
        !props.exam?.proctoring_enabled ||
        !submissionId.value
    ) {
        return;
    }

    monitoringAttached = true;

    startFlushRetry();
    startHeartbeat();

    document.addEventListener(
        'visibilitychange',
        handleVisibilityChange,
    );

    document.addEventListener(
        'fullscreenchange',
        handleFullscreenChange,
    );

    document.addEventListener(
        'copy',
        handleCopy,
    );

    document.addEventListener(
        'paste',
        handlePaste,
    );

    document.addEventListener(
        'cut',
        handleCut,
    );

    document.addEventListener(
        'contextmenu',
        handleContextMenu,
    );

    document.addEventListener(
        'keydown',
        handleKeyDown,
    );

    window.addEventListener(
        'resize',
        handleResize,
    );

    window.addEventListener(
        'blur',
        handleWindowBlur,
    );

    window.addEventListener(
        'focus',
        handleWindowFocus,
    );

    window.addEventListener(
        'pagehide',
        handlePageHide,
    );

    reportEvent(
        'heartbeat',
        {
            source: 'monitoring_attached',
            visible: !document.hidden,
            fullscreen: Boolean(
                document.fullscreenElement,
            ),
        },
    );
}

function detachMonitoring() {
    if (!monitoringAttached) {
        stopHeartbeat();
        stopFlushRetry();
        return;
    }

    monitoringAttached = false;

    stopHeartbeat();

    document.removeEventListener(
        'visibilitychange',
        handleVisibilityChange,
    );

    document.removeEventListener(
        'fullscreenchange',
        handleFullscreenChange,
    );

    document.removeEventListener(
        'copy',
        handleCopy,
    );

    document.removeEventListener(
        'paste',
        handlePaste,
    );

    document.removeEventListener(
        'cut',
        handleCut,
    );

    document.removeEventListener(
        'contextmenu',
        handleContextMenu,
    );

    document.removeEventListener(
        'keydown',
        handleKeyDown,
    );

    window.removeEventListener(
        'resize',
        handleResize,
    );

    window.removeEventListener(
        'blur',
        handleWindowBlur,
    );

    window.removeEventListener(
        'focus',
        handleWindowFocus,
    );

    window.removeEventListener(
        'pagehide',
        handlePageHide,
    );

    void flushEvents();

    stopFlushRetry();
}

/*
|--------------------------------------------------------------------------
| Watch Exam State
|--------------------------------------------------------------------------
*/

watch(
    examStarted,
    (started) => {
        if (started) {
            startTimer();
        } else {
            stopTimer();
        }
    },
    {
        immediate: true,
    },
);

/*
|--------------------------------------------------------------------------
| Watch Monitoring State
|--------------------------------------------------------------------------
*/

watch(
    [
        examStarted,
        () =>
            props.exam
                ?.proctoring_enabled,
        submissionId,
    ],
    ([started]) => {
        if (
            started &&
            props.exam?.proctoring_enabled &&
            submissionId.value
        ) {
            attachMonitoring();
        } else {
            detachMonitoring();
        }
    },
    {
        immediate: true,
    },
);

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

onBeforeUnmount(() => {
    stopTimer();
    detachMonitoring();
});
</script>

<template>
    <Head
        :title="`Submit — ${assignment.title}`"
    />

    <div
        class="min-h-full w-full min-w-0 flex-1 bg-muted/20"
    >
        <div
            class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-3 sm:gap-6 sm:p-5 lg:p-8"
        >
            <!-- ==========================================================
                 HEADER
            =========================================================== -->
            <div
                class="rounded-2xl border bg-card px-4 py-4 shadow-sm sm:px-6"
            >
                <div
                    class="flex min-w-0 items-start gap-3 sm:gap-4"
                >
                    <Button
                        variant="outline"
                        size="icon"
                        as-child
                        class="mt-0.5 h-9 w-9 shrink-0"
                    >
                        <Link
                            :href="`/my-sections/${assignment.section.id}/assignments`"
                        >
                            <ArrowLeft
                                class="h-4 w-4"
                            />

                            <span
                                class="sr-only"
                            >
                                Back
                            </span>
                        </Link>
                    </Button>

                    <div
                        class="min-w-0 flex-1"
                    >
                        <div
                            class="flex flex-wrap items-center gap-2"
                        >
                            <h1
                                class="break-words text-lg font-semibold tracking-tight sm:text-xl"
                            >
                                {{
                                    assignment.title
                                }}
                            </h1>

                            <Badge
                                variant="secondary"
                                class="shrink-0"
                            >
                                {{
                                    assignment.type ===
                                    'mcq'
                                        ? 'Quiz'
                                        : assignment.type ===
                                            'code'
                                          ? 'Programming'
                                          : 'Essay'
                                }}
                            </Badge>
                        </div>

                        <div
                            class="mt-2 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground sm:text-sm"
                        >
                            <span
                                class="flex items-center gap-1.5"
                            >
                                <BookOpen
                                    class="h-3.5 w-3.5 shrink-0"
                                />

                                {{
                                    assignment.section
                                        .subject
                                        .code
                                }}
                            </span>

                            <span
                                class="hidden text-muted-foreground/50 sm:inline"
                            >
                                •
                            </span>

                            <span
                                class="break-words"
                            >
                                {{
                                    assignment.section
                                        .name
                                }}
                            </span>

                            <span
                                class="hidden text-muted-foreground/50 sm:inline"
                            >
                                •
                            </span>

                            <span>
                                Max
                                {{
                                    assignment.max_score
                                }}
                                pts
                            </span>

                            <span
                                v-if="
                                    assignment.due_date
                                "
                                class="flex items-center gap-1.5"
                                :class="
                                    isPastDue
                                        ? 'font-medium text-red-600'
                                        : ''
                                "
                            >
                                <Clock
                                    class="h-3.5 w-3.5 shrink-0"
                                />

                                Due
                                {{
                                    new Date(
                                        assignment.due_date,
                                    ).toLocaleString()
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================================================
                 INSTRUCTIONS
            =========================================================== -->
            <section
                class="rounded-2xl border bg-card shadow-sm"
            >
                <div
                    class="flex items-start gap-3 border-b px-4 py-4 sm:px-6"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <FileText
                            class="h-4 w-4 text-primary"
                        />
                    </div>

                    <div
                        class="min-w-0"
                    >
                        <h2
                            class="text-sm font-semibold"
                        >
                            Instructions
                        </h2>

                        <p
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            Read carefully before
                            starting your submission.
                        </p>
                    </div>
                </div>

                <div
                    class="px-4 py-5 sm:px-6 sm:py-6"
                >
                    <p
                        class="whitespace-pre-wrap break-words text-sm leading-7 text-foreground/90"
                    >
                        {{
                            assignment.instructions ||
                            'No instructions provided.'
                        }}
                    </p>
                </div>
            </section>

            <!-- ==========================================================
                 TIMED EXAM
            =========================================================== -->
            <section
                v-if="exam"
                class="overflow-hidden rounded-2xl border border-amber-200 bg-card shadow-sm dark:border-amber-900"
            >
                <!-- Exam Header -->
                <div
                    class="border-b border-amber-200 bg-amber-50/70 px-4 py-4 dark:border-amber-900 dark:bg-amber-950/20 sm:px-6"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="flex min-w-0 items-start gap-3"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40"
                            >
                                <Timer
                                    class="h-5 w-5 text-amber-700 dark:text-amber-400"
                                />
                            </div>

                            <div
                                class="min-w-0"
                            >
                                <h2
                                    class="font-semibold text-amber-950 dark:text-amber-100"
                                >
                                    Timed Exam
                                </h2>

                                <p
                                    class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-300 sm:text-sm"
                                >
                                    Your exam attempt is
                                    timed. Activity may be
                                    recorded for faculty
                                    review.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="examStarted"
                            class="flex shrink-0 items-center gap-3 rounded-xl border border-amber-200 bg-white px-4 py-2.5 shadow-sm dark:border-amber-800 dark:bg-amber-950/40"
                        >
                            <Clock
                                class="h-4 w-4 text-amber-700 dark:text-amber-400"
                            />

                            <div>
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Time Remaining
                                </p>

                                <p
                                    class="font-mono text-xl font-bold tabular-nums"
                                    :class="
                                        remainingSeconds <
                                        60
                                            ? 'text-red-600'
                                            : ''
                                    "
                                >
                                    {{
                                        formatRemainingTime()
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accept Terms -->
                <div
                    v-if="!examStarted"
                    class="space-y-5 px-4 py-5 sm:px-6 sm:py-6"
                >
                    <div
                        class="rounded-xl border bg-muted/30 p-4"
                    >
                        <div
                            class="flex items-start gap-3"
                        >
                            <ShieldCheck
                                class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                            />

                            <div
                                class="min-w-0"
                            >
                                <p
                                    class="text-sm font-medium"
                                >
                                    Before you begin
                                </p>

                                <p
                                    class="mt-1 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                >
                                    Accept the terms to
                                    reveal the exam
                                    questions. Make sure
                                    you are ready to
                                    complete the attempt
                                    within the allotted
                                    time.
                                </p>
                            </div>
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/30"
                    >
                        <input
                            v-model="
                                termsAccepted
                            "
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
                        />

                        <span
                            class="text-sm leading-relaxed"
                        >
                            I understand that this
                            attempt is timed and that
                            tab changes, window
                            activity, fullscreen
                            changes, and clipboard
                            activity may be recorded
                            for faculty review.
                        </span>
                    </label>

                    <Button
                        type="button"
                        class="w-full sm:w-auto"
                        :disabled="
                            !termsAccepted ||
                            startingExam
                        "
                        @click="startExam"
                    >
                        {{
                            startingExam
                                ? 'Starting Exam...'
                                : 'Accept Terms and Start Exam'
                        }}
                    </Button>
                </div>

                <!-- Active Exam -->
                <div
                    v-else
                    class="space-y-3 px-4 py-4 sm:px-6"
                >
                    <div
                        class="flex items-start gap-3 rounded-xl bg-muted/30 p-4"
                    >
                        <CheckCircle2
                            class="mt-0.5 h-5 w-5 shrink-0 text-green-600"
                        />

                        <div
                            class="min-w-0"
                        >
                            <p
                                class="text-sm font-medium"
                            >
                                Exam attempt active
                            </p>

                            <p
                                v-if="
                                    exam.proctoring_enabled
                                "
                                class="mt-1 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{
                                    monitoringActive
                                        ? 'Monitoring active: tab, window, fullscreen, clipboard, resize, and heartbeat activity is being recorded.'
                                        : 'Monitoring is initializing...'
                                }}
                            </p>

                            <p
                                v-if="
                                    eventDeliveryError
                                "
                                class="mt-2 text-xs font-medium leading-relaxed text-red-600"
                            >
                                Monitoring connection
                                interrupted. Events
                                will be retried
                                automatically. Keep this
                                page open and notify your
                                instructor if the problem
                                continues.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ==========================================================
                 PAST DUE
            =========================================================== -->
            <div
                v-if="
                    isPastDue &&
                    !existing
                "
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/20 dark:text-red-400"
            >
                <Clock
                    class="mt-0.5 h-4 w-4 shrink-0"
                />

                <p
                    class="leading-relaxed"
                >
                    This assignment is past its
                    due date and can no longer be
                    submitted.
                </p>
            </div>

            <!-- ==========================================================
                 ALREADY APPROVED
            =========================================================== -->
            <div
                v-else-if="
                    existing?.status ===
                    'approved'
                "
                class="rounded-2xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/20"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex items-start gap-3"
                    >
                        <CheckCircle2
                            class="mt-0.5 h-5 w-5 shrink-0 text-green-600"
                        />

                        <p
                            class="text-sm leading-relaxed text-green-700 dark:text-green-400"
                        >
                            Your submission has been
                            graded and approved. You
                            can no longer edit it.
                        </p>
                    </div>

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

            <!-- ==========================================================
                 ESSAY
            =========================================================== -->
            <form
                v-else-if="
                    assignment.type ===
                        'essay' &&
                    (!exam ||
                        examStarted)
                "
                class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                @submit.prevent="
                    submitEssayCode
                "
            >
                <div
                    class="border-b px-4 py-4 sm:px-6"
                >
                    <div
                        class="flex items-start gap-3"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                        >
                            <FileText
                                class="h-4 w-4 text-primary"
                            />
                        </div>

                        <div>
                            <h2
                                class="text-sm font-semibold"
                            >
                                Your Essay
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                Write your response
                                below.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="p-4 sm:p-6"
                >
                    <textarea
                        v-model="
                            essayContent
                        "
                        rows="16"
                        required
                        minlength="10"
                        placeholder="Write your essay here…"
                        class="min-h-[300px] w-full resize-y rounded-xl border bg-background px-4 py-3 text-sm leading-7 shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:min-h-[400px]"
                    ></textarea>
                </div>

                <div
                    class="flex flex-col gap-3 border-t bg-muted/20 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <p
                        class="text-xs leading-relaxed text-muted-foreground"
                    >
                        Your essay will be graded by
                        AI and reviewed by your
                        faculty.
                    </p>

                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="
                            submitting
                        "
                    >
                        {{
                            submitting
                                ? 'Submitting...'
                                : existing
                                  ? 'Update Submission'
                                  : 'Submit Essay'
                        }}
                    </Button>
                </div>
            </form>

            <!-- ==========================================================
                 CODE
            =========================================================== -->
            <form
                v-else-if="
                    assignment.type ===
                        'code' &&
                    (!exam ||
                        examStarted)
                "
                class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                @submit.prevent="
                    submitEssayCode
                "
            >
                <div
                    class="border-b px-4 py-4 sm:px-6"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div
                            class="flex items-start gap-3"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                            >
                                <Code2
                                    class="h-4 w-4 text-primary"
                                />
                            </div>

                            <div>
                                <h2
                                    class="text-sm font-semibold"
                                >
                                    Your Code
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    Enter your solution
                                    using the required
                                    language.
                                </p>
                            </div>
                        </div>

                        <Badge
                            variant="outline"
                            class="shrink-0 font-mono text-xs"
                        >
                            {{
                                assignment.language ??
                                'any'
                            }}
                        </Badge>
                    </div>
                </div>

                <div
                    class="bg-zinc-950 p-2 sm:p-3"
                >
                    <textarea
                        v-model="
                            essayContent
                        "
                        rows="20"
                        required
                        placeholder="# Write your code here…"
                        spellcheck="false"
                        class="min-h-[350px] w-full resize-y rounded-lg border border-zinc-800 bg-zinc-950 px-4 py-3 font-mono text-xs leading-6 text-green-400 outline-none placeholder:text-zinc-600 focus:border-zinc-700 focus:ring-2 focus:ring-primary/20 sm:min-h-[500px] sm:text-sm"
                    ></textarea>
                </div>

                <div
                    class="flex flex-col gap-3 border-t bg-muted/20 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <p
                        class="text-xs leading-relaxed text-muted-foreground"
                    >
                        Your code will be evaluated by
                        AI and reviewed by your faculty.
                    </p>

                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="
                            submitting
                        "
                    >
                        {{
                            submitting
                                ? 'Submitting...'
                                : existing
                                  ? 'Update Submission'
                                  : 'Submit Code'
                        }}
                    </Button>
                </div>
            </form>

            <!-- ==========================================================
                 MCQ
            =========================================================== -->
            <div
                v-else-if="
                    assignment.type ===
                        'mcq' &&
                    (!exam ||
                        examStarted)
                "
                class="min-w-0 space-y-4"
            >
                <!-- Question Progress -->
                <div
                    class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="min-w-0"
                        >
                            <div
                                class="flex items-center gap-2"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                                >
                                    <Flag
                                        class="h-4 w-4 text-primary"
                                    />
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                                    >
                                        Quiz Progress
                                    </p>

                                    <p
                                        class="text-sm font-medium"
                                    >
                                        Question
                                        {{
                                            currentQuestionIndex +
                                            1
                                        }}
                                        of
                                        {{
                                            assignment
                                                .questions
                                                .length
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Badge
                            variant="secondary"
                            class="w-fit shrink-0"
                        >
                            {{
                                Object.keys(
                                    mcqAnswers,
                                ).length
                            }}
                            /
                            {{
                                assignment.questions
                                    .length
                            }}
                            answered
                        </Badge>
                    </div>

                    <div
                        class="mt-4 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-300"
                            :style="{
                                width: `${
                                    assignment
                                        .questions
                                        .length
                                        ? ((currentQuestionIndex +
                                              1) /
                                              assignment
                                                  .questions
                                                  .length) *
                                          100
                                        : 0
                                }%`,
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Question -->
                <div
                    v-if="currentQuestion"
                    class="min-w-0 overflow-hidden rounded-2xl border bg-card shadow-sm"
                >
                    <div
                        class="border-b px-4 py-4 sm:px-6"
                    >
                        <div
                            class="flex items-center justify-between gap-4"
                        >
                            <div
                                class="flex min-w-0 items-center gap-2"
                            >
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                                >
                                    {{
                                        currentQuestionIndex +
                                        1
                                    }}
                                </span>

                                <span
                                    class="text-sm font-semibold"
                                >
                                    Question
                                    {{
                                        currentQuestionIndex +
                                        1
                                    }}
                                </span>
                            </div>

                            <Badge
                                variant="outline"
                                class="shrink-0"
                            >
                                {{
                                    currentQuestion.points
                                }}
                                point{{
                                    currentQuestion.points !==
                                    1
                                        ? 's'
                                        : ''
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div
                        class="space-y-6 p-4 sm:p-6"
                    >
                        <p
                            class="break-words text-base font-medium leading-7 sm:text-lg"
                        >
                            {{
                                currentQuestion.question
                            }}
                        </p>

                        <div
                            class="space-y-2.5"
                        >
                            <label
                                v-for="
                                    choice in currentQuestion.choices
                                "
                                :key="
                                    choice.id
                                "
                                class="group flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all sm:p-4"
                                :class="
                                    mcqAnswers[
                                        currentQuestion
                                            .id
                                    ] ===
                                    choice.id
                                        ? 'border-primary bg-primary/5 shadow-sm'
                                        : 'hover:border-primary/40 hover:bg-muted/30'
                                "
                            >
                                <input
                                    v-model="
                                        mcqAnswers[
                                            currentQuestion
                                                .id
                                        ]
                                    "
                                    type="radio"
                                    :name="`q_${currentQuestion.id}`"
                                    :value="
                                        choice.id
                                    "
                                    class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
                                />

                                <span
                                    class="flex min-w-0 flex-1 items-start gap-3"
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-medium text-muted-foreground"
                                    >
                                        {{
                                            String.fromCharCode(
                                                65 +
                                                    currentQuestion
                                                        .choices
                                                        .indexOf(
                                                            choice,
                                                        ),
                                            )
                                        }}
                                    </span>

                                    <span
                                        class="min-w-0 break-words pt-0.5 text-sm leading-relaxed"
                                    >
                                        {{
                                            choice.choice_text
                                        }}
                                    </span>
                                </span>

                                <CheckCircle2
                                    v-if="
                                        mcqAnswers[
                                            currentQuestion
                                                .id
                                        ] ===
                                        choice.id
                                    "
                                    class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                />
                            </label>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div
                        class="flex flex-col-reverse gap-3 border-t bg-muted/20 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:w-auto"
                            :disabled="
                                currentQuestionIndex ===
                                0
                            "
                            @click="
                                previousQuestion
                            "
                        >
                            Previous
                        </Button>

                        <Button
                            v-if="
                                !isLastQuestion
                            "
                            type="button"
                            class="w-full sm:w-auto"
                            @click="
                                nextQuestion
                            "
                        >
                            Next
                        </Button>

                        <Button
                            v-else
                            type="button"
                            class="w-full sm:w-auto"
                            :disabled="
                                !allAnswered ||
                                submitting
                            "
                            @click="
                                submitMcq
                            "
                        >
                            {{
                                submitting
                                    ? 'Submitting...'
                                    : existing
                                      ? 'Update Answers'
                                      : 'Submit Quiz'
                            }}
                        </Button>
                    </div>
                </div>

                <!-- Incomplete Answers -->
                <div
                    v-if="!allAnswered"
                    class="flex items-start gap-2.5 rounded-xl border bg-muted/30 px-4 py-3 text-xs text-muted-foreground"
                >
                    <Flag
                        class="mt-0.5 h-4 w-4 shrink-0"
                    />

                    <p
                        class="leading-relaxed"
                    >
                        Answer every question before
                        submitting.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
