<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BarChart3, Users, Layers, ClipboardList, FileCheck, BookOpen, GraduationCap } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

type EnrollmentStat = { label: string; sections: number; enrollments: number };
type SubmissionRate = { type: string; assignments: number; submissions: number };
type GradeDist = { label: string; count: number };
type TopSection = {
    id: number; name: string; subject: string;
    faculty: string; semester: string; enrollments: number;
};
type Totals = {
    users: number; sections: number; enrollments: number;
    modules: number; assignments: number; submissions: number; graded: number;
};

defineProps<{
    enrollmentBySemester: EnrollmentStat[];
    submissionRates: SubmissionRate[];
    gradeDistribution: GradeDist[];
    topSections: TopSection[];
    totals: Totals;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Panel', href: '/admin' },
            { title: 'Reports', href: '/admin/reports' },
        ],
    },
});

const typeLabels: Record<string, string> = {
    essay: 'Essay',
    mcq: 'Multiple Choice',
    code: 'Code Assignment',
};

const gradeOrder = ['90–100', '80–89', '75–79', '60–74', 'Below 60'];
const gradeColors: Record<string, string> = {
    '90–100': 'bg-green-500',
    '80–89': 'bg-blue-500',
    '75–79': 'bg-yellow-500',
    '60–74': 'bg-orange-500',
    'Below 60': 'bg-red-500',
};

function maxEnrollment(stats: EnrollmentStat[]): number {
    return Math.max(...stats.map(s => s.enrollments), 1);
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <BarChart3 class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">System Reports</h1>
                <p class="text-sm text-muted-foreground">Overview of platform usage and academic data</p>
            </div>
        </div>

        <!-- System totals -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <Users class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.users }}</p>
                <p class="text-xs text-muted-foreground">Users</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <Layers class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.sections }}</p>
                <p class="text-xs text-muted-foreground">Sections</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <GraduationCap class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.enrollments }}</p>
                <p class="text-xs text-muted-foreground">Enrollments</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <BookOpen class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.modules }}</p>
                <p class="text-xs text-muted-foreground">Modules</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <ClipboardList class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.assignments }}</p>
                <p class="text-xs text-muted-foreground">Assignments</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <FileCheck class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-2xl font-bold">{{ totals.submissions }}</p>
                <p class="text-xs text-muted-foreground">Submissions</p>
            </div>
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm">
                <FileCheck class="mx-auto mb-1 h-5 w-5 text-green-500" />
                <p class="text-2xl font-bold">{{ totals.graded }}</p>
                <p class="text-xs text-muted-foreground">Graded</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Enrollment by semester (horizontal bar chart) -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 font-semibold text-sm">Enrollment by Semester</div>
                <div v-if="enrollmentBySemester.length === 0" class="p-6 text-center text-sm text-muted-foreground">No data yet.</div>
                <div v-else class="space-y-3 p-4">
                    <div v-for="row in enrollmentBySemester" :key="row.label" class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium truncate max-w-[60%]">{{ row.label }}</span>
                            <span class="text-muted-foreground">{{ row.enrollments }} enrolled · {{ row.sections }} sections</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: `${(row.enrollments / maxEnrollment(enrollmentBySemester)) * 100}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grade distribution -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 font-semibold text-sm">Grade Distribution (Released Grades)</div>
                <div v-if="gradeDistribution.length === 0" class="p-6 text-center text-sm text-muted-foreground">No released grades yet.</div>
                <div v-else class="p-4 space-y-3">
                    <div
                        v-for="band in gradeOrder"
                        :key="band"
                        class="space-y-1"
                    >
                        <template v-if="gradeDistribution.find(g => g.label === band)">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium">{{ band }}%</span>
                                <span class="text-muted-foreground">{{ gradeDistribution.find(g => g.label === band)?.count ?? 0 }} grades</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="gradeColors[band]"
                                    :style="{
                                        width: `${((gradeDistribution.find(g => g.label === band)?.count ?? 0) / Math.max(...gradeDistribution.map(g => g.count), 1)) * 100}%`
                                    }"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Submission rates by type -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 font-semibold text-sm">Assignment Submission Rates</div>
                <div v-if="submissionRates.length === 0" class="p-6 text-center text-sm text-muted-foreground">No assignments yet.</div>
                <div v-else class="divide-y">
                    <div v-for="row in submissionRates" :key="row.type" class="flex items-center gap-4 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm">{{ typeLabels[row.type] ?? row.type }}</p>
                            <p class="text-xs text-muted-foreground">{{ row.assignments }} assignments</p>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${row.assignments > 0 ? (row.submissions / row.assignments) * 100 : 0}%` }"
                                />
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-bold text-sm">{{ row.submissions }}</p>
                            <p class="text-xs text-muted-foreground">submissions</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top sections by enrollment -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 font-semibold text-sm">Top Sections by Enrollment</div>
                <div v-if="topSections.length === 0" class="p-6 text-center text-sm text-muted-foreground">No sections yet.</div>
                <div v-else class="divide-y">
                    <div
                        v-for="(section, i) in topSections"
                        :key="section.id"
                        class="flex items-center gap-3 px-4 py-3 text-sm"
                    >
                        <span class="w-5 shrink-0 text-center text-xs font-bold text-muted-foreground">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ section.subject }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ section.name }} · {{ section.faculty }}</p>
                            <p class="text-xs text-muted-foreground">{{ section.semester }}</p>
                        </div>
                        <Badge variant="secondary" class="shrink-0">{{ section.enrollments }}</Badge>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
