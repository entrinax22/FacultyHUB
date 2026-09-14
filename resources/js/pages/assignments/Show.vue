<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BarChart2, CheckCircle2, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Grade = { raw_score: number; max_score: number; is_released: boolean };
type AiFeedback = { score: number };
type ProctoringEvent = { id: number; event_type: string; metadata: Record<string, string | number | boolean> | null; created_at: string };
type Submission = {
    id: number;
    status: string;
    submitted_at: string;
    student: { id: number; student_no: string; first_name: string; last_name: string };
    grade: Grade | null;
    ai_feedback: AiFeedback | null;
    proctoring_events: ProctoringEvent[];
    proctoring_events_count: number;
    proctoring_alerts_count: number;
};

type Assignment = {
    id: number; title: string; type: string;
    max_score: number; is_published: boolean;
    due_date: string | null; answer_release_at: string | null;
    section: { id: number; name: string; subject: { code: string } };
};

const props = defineProps<{ assignment: Assignment; submissions: Submission[]; plagiarismRan: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    pending: 'secondary',
    grading: 'outline',
    graded: 'default',
    approved: 'default',
};

const releaseForm = useForm({ assignment_id: props.assignment.id });

function releaseAll() {
    if (confirm('Release all grades to students?')) {
        releaseForm.post('/grades/release');
    }
}

function runPlagiarism() {
    router.post(`/assignments/${props.assignment.id}/plagiarism/run`);
}

const approvedCount = props.submissions.filter((s) => s.status === 'approved').length;
const gradedCount = props.submissions.filter((s) => ['graded', 'approved'].includes(s.status)).length;
const expandedSubmissionId = ref<number | null>(null);

function toggleAlerts(submissionId: number) {
    expandedSubmissionId.value = expandedSubmissionId.value === submissionId ? null : submissionId;
}

function eventLabel(eventType: string) {
    return {
        tab_hidden: 'Left exam tab',
        tab_visible: 'Returned to exam tab',
        window_resized: 'Window resized',
        camera_permission_denied: 'Camera permission denied',
        exam_started: 'Exam started',
        heartbeat: 'Connection heartbeat',
        fullscreen_entered: 'Fullscreen enabled',
        fullscreen_exited: 'Fullscreen exited',
        copy_detected: 'Content copied',
        paste_detected: 'Content pasted',
        cut_detected: 'Content cut',
        context_menu_used: 'Context menu used',
        print_screen_suspected: 'Possible screenshot shortcut detected',
    }[eventType] ?? eventType;
}

function eventMetadata(event: ProctoringEvent) {
    if (event.event_type !== 'window_resized' || !event.metadata) {
        return '';
    }

    return `${event.metadata.width ?? '?'} × ${event.metadata.height ?? '?'}`;
}
</script>

<template>
    <Head :title="assignment.title" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ assignment.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ assignment.section.subject.code }} · {{ assignment.section.name }}
                    <span v-if="assignment.due_date"> · Due {{ new Date(assignment.due_date).toLocaleString() }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/assignments/${assignment.id}/edit`">Edit</Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ submissions.length }}</p>
                <p class="text-xs text-muted-foreground">Submitted</p>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ gradedCount }}</p>
                <p class="text-xs text-muted-foreground">Graded</p>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ approvedCount }}</p>
                <p class="text-xs text-muted-foreground">Approved</p>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ assignment.max_score }}</p>
                <p class="text-xs text-muted-foreground">Max Score</p>
            </div>
        </div>

        <!-- Actions bar -->
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="approvedCount > 0"
                variant="outline"
                size="sm"
                :disabled="releaseForm.processing"
                @click="releaseAll"
            >
                <CheckCircle2 class="mr-1.5 h-4 w-4" />
                Release All Grades
            </Button>
            <Button
                v-if="assignment.type === 'essay' && submissions.length >= 2"
                variant="outline"
                size="sm"
                @click="runPlagiarism"
            >
                <BarChart2 class="mr-1.5 h-4 w-4" />
                {{ plagiarismRan ? 'Re-run' : 'Run' }} Plagiarism Check
            </Button>
            <Button
                v-if="plagiarismRan"
                variant="ghost"
                size="sm"
                as-child
            >
                <Link :href="`/assignments/${assignment.id}/plagiarism`">View Plagiarism Report</Link>
            </Button>
        </div>

        <!-- Submissions table -->
        <div v-if="submissions.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            No submissions yet.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Submitted</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Status</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">AI Score</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Final Score</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Exam Alerts</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <template v-for="sub in submissions" :key="sub.id">
                    <tr class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ sub.student.last_name }}, {{ sub.student.first_name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ sub.student.student_no }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ new Date(sub.submitted_at).toLocaleString() }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Badge :variant="statusVariant[sub.status]" class="capitalize">
                                <Loader2 v-if="sub.status === 'grading'" class="mr-1 h-3 w-3 animate-spin" />
                                {{ sub.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground">
                            <span v-if="sub.ai_feedback">{{ sub.ai_feedback.score }} / {{ assignment.max_score }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="sub.grade" class="font-semibold">
                                {{ sub.grade.raw_score }} / {{ sub.grade.max_score }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Button
                                v-if="sub.proctoring_alerts_count > 0"
                                variant="outline"
                                size="sm"
                                class="text-amber-700 dark:text-amber-300"
                                @click="toggleAlerts(sub.id)"
                            >
                                {{ sub.proctoring_alerts_count }} alert{{ sub.proctoring_alerts_count === 1 ? '' : 's' }}
                            </Button>
                            <span v-else class="text-muted-foreground">None</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/submissions/${sub.id}/grade`">
                                    {{ sub.status === 'approved' ? 'View' : 'Review' }}
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="expandedSubmissionId === sub.id" :key="`${sub.id}-alerts`">
                        <td colspan="7" class="bg-amber-50/60 px-4 py-4 dark:bg-amber-950/20">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold">Exam activity alerts</p>
                                    <p class="text-xs text-muted-foreground">Showing the latest recorded events for this student.</p>
                                </div>
                                <Badge variant="destructive">{{ sub.proctoring_alerts_count }} alerts</Badge>
                            </div>
                            <div class="mt-3 space-y-2">
                                <div v-for="event in sub.proctoring_events" :key="event.id" class="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background px-3 py-2 text-sm">
                                    <span class="font-medium">{{ eventLabel(event.event_type) }}</span>
                                    <span v-if="eventMetadata(event)" class="text-xs text-muted-foreground">{{ eventMetadata(event) }}</span>
                                    <time class="text-xs text-muted-foreground" :datetime="event.created_at">{{ new Date(event.created_at).toLocaleString() }}</time>
                                </div>
                                <p v-if="sub.proctoring_events.length === 0" class="text-sm text-muted-foreground">No event details are available.</p>
                            </div>
                        </td>
                    </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
