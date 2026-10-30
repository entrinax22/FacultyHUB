<script setup lang="ts">
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
import { showApiError, showApiToast, showApiWarning } from '@/lib/flashToast';

type ProcessingStatus = {
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_submissions: number;
    pending_count: number;
    processing_count: number;
    completed_count: number;
    failed_count: number;
    progress_percentage: number;
    next_eligible_at: string | null;
    can_continue: boolean;
    can_retry: boolean;
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
let pollingTimer: number | undefined;
let statusRequestInFlight = false;
let statusVersion = 0;

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
        const response = await axios.get<ApiResponse>(endpoint('status'));

        if (requestVersion !== statusVersion) {
            return;
        }

        const previousCompleted = status.value?.completed_count;
        status.value = response.data.data;

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

async function action(
    name: 'start' | 'next' | 'retry' | 'cancel',
): Promise<void> {
    if (busy.value) {
        return;
    }

    if (
        name === 'retry' &&
        status.value?.outcome_unknown_count &&
        !window.confirm(
            'Review the affected submissions first. The provider may already have processed the previous request. Retry anyway?',
        )
    ) {
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
            status.value?.is_queued_batch &&
            ['pending', 'processing'].includes(status.value.status)
        ) {
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
                    <Badge v-if="status" variant="outline" class="capitalize">{{
                        status.outcome_unknown_count
                            ? 'Outcome unknown'
                            : preparing
                              ? 'Preparing'
                              : status.status
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
                    v-if="status?.next_eligible_at"
                    class="text-xs text-muted-foreground"
                >
                    Next eligible time:
                    {{ formatDate(status.next_eligible_at) }}
                </p>
                <p
                    v-if="status?.can_resume"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    The batch is paused or its worker stopped. Resume when
                    ready; a sleeping service resumes after it wakes.
                </p>
                <p
                    v-else-if="status?.stalled"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    The worker has not finished this request. Wait for the
                    service to wake and check its status before retrying.
                </p>
                <p
                    v-else-if="status?.is_queued_batch && status.queued"
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
                    @click="action('next')"
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
                    v-if="status?.can_retry"
                    variant="outline"
                    size="sm"
                    :disabled="busy"
                    @click="action('retry')"
                >
                    <RotateCcw class="mr-1.5 h-4 w-4" />{{
                        status?.outcome_unknown_count
                            ? 'Review and retry'
                            : 'Retry failed'
                    }}
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
        </div>
    </section>
</template>
