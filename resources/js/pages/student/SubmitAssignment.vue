<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Clock } from 'lucide-vue-next';
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
|
| The ID remains encrypted on the frontend.
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

    /*
     * Fullscreen must be requested from the
     * user's click event.
     */
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

        /*
         * Camera permission is only checked if
         * proctoring is enabled.
         *
         * This is a permission check only.
         * We do not continuously record video here.
         */
        if (
            props.exam?.proctoring_enabled
        ) {
            await checkCameraPermission();
        }

        /*
         * Reload so the backend can return the
         * now-authorized exam questions and
         * submission state.
         */
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

        /*
         * We only check permission.
         * Stop the camera immediately.
         */
        stream
            .getTracks()
            .forEach((track) => track.stop());
    } catch {
        /*
         * The backend already supports this
         * monitoring event.
         *
         * The event cannot be sent through the
         * normal queue before the submission ID
         * is available, so this is handled after
         * the exam has started on subsequent
         * monitoring cycles.
         */
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

    /*
     * Start sending immediately.
     */
    void flushEvents();
}

/*
|--------------------------------------------------------------------------
| Flush Monitoring Events
|--------------------------------------------------------------------------
|
| Important:
| We keep draining the queue until there are
| no more events.
|
| This prevents an event from getting stuck
| when another event arrives while Axios is
| already sending the previous batch.
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
                /*
                 * Put the events back at the
                 * front of the queue.
                 */
                pendingEvents.value.unshift(
                    ...events,
                );

                eventDeliveryError.value =
                    true;

                console.error(
                    'PROCTORING EVENT ERROR:',
                    error,
                );

                /*
                 * Do not hammer the server if
                 * the connection is unavailable.
                 */
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
        .catch(() => {
            /*
             * Fullscreen can be rejected by
             * browser policy. Monitoring should
             * continue regardless.
             */
        });
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

    /*
     * Try to flush immediately.
     *
     * Note: browsers may terminate asynchronous
     * requests during pagehide, so this is a
     * best-effort operation.
     */
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

    /*
     * Prevent resize event flooding.
     */
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
    /*
     * PrintScreen is not guaranteed to be
     * detectable by browsers.
     *
     * When the browser exposes the key,
     * record it.
     */
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

    /*
     * Send one immediately.
     */
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

    /*
     * Start retry processing.
     */
    startFlushRetry();

    /*
     * Heartbeat.
     */
    startHeartbeat();

    /*
     * Visibility.
     */
    document.addEventListener(
        'visibilitychange',
        handleVisibilityChange,
    );

    /*
     * Fullscreen.
     */
    document.addEventListener(
        'fullscreenchange',
        handleFullscreenChange,
    );

    /*
     * Clipboard.
     */
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

    /*
     * Context menu.
     */
    document.addEventListener(
        'contextmenu',
        handleContextMenu,
    );

    /*
     * Keyboard.
     */
    document.addEventListener(
        'keydown',
        handleKeyDown,
    );

    /*
     * Window.
     */
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

    /*
     * Initial heartbeat.
     */
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

    /*
     * Remove document listeners.
     */
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

    /*
     * Remove window listeners.
     */
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

    /*
     * Try to deliver anything still
     * waiting.
     */
    void flushEvents();

    stopFlushRetry();
}

