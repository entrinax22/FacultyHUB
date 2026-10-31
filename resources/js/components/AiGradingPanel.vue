<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bot,
    Loader2,
    Play,
    RefreshCw,
    RotateCcw,
    XCircle,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { showApiError, showApiToast, showApiWarning } from '@/lib/flashToast';

type ProcessingStatus = {
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_submissions: number;
    pending_count: number;
    processing_count: number;
    completed_count: number;
    failed_count: number;
    failed_submissions: {
        items: {
            submission_id: string | null;
            student_name: string;
            status: 'failed' | 'outcome_unknown';
            reason: string;
            last_updated_at: string | null;
            retryable: boolean;
        }[];
        page: number;
        last_page: number;
        total: number;
    };
    progress_percentage: number;
    next_eligible_at: string | null;
    budget_reason?:
        | 'budget_minute_requests'
        | 'budget_daily_requests'
        | 'budget_daily_tokens'
        | null;
    budget_reset_at?: string | null;
    can_continue: boolean;
    can_retry: boolean;
    retryable_count: number;
    can_cancel: boolean;
    error_code: string | null;
    error_message: string | null;
    outcome?: 'completed' | 'deferred' | 'failed' | 'no_eligible_work';
    preparation_status?: string;
    is_queued_batch?: boolean;
    queued?: boolean;
    can_resume?: boolean;
    stalled?: boolean;
    outcome_unknown_count?: number;
    pending_unknown_count: number;
    recoverable_pending_count: number;
    can_recover_failed_run: boolean;
    monitoring_state:
        | 'preparing'
        | 'queued'
        | 'processing'
        | 'waiting_minute'
        | 'waiting_provider'
        | 'paused_budget'
        | 'stalled'
        | 'outcome_unknown'
        | 'completed'
        | 'failed'
        | 'pending';
    last_activity_at: string | null;
    next_attempt_at: string | null;
};

type ApiResponse = {
    success: boolean;
    message: string;
    data: ProcessingStatus;
};

const props = defineProps<{
    assignmentId: string;
    submissionId?: string;
    mode: 'individual' | 'batch';
}>();
const emit = defineEmits<{ changed: [] }>();

const status = ref<ProcessingStatus | null>(null);
const loading = ref(true);
const busy = ref(false);
const retryDialogOpen = ref(false);
const recoverDialogOpen = ref(false);
const resumeUnknownDialogOpen = ref(false);
const failedPage = ref(1);
const endpoint = (action: string) =>
    `/assignments/${props.assignmentId}/ai-grading/${action}`;
const startLabel = computed(() =>
    props.mode === 'individual' ? 'Grade with AI' : 'Start AI Batch',
);
const isCancelled = computed(() => status.value?.error_code === 'cancelled');
const canStart = computed(
    () =>
        !status.value ||
        status.value.status === 'completed' ||
        isCancelled.value,
);
const preparing = computed(
    () =>
        status.value?.is_queued_batch &&
        ['pending', 'processing'].includes(status.value.status) &&
        status.value.preparation_status !== 'prepared',
);
const monitoringLabels: Record<ProcessingStatus['monitoring_state'], string> = {
    preparing: 'Preparing',
    queued: 'Queued',
    processing: 'Processing',
    waiting_minute: 'Waiting for minute budget',
    waiting_provider: 'Waiting for provider retry',
    paused_budget: 'Paused by daily budget',
    stalled: 'Possibly stalled',
    outcome_unknown: 'Outcome unknown',
    completed: 'Completed',
    failed: 'Failed',
    pending: 'Pending',
};
let pollingTimer: number | undefined;
let statusRequestInFlight = false;
let statusVersion = 0;
let lastStalledPoll = 0;

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}

async function loadStatus(silent = false): Promise<void> {
    if (statusRequestInFlight) {
        return;
    }

    statusRequestInFlight = true;
    const requestVersion = statusVersion;

    if (!silent) {
        loading.value = true;
    }

    try {
        const response = await axios.get<ApiResponse>(endpoint('status'), {
            params: { failed_page: failedPage.value },
        });

        if (requestVersion !== statusVersion) {
            return;
        }

        const previousCompleted = status.value?.completed_count;
        status.value = response.data.data;
        failedPage.value = status.value.failed_submissions.page;

        if (
            previousCompleted !== undefined &&
            status.value.completed_count > previousCompleted
        ) {
            emit('changed');
        }
    } catch (error) {
        if (requestVersion !== statusVersion) {
            return;
        }

        if (axios.isAxiosError(error) && error.response?.status === 404) {
            status.value = null;

            return;
        }

        if (!silent) {
            showApiError(error);
        }
    } finally {
        statusRequestInFlight = false;
        loading.value = false;
    }
}

