<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, ArrowLeft, BarChart2, CheckCircle2, Play, RefreshCw, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { showApiError, showApiToast, showApiWarning } from '@/lib/flashToast';

type Student = { id: string; first_name: string; last_name: string; student_number: string };
type Report = { id: string; similarity_score: number; flagged: boolean; explanation: string | null; student_a: Student | null; student_b: Student | null };
type Assignment = { id: string; title: string; section: { id: string; name: string; subject: { code: string; name: string } | null } | null };
type ProcessingRun = { status: 'pending' | 'processing' | 'completed' | 'failed'; preparation_status: string; total_pairs: number; completed_pairs: number; failed_pairs: number; remaining_pairs: number; progress_percentage: number; error_code: string | null; next_eligible_at: string | null; published_at: string | null; can_continue: boolean; outcome?: 'completed' | 'deferred' | 'failed' };
type ApiResponse<T> = { success: boolean; message: string; data: T };

const props = defineProps<{ assignmentId: string }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Assignments', href: '/sections' }, { title: 'Plagiarism Report', href: '#' }] } });

const assignment = ref<Assignment | null>(null);
const reports = ref<Report[]>([]);
const processing = ref<ProcessingRun | null>(null);
const loading = ref(true);
const busy = ref(false);
const canStartNewAnalysis = computed(() => !processing.value || (processing.value.status === 'failed' && ['cancelled', 'provider_unavailable', 'provider_rate_limited', 'provider_request_failed'].includes(processing.value.error_code ?? '')));
const canRetry = computed(() => processing.value?.status === 'pending'
    && !processing.value.can_continue
    && processing.value.failed_pairs === 0
    && (!processing.value.next_eligible_at || new Date(processing.value.next_eligible_at) <= new Date()));
const canCancel = computed(() => ['pending', 'processing'].includes(processing.value?.status ?? ''));
const canPublish = computed(() => processing.value?.status === 'completed' && !processing.value.published_at);

function endpoint(action: string): string {
    return '/assignments/' + props.assignmentId + '/plagiarism/' + action;
}

function scoreColor(score: number): string {
    return score >= 70 ? 'text-red-600' : score >= 40 ? 'text-orange-500' : 'text-green-600';
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : 'Not scheduled';
}

async function loadReports(): Promise<void> {
    const response = await axios.get<ApiResponse<{ assignment: Assignment; reports: Report[] }>>(endpoint('data'));
    assignment.value = response.data.data.assignment;
    reports.value = response.data.data.reports;
}

async function loadStatus(): Promise<void> {
    try {
        const response = await axios.get<ApiResponse<ProcessingRun>>(endpoint('processing/status'));
        processing.value = response.data.data;
    } catch (error: any) {
        if (error.response?.status === 404) {
            processing.value = null;

            return;
        }

        throw error;
    }
}

async function refresh(): Promise<void> {
    loading.value = true;

    try {
        await Promise.all([loadReports(), loadStatus()]);
    } catch (error) {
        showApiError(error);
    } finally {
        loading.value = false;
    }
}

async function runAction(action: 'start' | 'next' | 'retry' | 'cancel' | 'publish'): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const response = await axios.post<ApiResponse<ProcessingRun>>(endpoint('processing/' + action));
        processing.value = response.data.data;

        if ((action === 'next' || action === 'retry') && response.data.data.outcome === 'deferred') {
            showApiWarning(response);
        } else {
            showApiToast(response);
        }

        if (action === 'publish') {
            await loadReports();
        }
    } catch (error) {
        showApiError(error);
    } finally {
        busy.value = false;

        if (action === 'next' || action === 'retry') {
            await refresh();
        }
    }
}

onMounted(refresh);
</script>

