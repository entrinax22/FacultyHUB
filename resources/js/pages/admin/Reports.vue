<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    Users,
    Layers,
    ClipboardList,
    FileCheck,
    BookOpen,
    GraduationCap,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

type EnrollmentStat = {
    label: string;
    sections: number;
    enrollments: number;
};

type SubmissionRate = {
    type: string;
    assignments: number;
    submissions: number;
};

type GradeDist = {
    label: string;
    count: number;
};

type TopSection = {
    id: number;
    name: string;
    subject: string;
    faculty: string;
    semester: string;
    enrollments: number;
};

type Totals = {
    users: number;
    sections: number;
    enrollments: number;
    modules: number;
    assignments: number;
    submissions: number;
    graded: number;
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
    return Math.max(...stats.map((s) => s.enrollments), 1);
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:gap-6 sm:p-4 lg:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
                >
                    <BarChart3 class="h-5 w-5 text-primary" />
                </div>

                <div class="min-w-0">
                    <h1 class="text-xl font-semibold sm:text-2xl">
                        System Reports
                    </h1>

                    <p class="text-xs text-muted-foreground sm:text-sm">
                        Overview of platform usage and academic data
                    </p>
                </div>
            </div>
        </div>

        <!-- System Totals -->
        <div
            class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-7"
        >
            <!-- Users -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <Users class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.users }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Users
                </p>
            </div>

            <!-- Sections -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <Layers class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.sections }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Sections
                </p>
            </div>

            <!-- Enrollments -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <GraduationCap class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.enrollments }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Enrollments
                </p>
            </div>

            <!-- Modules -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <BookOpen class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.modules }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Modules
                </p>
            </div>

            <!-- Assignments -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <ClipboardList class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.assignments }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Assignments
                </p>
            </div>

            <!-- Submissions -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <FileCheck class="mx-auto mb-1 h-5 w-5 text-primary" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.submissions }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Submissions
                </p>
            </div>

            <!-- Graded -->
            <div class="rounded-xl border bg-card p-3 text-center shadow-sm sm:p-4">
                <FileCheck class="mx-auto mb-1 h-5 w-5 text-green-500" />
                <p class="text-xl font-bold sm:text-2xl">
                    {{ totals.graded }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Graded
                </p>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">

            <!-- Enrollment by Semester -->
            <div class="min-w-0 rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 text-sm font-semibold">
                    Enrollment by Semester
                </div>

                <div
                    v-if="enrollmentBySemester.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No data yet.
                </div>

                <div v-else class="space-y-4 p-4">
                    <div
                        v-for="row in enrollmentBySemester"
                        :key="row.label"
                        class="space-y-1.5"
                    >
                        <div
                            class="flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span
                                class="max-w-full truncate font-medium sm:max-w-[60%]"
                            >
                                {{ row.label }}
                            </span>

                            <span class="text-muted-foreground">
                                {{ row.enrollments }} enrolled ·
                                {{ row.sections }} sections
                            </span>
                        </div>

                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${(row.enrollments / maxEnrollment(enrollmentBySemester)) * 100}%`,
                                }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grade Distribution -->
            <div class="min-w-0 rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 text-sm font-semibold">
                    Grade Distribution (Released Grades)
                </div>

                <div
                    v-if="gradeDistribution.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No released grades yet.
                </div>

                <div v-else class="space-y-4 p-4">
                    <div
                        v-for="band in gradeOrder"
                        :key="band"
                        class="space-y-1.5"
                    >
                        <template
                            v-if="gradeDistribution.find((g) => g.label === band)"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium">
                                    {{ band }}%
                                </span>

                                <span class="text-muted-foreground">
                                    {{
                                        gradeDistribution.find(
                                            (g) => g.label === band
                                        )?.count ?? 0
                                    }}
                                    grades
                                </span>
                            </div>

                            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="gradeColors[band]"
                                    :style="{
                                        width: `${((gradeDistribution.find((g) => g.label === band)?.count ?? 0) / Math.max(...gradeDistribution.map((g) => g.count), 1)) * 100}%`,
                                    }"
                                ></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Submission Rates -->
            <div class="min-w-0 rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 text-sm font-semibold">
                    Assignment Submission Rates
                </div>

                <div
                    v-if="submissionRates.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No assignments yet.
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="row in submissionRates"
                        :key="row.type"
                        class="flex items-center gap-3 px-4 py-3 sm:gap-4"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ typeLabels[row.type] ?? row.type }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                {{ row.assignments }} assignments
                            </p>

                            <div
                                class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${row.assignments > 0 ? (row.submissions / row.assignments) * 100 : 0}%`,
                                    }"
                                ></div>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold">
                                {{ row.submissions }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                submissions
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Sections -->
            <div class="min-w-0 rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 text-sm font-semibold">
                    Top Sections by Enrollment
                </div>

                <div
                    v-if="topSections.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No sections yet.
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="(section, i) in topSections"
                        :key="section.id"
                        class="flex items-start gap-3 px-4 py-3 text-sm"
                    >
                        <!-- Rank -->
                        <span
                            class="mt-0.5 w-5 shrink-0 text-center text-xs font-bold text-muted-foreground"
                        >
                            {{ i + 1 }}
                        </span>

                        <!-- Section Info -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ section.subject }}
                            </p>

                            <p class="truncate text-xs text-muted-foreground">
                                {{ section.name }} · {{ section.faculty }}
                            </p>

                            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                {{ section.semester }}
                            </p>
                        </div>

                        <!-- Enrollment -->
                        <Badge
                            variant="secondary"
                            class="shrink-0 whitespace-nowrap"
                        >
                            {{ section.enrollments }}
                        </Badge>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
