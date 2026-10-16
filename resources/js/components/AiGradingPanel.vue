<script setup lang="ts">
import axios from 'axios';
import { Bot, Loader2, Play, RefreshCw, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
};

type ApiResponse = { success: boolean; message: string; data: ProcessingStatus };

const props = defineProps<{
    assignmentId: string;
    submissionId?: string;
    mode: 'individual' | 'batch';
}>();
const emit = defineEmits<{ changed: [] }>();

const status = ref<ProcessingStatus | null>(null);
const loading = ref(true);
const busy = ref(false);
const endpoint = (action: string) => `/assignments/${props.assignmentId}/ai-grading/${action}`;
const startLabel = computed(() => props.mode === 'individual' ? 'Grade with AI' : 'Start AI Batch');
const isCancelled = computed(() => status.value?.error_code === 'cancelled');
const canStart = computed(() => !status.value || (status.value.status === 'completed') || isCancelled.value);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}

async function loadStatus(): Promise<void> {
    loading.value = true;

    try {
        const response = await axios.get<ApiResponse>(endpoint('status'));
        status.value = response.data.data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            status.value = null;

            return;
        }

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

async function action(name: 'start' | 'next' | 'retry' | 'cancel'): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const payload = name === 'start'
            ? { mode: props.mode, submission_id: props.mode === 'individual' ? props.submissionId : undefined }
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

onMounted(loadStatus);
</script>

<template>
    <section class="rounded-xl border bg-card p-4 sm:p-5" aria-live="polite">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <Bot class="h-4 w-4 text-primary" />
                    <h2 class="font-semibold">AI grading</h2>
                    <Badge v-if="status" variant="outline" class="capitalize">{{ status.status }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="status">
                        {{ status.completed_count }} of {{ status.total_submissions }} completed;
                        {{ status.pending_count }} pending and {{ status.failed_count }} failed.
                    </template>
                    <template v-else>
                        {{ mode === 'individual' ? 'Generate a faculty-reviewed suggestion for this submission.' : 'Start a bounded, resumable grading run for eligible submissions.' }}
                    </template>
                </p>
                <p v-if="status?.error_message" :class="status.status === 'failed' ? 'text-sm text-destructive' : 'text-sm text-amber-600'">
                    {{ status.error_message }}
                </p>
                <p v-if="status?.next_eligible_at" class="text-xs text-muted-foreground">
                    Next eligible time: {{ formatDate(status.next_eligible_at) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="canStart" size="sm" :disabled="busy || loading" @click="action('start')">
                    <Loader2 v-if="busy" class="mr-1.5 h-4 w-4 animate-spin" />
                    <Bot v-else class="mr-1.5 h-4 w-4" />{{ startLabel }}
                </Button>
                <Button v-if="status?.can_continue" size="sm" :disabled="busy" @click="action('next')">
                    <Play class="mr-1.5 h-4 w-4" />{{ status.completed_count ? 'Continue' : 'Process Next' }}
                </Button>
                <Button v-if="status?.can_retry" variant="outline" size="sm" :disabled="busy" @click="action('retry')">
                    <RotateCcw class="mr-1.5 h-4 w-4" />Retry failed
                </Button>
                <Button v-if="status?.can_cancel" variant="outline" size="sm" :disabled="busy" @click="action('cancel')">
                    <XCircle class="mr-1.5 h-4 w-4" />Cancel
                </Button>
                <Button variant="ghost" size="sm" :disabled="busy || loading" aria-label="Refresh AI grading status" @click="loadStatus">
                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                </Button>
            </div>
        </div>

        <div v-if="status" class="mt-4 space-y-2">
            <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="status.progress_percentage" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${status.progress_percentage}%` }" />
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