<template>
    <Head :title="'Plagiarism Report — ' + (assignment?.title ?? 'Assignment')" />
    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="assignment ? '/assignments/' + assignment.id : '/sections'"><ArrowLeft class="h-4 w-4" /></Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold">Plagiarism analysis</h1>
                    <p class="text-sm text-muted-foreground">{{ assignment?.title ?? 'Loading assignment…' }}<template v-if="assignment?.section?.subject"> · {{ assignment.section.subject.code }}</template></p>
                </div>
            </div>
            <Button variant="outline" size="sm" :disabled="loading || busy" @click="refresh"><RefreshCw class="mr-1.5 h-4 w-4" :class="{ 'animate-spin': loading }" />Refresh</Button>
        </div>

        <div class="rounded-xl border bg-card p-4 sm:p-5" aria-live="polite">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold">Pairwise processing</h2><Badge v-if="processing" variant="outline" class="capitalize">{{ processing.status }}</Badge><Badge v-if="processing?.published_at" variant="default">Published</Badge></div>
                    <p class="text-sm text-muted-foreground"><template v-if="processing">{{ processing.completed_pairs }} of {{ processing.total_pairs }} comparisons completed; {{ processing.remaining_pairs }} remaining.</template><template v-else>Start an analysis to create a bounded, resumable comparison run.</template></p>
                    <p v-if="processing?.error_code" :class="processing.status === 'failed' ? 'text-sm text-destructive' : 'text-sm text-amber-600'">{{ processing.status === 'failed' ? 'Processing failed' : 'Processing paused' }}: {{ processing.error_code }}.</p>
                    <p v-if="processing?.next_eligible_at" class="text-xs text-muted-foreground">Next eligible processing time: {{ formatDate(processing.next_eligible_at) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canStartNewAnalysis" size="sm" :disabled="busy" @click="runAction('start')"><BarChart2 class="mr-1.5 h-4 w-4" />{{ processing ? 'Start New Analysis' : 'Start analysis' }}</Button>
                    <Button v-if="processing?.can_continue" size="sm" :disabled="busy" @click="runAction('next')"><Play class="mr-1.5 h-4 w-4" />Process next pair</Button>
                    <Button v-if="canRetry" variant="outline" size="sm" :disabled="busy" @click="runAction('retry')"><RefreshCw class="mr-1.5 h-4 w-4" />Resume</Button>
                    <Button v-if="canCancel" variant="outline" size="sm" :disabled="busy" @click="runAction('cancel')"><XCircle class="mr-1.5 h-4 w-4" />Cancel</Button>
                    <Button v-if="canPublish" size="sm" :disabled="busy" @click="runAction('publish')"><CheckCircle2 class="mr-1.5 h-4 w-4" />Publish results</Button>
                </div>
            </div>
            <div v-if="processing" class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-lg bg-muted/50 p-3"><p class="text-xs text-muted-foreground">Progress</p><p class="font-semibold">{{ processing.progress_percentage }}%</p></div>
                <div class="rounded-lg bg-muted/50 p-3"><p class="text-xs text-muted-foreground">Completed</p><p class="font-semibold">{{ processing.completed_pairs }}</p></div>
                <div class="rounded-lg bg-muted/50 p-3"><p class="text-xs text-muted-foreground">Failed</p><p class="font-semibold">{{ processing.failed_pairs }}</p></div>
                <div class="rounded-lg bg-muted/50 p-3"><p class="text-xs text-muted-foreground">Published</p><p class="font-semibold">{{ processing.published_at ? 'Yes' : 'No' }}</p></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-4 text-xs text-muted-foreground"><span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500" /> ≥70% Flagged</span><span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-400" /> 40–69% Review</span><span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500" /> &lt;40% OK</span></div>
        <div v-if="loading" class="rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground">Loading plagiarism reports…</div>
        <div v-else-if="reports.length === 0" class="rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground">No published plagiarism reports yet. Staged comparisons remain private until you publish a completed run.</div>
        <div v-else class="space-y-3">
            <div v-if="reports.some((report) => report.flagged)" class="flex items-center gap-2 text-sm font-semibold text-red-600"><AlertTriangle class="h-4 w-4" />{{ reports.filter((report) => report.flagged).length }} flagged pair(s) above 70% similarity</div>
            <div class="overflow-x-auto rounded-xl border">
                <table class="min-w-[680px] w-full text-sm">
                    <thead class="border-b bg-muted/50"><tr><th class="px-4 py-3 text-left font-medium text-muted-foreground">Student A</th><th class="px-4 py-3 text-left font-medium text-muted-foreground">Student B</th><th class="px-4 py-3 text-center font-medium text-muted-foreground">Similarity</th><th class="px-4 py-3 text-center font-medium text-muted-foreground">Flag</th><th class="px-4 py-3 text-left font-medium text-muted-foreground">Explanation</th></tr></thead>
                    <tbody class="divide-y"><tr v-for="report in reports" :key="report.id" :class="report.flagged ? 'bg-red-50/40 dark:bg-red-950/10' : 'hover:bg-muted/30'">
                        <td class="px-4 py-3"><p class="font-medium">{{ report.student_a?.last_name }}, {{ report.student_a?.first_name }}</p><p class="font-mono text-xs text-muted-foreground">{{ report.student_a?.student_number }}</p></td>
                        <td class="px-4 py-3"><p class="font-medium">{{ report.student_b?.last_name }}, {{ report.student_b?.first_name }}</p><p class="font-mono text-xs text-muted-foreground">{{ report.student_b?.student_number }}</p></td>
                        <td class="px-4 py-3 text-center"><span class="text-lg font-bold" :class="scoreColor(report.similarity_score)">{{ report.similarity_score.toFixed(1) }}%</span></td>
                        <td class="px-4 py-3 text-center"><Badge v-if="report.flagged" variant="destructive">Flagged</Badge><span v-else class="text-xs text-muted-foreground">—</span></td>
                        <td class="max-w-xs px-4 py-3 text-xs text-muted-foreground">{{ report.explanation || '—' }}</td>
                    </tr></tbody>
                </table>
            </div>
        </div>
    </div>
</template>