async function changeFailedPage(page: number): Promise<void> {
    if (loading.value || busy.value || statusRequestInFlight) {
        return;
    }

    failedPage.value = page;
    await loadStatus();
}

async function action(
    name: 'start' | 'next' | 'retry' | 'recover' | 'cancel',
): Promise<void> {
    if (busy.value) {
        return;
    }

    if (
        name === 'retry' &&
        (!status.value?.can_retry || !status.value.retryable_count)
    ) {
        return;
    }

    if (name === 'recover' && !status.value?.can_recover_failed_run) {
        return;
    }

    busy.value = true;
    statusVersion++;

    try {
        const payload =
            name === 'start'
                ? {
                      mode: props.mode,
                      submission_id:
                          props.mode === 'individual'
                              ? props.submissionId
                              : undefined,
                  }
                : name === 'retry' && status.value?.outcome_unknown_count
                  ? { confirm_unknown: true }
                  : name === 'recover' && status.value?.pending_unknown_count
                    ? { confirm_unknown: true }
                    : name === 'next' &&
                        status.value?.is_queued_batch &&
                        status.value.pending_unknown_count
                      ? { confirm_unknown: true }
                      : undefined;
        const response = await axios.post<ApiResponse>(endpoint(name), payload);
        status.value = response.data.data;

        if (name === 'next' && !response.data.success) {
            showApiWarning(response);
        } else {
            showApiToast(response);
        }

        if (name === 'next' && response.data.data.outcome === 'completed') {
            emit('changed');
        }
    } catch (error) {
        showApiError(error);
    } finally {
        busy.value = false;
        await loadStatus();
    }
}

onMounted(() => {
    void loadStatus();
    pollingTimer = window.setInterval(() => {
        if (
            !busy.value &&
            document.visibilityState === 'visible' &&
            status.value?.is_queued_batch &&
            ['pending', 'processing'].includes(status.value.status) &&
            !status.value.can_resume &&
            !(
                status.value.monitoring_state === 'stalled' &&
                Date.now() - lastStalledPoll < 30000
            ) &&
            !(
                status.value.next_attempt_at &&
                Date.now() < Date.parse(status.value.next_attempt_at)
            ) &&
            !(
                status.value.error_code === 'budget_waiting' &&
                status.value.budget_reset_at &&
                Date.now() < Date.parse(status.value.budget_reset_at)
            )
        ) {
            if (status.value.monitoring_state === 'stalled') {
                lastStalledPoll = Date.now();
            }

            void loadStatus(true);
        }
    }, 5000);
});
onBeforeUnmount(() => window.clearInterval(pollingTimer));
</script>