/*
|--------------------------------------------------------------------------
| Watch Exam State
|--------------------------------------------------------------------------
|
| IMPORTANT:
| immediate: true is required.
|
| After startExam(), the page reloads.
| The backend then returns an already-active
| exam. Therefore examStarted is already true
| when Vue mounts.
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
                    <ArrowLeft
                        class="h-4 w-4"
                    />

                    <span class="sr-only">
                        Back
                    </span>
                </Link>
            </Button>

            <div
                class="min-w-0 flex-1"
            >
                <h1
                    class="break-words text-lg font-semibold sm:text-xl"
                >
                    {{ assignment.title }}
                </h1>

                <div
                    class="mt-1 flex min-w-0 flex-col gap-1 text-xs text-muted-foreground sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-2 sm:text-sm"
                >
                    <span
                        class="break-words"
                    >
                        {{
                            assignment.section
                                .subject.code
                        }}
                        · Max:
                        {{
                            assignment.max_score
                        }}
                        pts
                    </span>

                    <span
                        v-if="
                            assignment.due_date
                        "
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

                        <span
                            class="break-words"
                        >
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
                {{
                    assignment.instructions
                }}
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

                <p
                    class="mt-1 text-xs leading-relaxed sm:text-sm"
                >
                    Accept the terms before questions
                    are revealed. Tab changes, window
                    resizing, fullscreen changes, and
                    clipboard activity are recorded for
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
                        v-model="
                            termsAccepted
                        "
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 accent-primary"
                    />

                    <span
                        class="leading-relaxed"
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
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
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
                        class="mt-1 text-xs leading-relaxed text-amber-800"
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
                        class="mt-1 text-xs font-medium leading-relaxed text-red-700"
                    >
                        Monitoring connection
                        interrupted. Events will be
                        retried automatically. Keep
                        this page open and notify your
                        instructor if the problem
                        continues.
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
                            remainingSeconds <
                            60
                                ? 'text-red-700'
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

        <!-- Past Due -->
        <div
            v-if="
                isPastDue &&
                !existing
            "
            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-relaxed text-red-700"
        >
            This assignment is past its due date and
            can no longer be submitted.
        </div>

        <!-- Already Approved -->
        <div
            v-else-if="
                existing?.status ===
                'approved'
            "
            class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm leading-relaxed text-green-700"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>
                    Your submission has been graded
                    and approved. You can no longer
                    edit it.
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

        <!-- ESSAY -->
        <form
            v-else-if="
                assignment.type ===
                    'essay' &&
                (!exam ||
                    examStarted)
            "
            class="space-y-4"
            @submit.prevent="
                submitEssayCode
            "
        >
            <div class="grid gap-1.5">
                <label
                    class="text-sm font-medium"
                >
                    Your Essay
                </label>

                <textarea
                    v-model="
                        essayContent
                    "
                    rows="16"
                    required
                    minlength="10"
                    placeholder="Write your essay here…"
                    class="min-h-[300px] w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-relaxed shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:min-h-[350px]"
                ></textarea>
            </div>

            <div
                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center"
            >
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

                <p
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    Your essay will be graded by AI
                    and reviewed by your faculty.
                </p>
            </div>
        </form>

        <!-- CODE -->
        <form
            v-else-if="
                assignment.type ===
                    'code' &&
                (!exam ||
                    examStarted)
            "
            class="space-y-4"
            @submit.prevent="
                submitEssayCode
            "
        >
            <div class="grid gap-1.5">
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <label
                        class="text-sm font-medium"
                    >
                        Your Code
                    </label>

                    <Badge
                        variant="outline"
                        class="shrink-0 text-xs"
                    >
                        {{
                            assignment.language ??
                            'any'
                        }}
                    </Badge>
                </div>

                <textarea
                    v-model="
                        essayContent
                    "
                    rows="20"
                    required
                    placeholder="# Write your code here…"
                    spellcheck="false"
                    class="min-h-[350px] w-full resize-y rounded-md border border-input bg-zinc-950 px-3 py-2 font-mono text-xs leading-relaxed text-green-400 shadow-sm placeholder:text-zinc-600 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:min-h-[450px] sm:text-sm"
                ></textarea>
            </div>

            <div
                class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center"
            >
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

                <p
                    class="text-xs leading-relaxed text-muted-foreground"
                >
                    Your code will be evaluated by
                    AI and reviewed by your faculty.
                </p>
            </div>
        </form>

        <!-- MCQ -->
        <div
            v-else-if="
                assignment.type ===
                    'mcq' &&
                (!exam ||
                    examStarted)
            "
            class="min-w-0 space-y-5"
        >
            <div
                v-if="currentQuestion"
                class="min-w-0 space-y-4 rounded-xl border p-4 sm:p-5"
            >
                <div
                    class="flex flex-col gap-1 border-b pb-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        Question
                        {{
                            currentQuestionIndex +
                            1
                        }}
                        of
                        {{
                            assignment.questions
                                .length
                        }}
                    </p>

                    <span
                        class="text-xs text-muted-foreground"
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
                    </span>
                </div>

                <p
                    class="break-words text-sm font-medium leading-relaxed sm:text-base"
                >
                    {{
                        currentQuestion.question
                    }}
                </p>

                <div
                    class="space-y-2"
                >
                    <label
                        v-for="
                            choice in currentQuestion.choices
                        "
                        :key="
                            choice.id
                        "
                        class="flex min-w-0 cursor-pointer items-start gap-3 rounded-lg border px-3 py-3 text-slate-900 transition-colors dark:text-slate-100 sm:px-4"
                        :class="
                            mcqAnswers[
                                currentQuestion
                                    .id
                            ] ===
                            choice.id
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted/40'
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
                            class="min-w-0 break-words text-sm leading-relaxed"
                        >
                            {{
                                choice.choice_text
                            }}
                        </span>
                    </label>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
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

            <div
                v-else
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No questions are available for
                this assignment.
            </div>

            <div
                v-if="!allAnswered"
                class="rounded-lg bg-muted/30 px-3 py-2 text-xs leading-relaxed text-muted-foreground"
            >
                Answer every question before
                submitting.
            </div>
        </div>
    </div>
</template>