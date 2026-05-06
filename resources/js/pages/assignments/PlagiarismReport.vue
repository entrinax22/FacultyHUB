<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BarChart2, RefreshCw } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Student = { id: number; first_name: string; last_name: string; student_no: string };
type Report = {
    id: number;
    similarity_score: number;
    flagged: boolean;
    explanation: string | null;
    student_a: Student;
    student_b: Student;
};

type Assignment = {
    id: number;
    title: string;
    section: { id: number; name: string; subject: { code: string } };
};

defineProps<{ assignment: Assignment; reports: Report[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Assignments', href: '#' },
            { title: 'Plagiarism Report', href: '#' },
        ],
    },
});

function rerun(assignmentId: number) {
    router.post(`/assignments/${assignmentId}/plagiarism/run`);
}

function scoreColor(score: number): string {
    if (score >= 70) return 'text-red-600';
    if (score >= 40) return 'text-orange-500';
    return 'text-green-600';
}
</script>

<template>
    <Head :title="`Plagiarism Report — ${assignment.title}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="`/assignments/${assignment.id}`"><ArrowLeft class="h-4 w-4" /></Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold">Plagiarism Report</h1>
                    <p class="text-sm text-muted-foreground">{{ assignment.title }} · {{ assignment.section.subject.code }}</p>
                </div>
            </div>
            <Button variant="outline" size="sm" @click="rerun(assignment.id)">
                <RefreshCw class="mr-1.5 h-4 w-4" /> Re-run Check
            </Button>
        </div>

        <!-- Legend -->
        <div class="flex gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500 inline-block"></span> ≥70% Flagged</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-orange-400 inline-block"></span> 40–69% Review</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-500 inline-block"></span> &lt;40% OK</span>
        </div>

        <div v-if="reports.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No plagiarism data yet. Run the check from the assignment page.
        </div>

        <!-- Flagged first -->
        <div v-else class="space-y-3">
            <div v-if="reports.some((r) => r.flagged)" class="flex items-center gap-2 text-sm font-semibold text-red-600">
                <AlertTriangle class="h-4 w-4" />
                {{ reports.filter((r) => r.flagged).length }} flagged pair{{ reports.filter((r) => r.flagged).length !== 1 ? 's' : '' }} above 70% similarity
            </div>

            <div class="overflow-hidden rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student A</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student B</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Similarity</th>
                            <th class="px-4 py-3 text-center font-medium text-muted-foreground">Flag</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Explanation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="r in reports"
                            :key="r.id"
                            class="hover:bg-muted/30"
                            :class="r.flagged ? 'bg-red-50/40' : ''"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ r.student_a.last_name }}, {{ r.student_a.first_name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ r.student_a.student_no }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ r.student_b.last_name }}, {{ r.student_b.first_name }}</p>
                                <p class="font-mono text-xs text-muted-foreground">{{ r.student_b.student_no }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-lg font-bold" :class="scoreColor(r.similarity_score)">
                                    {{ r.similarity_score.toFixed(1) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge v-if="r.flagged" variant="destructive">Flagged</Badge>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground max-w-xs">
                                {{ r.explanation || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