<template>
    <section class="rounded-xl border bg-card p-4 sm:p-5" aria-live="polite">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Bot class="h-4 w-4 text-primary" />
                    <h2 class="font-semibold">AI grading</h2>
                    <Badge v-if="status" variant="outline">{{
                        monitoringLabels[status.monitoring_state]
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="preparing"
                        >Preparing eligible submissions for background
                        grading.</template
                    >
                    <template v-else-if="status">
                        {{ status.completed_count }} of
                        {{ status.total_submissions }} completed;
                        {{ status.pending_count }} pending and
                        {{ status.failed_count }} failed.
                    </template>
                    <template v-else>
                        {{
                            mode === 'individual'
                                ? 'Generate a faculty-reviewed suggestion for this submission.'
                                : 'Start a bounded, resumable grading run for eligible submissions.'
                        }}
                    </template>
                </p>
                <p
                    v-if="status?.error_message"
                    :class="
                        status.status === 'failed'
                            ? 'text-sm text-destructive'
                            : 'text-sm text-amber-600'
                    "
                >
                    {{ status.error_message }}
                </p>
                <p
                    v-if="
                        status?.next_eligible_at &&
                        !status.budget_reason &&
                        !status.next_attempt_at
                    "
                    class="text-xs text-muted-foreground"
                >
                    Next eligible time:
                    {{ formatDate(status.next_eligible_at) }}
                </p>
                <p
                    v-if="status?.next_attempt_at"
                    class="text-xs text-muted-foreground"
                >
                    Next scheduled attempt:
                    {{ formatDate(status.next_attempt_at) }}
                </p>
                <p
                    v-if="status?.last_activity_at"
                    class="text-xs text-muted-foreground"
                >
                    Last recorded activity:
                    {{ formatDate(status.last_activity_at) }}
                </p>
                <p
                    v-if="status?.budget_reason"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        status.budget_reason === 'budget_minute_requests'
                            ? 'Per-minute request limit reached.'
                            : status.budget_reason === 'budget_daily_requests'
                              ? 'Daily request limit reached.'
                              : 'Daily estimated input token limit reached.'
                    }}
                    {{
                        status.budget_reason === 'budget_minute_requests'
                            ? 'Expected continuation:'
                            : 'Budget window resets:'
                    }}
                    {{ formatDate(status.budget_reset_at ?? null) }}
                </p>
                <p
                    v-if="status?.can_resume"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    The batch can be resumed when ready.
                </p>
                <p
                    v-if="status?.monitoring_state === 'stalled'"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    No recent progress is visible. The service may be asleep or
                    the worker may be delayed; its availability is not known.
                </p>
                <p
                    v-else-if="
                        status?.monitoring_state === 'queued' ||
                        status?.monitoring_state === 'preparing'
                    "
                    class="text-xs text-muted-foreground"
                >
                    Background grading may pause while the service sleeps and
                    continue when it wakes.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="canStart"
                    size="sm"
                    :disabled="busy || loading"
                    @click="action('start')"
                >
                    <Loader2 v-if="busy" class="mr-1.5 h-4 w-4 animate-spin" />
                    <Bot v-else class="mr-1.5 h-4 w-4" />{{ startLabel }}
                </Button>
                <Button
                    v-if="
                        status?.is_queued_batch
                            ? status.can_resume
                            : status?.can_continue
                    "
                    size="sm"
                    :disabled="busy"
                    @click="
                        status?.is_queued_batch && status.pending_unknown_count
                            ? (resumeUnknownDialogOpen = true)
                            : action('next')
                    "
                >
                    <Play class="mr-1.5 h-4 w-4" />{{
                        status?.is_queued_batch
                            ? 'Resume batch'
                            : status?.completed_count
                              ? 'Continue'
                              : 'Process Next'
                    }}
                </Button>
                <Button
                    v-if="status?.can_recover_failed_run"
                    size="sm"
                    :disabled="busy || loading"
                    @click="recoverDialogOpen = true"
                >
                    <RotateCcw class="mr-1.5 h-4 w-4" />Resume failed run ({{
                        status.recoverable_pending_count
                    }}
                    pending)
                </Button>
                <Button
                    v-if="status && status.failed_count > 0"
                    variant="outline"
                    size="sm"
                    :disabled="
                        busy ||
                        loading ||
                        !status.can_retry ||
                        status.retryable_count === 0
                    "
                    @click="retryDialogOpen = true"
                >
                    <RotateCcw class="mr-1.5 h-4 w-4" />Retry eligible failures
                    ({{ status.retryable_count }})
                </Button>
                <Button
                    v-if="status?.can_cancel"
                    variant="outline"
                    size="sm"
                    :disabled="busy"
                    @click="action('cancel')"
                >
                    <XCircle class="mr-1.5 h-4 w-4" />Cancel
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="busy || loading"
                    aria-label="Refresh AI grading status"
                    @click="loadStatus"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': loading }"
                    />
                </Button>
            </div>
        </div>

        <div v-if="status && !preparing" class="mt-4 space-y-2">
            <div
                class="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="status.progress_percentage"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${status.progress_percentage}%` }"
                />
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                <span>Progress: {{ status.progress_percentage }}%</span>
                <span>Total: {{ status.total_submissions }}</span>
                <span>Pending: {{ status.pending_count }}</span>
                <span>Processing: {{ status.processing_count }}</span>
                <span>Failed: {{ status.failed_count }}</span>
            </div>
            <p
                v-if="status.pending_count > 0"
                class="text-xs text-muted-foreground"
            >
                Pending submissions are waiting for processing.
            </p>
        </div>
        <section
            v-if="status && status.failed_count > 0"
            class="mt-5 border-t pt-4"
            aria-labelledby="failed-submissions-heading"
        >
            <h3 id="failed-submissions-heading" class="font-semibold">
                Failed submissions
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Failed submissions may be eligible for retry. Outcome unknown
                requires manual review and confirmation. Review is separate from
                retrying all eligible failures in this run.
            </p>
            <ul class="mt-3 divide-y">
                <li
                    v-for="(failure, index) in status.failed_submissions.items"
                    :key="failure.submission_id ?? index"
                    class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1 text-sm">
                        <p class="font-medium">
                            {{ failure.student_name }}
                            <Badge variant="outline">{{
                                failure.status === 'outcome_unknown'
                                    ? 'Outcome unknown'
                                    : 'Failed'
                            }}</Badge>
                            <Badge v-if="failure.retryable" variant="outline"
                                >Retry available</Badge
                            >
                        </p>
                        <p class="text-muted-foreground">
                            {{ failure.reason }}
                        </p>
                        <p
                            v-if="failure.last_updated_at"
                            class="text-xs text-muted-foreground"
                        >
                            Last updated:
                            {{ formatDate(failure.last_updated_at) }}
                        </p>
                    </div>
                    <Button
                        v-if="failure.submission_id"
                        as-child
                        variant="outline"
                        size="sm"
                        ><Link
                            :href="`/submissions/${failure.submission_id}/grade`"
                            :aria-label="`Review submission for ${failure.student_name}`"
                            >Review</Link
                        ></Button
                    >
                </li>
            </ul>
            <nav
                v-if="status.failed_submissions.last_page > 1"
                aria-label="Failed submissions pages"
                class="mt-2 flex flex-wrap items-center gap-2 text-sm"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        loading || busy || status.failed_submissions.page <= 1
                    "
                    @click="
                        changeFailedPage(status.failed_submissions.page - 1)
                    "
                    >Previous</Button
                >
                <span
                    >Page {{ status.failed_submissions.page }} of
                    {{ status.failed_submissions.last_page }}</span
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        loading ||
                        busy ||
                        status.failed_submissions.page >=
                            status.failed_submissions.last_page
                    "
                    @click="
                        changeFailedPage(status.failed_submissions.page + 1)
                    "
                    >Next</Button
                >
            </nav>
        </section>
        <Dialog v-model:open="retryDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Retry eligible failures?</DialogTitle>
                    <DialogDescription>
                        This will retry
                        {{ status?.retryable_count ?? 0 }} eligible
                        {{
                            status?.retryable_count === 1
                                ? 'submission'
                                : 'submissions'
                        }}
                        in this run. Retrying may process multiple submissions
                        and incur additional AI usage.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="status?.outcome_unknown_count"
                    class="text-sm text-amber-700 dark:text-amber-300"
                >
                    Review all outcome-unknown submissions first. The previous
                    provider request may have completed despite the missing
                    response; retrying may repeat it.
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="busy"
                        @click="retryDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="
                            busy ||
                            loading ||
                            !status?.can_retry ||
                            !status?.retryable_count
                        "
                        @click="
                            retryDialogOpen = false;
                            action('retry');
                        "
                        >Confirm retry</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="recoverDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Resume this failed grading run?</DialogTitle>
                    <DialogDescription>
                        This resumes
                        {{ status?.recoverable_pending_count ?? 0 }} pending
                        submissions from the existing snapshots. It preserves
                        completed results and does not create a new run.
                        Processing may incur additional AI usage.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="status?.pending_unknown_count"
                    class="text-sm text-amber-700 dark:text-amber-300"
                >
                    {{ status.pending_unknown_count }} pending
                    {{
                        status.pending_unknown_count === 1
                            ? 'submission has'
                            : 'submissions have'
                    }}
                    an unresolved or historical unknown provider outcome. A
                    previous request may have completed even though its result
                    was not recorded. Confirming may repeat that request and
                    incur additional AI usage. An attempt count of zero does not
                    establish that no provider request occurred.
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="busy"
                        @click="recoverDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        :disabled="
                            busy || loading || !status?.can_recover_failed_run
                        "
                        @click="
                            recoverDialogOpen = false;
                            action('recover');
                        "
                        >Confirm resume</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="resumeUnknownDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Resume despite an unknown provider
                        outcome?</DialogTitle
                    >
                    <DialogDescription>
                        {{ status?.pending_unknown_count ?? 0 }} pending
                        {{
                            status?.pending_unknown_count === 1
                                ? 'submission has'
                                : 'submissions have'
                        }}
                        an unresolved provider outcome. The previous AI request
                        may have completed even though its response was not
                        recorded. Resuming can repeat that request and incur
                        additional AI usage.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="busy"
                        @click="resumeUnknownDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        :disabled="busy || loading || !status?.can_resume"
                        @click="
                            resumeUnknownDialogOpen = false;
                            action('next');
                        "
                    >
                        Confirm resume
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
