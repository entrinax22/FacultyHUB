<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, CheckCircle2, AlertCircle, FileText } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Grade = { raw_score: number; max_score: number; is_released: boolean };
type Submission = { id: number; status: string; submitted_at: string; grade: Grade | null };
type Assignment = {
    id: number; title: string; type: string;
    max_score: number; due_date: string | null; is_published: boolean;
    my_submission: Submission | null;
    my_grade: Grade | null;
};

type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

defineProps<{ section: Section; assignments: Assignment[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const typeLabel: Record<string, string> = { essay: 'Essay', mcq: 'Multiple Choice', code: 'Code' };

function isPastDue(dueDate: string | null): boolean {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
}
</script>

<template>
    <Head :title="`Assignments — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/my-sections/${section.id}`"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold">Assignments</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }}
                </p>
            </div>
        </div>

        <div v-if="assignments.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No assignments posted yet.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="a in assignments"
                :key="a.id"
                class="rounded-xl border p-4 space-y-3"
                :class="{ 'border-red-200 bg-red-50/30': isPastDue(a.due_date) && !a.my_submission }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold truncate">{{ a.title }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline" class="text-xs">{{ typeLabel[a.type] }}</Badge>
                            <span>Max: {{ a.max_score }} pts</span>
                            <span v-if="a.due_date" class="flex items-center gap-1" :class="isPastDue(a.due_date) ? 'text-red-600' : ''">
                                <Clock class="h-3 w-3" />
                                {{ isPastDue(a.due_date) ? 'Due was' : 'Due' }}
                                {{ new Date(a.due_date).toLocaleString() }}
                            </span>
                        </div>
                    </div>

                    <!-- Status indicator -->
                    <div class="shrink-0 text-right">
                        <div v-if="a.my_submission" class="space-y-1">
                            <Badge :variant="a.my_submission.status === 'approved' ? 'default' : 'secondary'" class="capitalize">
                                <CheckCircle2 v-if="a.my_submission.status === 'approved'" class="mr-1 h-3 w-3" />
                                {{ a.my_submission.status === 'grading' ? 'Being graded…' : a.my_submission.status }}
                            </Badge>
                            <div v-if="a.my_grade?.is_released" class="text-sm font-bold">
                                {{ a.my_grade.raw_score }} / {{ a.my_grade.max_score }}
                            </div>
                        </div>
                        <Badge v-else-if="isPastDue(a.due_date)" variant="destructive">Past Due</Badge>
                        <Badge v-else variant="outline">Not Submitted</Badge>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button
                        v-if="!a.my_submission && !isPastDue(a.due_date)"
                        size="sm"
                        as-child
                    >
                        <Link :href="`/assignments/${a.id}/submit`">Submit Assignment</Link>
                    </Button>
                    <Button
                        v-else-if="a.my_submission && a.my_submission.status !== 'approved'"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="`/assignments/${a.id}/submit`">Edit Submission</Link>
                    </Button>
                    <Button
                        v-if="a.my_submission"
                        size="sm"
                        variant="ghost"
                        as-child
                    >
                        <Link :href="`/submissions/${a.my_submission.id}`">View Result</Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
