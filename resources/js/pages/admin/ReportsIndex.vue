<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import {
    AlertTriangle,
    BookOpen,
    CheckCircle2,
    ClipboardList,
    FileCheck,
    GraduationCap,
    Layers,
    Loader2,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import { showApiError } from '@/lib/flashToast';

ChartJS.register(
    ArcElement,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
);
type EnrollmentStat = { label: string; sections: number; enrollments: number };
type AssignmentStat = {
    id: string;
    title: string;
    submitted: number;
    expected: number;
    completion_rate: number | null;
};
type GradeStat = { label: string; count: number };
type TopSection = {
    id: string;
    name: string;
    subject: string | null;
    semester: string | null;
    enrollments: number;
};
type Insight = { type: 'warning' | 'error'; title: string; detail: string };
type Totals = {
    users: number;
    sections: number;
    enrollments: number;
    modules: number;
    assignments: number;
    submissions: number;
    graded: number;
};
type ReportsData = {
    semesters?: { id: string; label: string }[];
    enrollmentBySemester: EnrollmentStat[];
    assignmentAnalysis: AssignmentStat[];
    gradeDistribution: GradeStat[];
    topSections: TopSection[];
    insights: Insight[];
    totals: Totals;
};
type ReportsResponse = { success: boolean; message: string; data: ReportsData };

const emptyData = (): ReportsData => ({
    semesters: [],
    enrollmentBySemester: [],
    assignmentAnalysis: [],
    gradeDistribution: [],
    topSections: [],
    insights: [],
    totals: {
        users: 0,
        sections: 0,
        enrollments: 0,
        modules: 0,
        assignments: 0,
        submissions: 0,
        graded: 0,
    },
});
const data = ref<ReportsData>(emptyData());
const loading = ref(false);
const error = ref<string | null>(null);
const filterError = ref<string | null>(null);
const semesterId = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const { resolvedAppearance } = useAppearance();
const colors = computed(() =>
    resolvedAppearance.value === 'dark'
        ? {
              text: '#d1fae5',
              grid: 'rgba(167,243,208,.16)',
              green: '#34d399',
              blue: '#60a5fa',
              amber: '#fbbf24',
              red: '#f87171',
          }
        : {
              text: '#374151',
              grid: 'rgba(17,24,39,.10)',
              green: '#15803d',
              blue: '#2563eb',
              amber: '#d97706',
              red: '#dc2626',
          },
);
const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false as const,
    plugins: {
        legend: {
            labels: {
                color: colors.value.text,
                usePointStyle: true,
                boxWidth: 8,
            },
        },
        tooltip: { intersect: false, mode: 'index' as const },
    },
    scales: {
        x: {
            ticks: {
                color: colors.value.text,
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 8,
            },
            grid: { color: colors.value.grid },
        },
        y: {
            beginAtZero: true,
            ticks: { color: colors.value.text, precision: 0 },
            grid: { color: colors.value.grid },
        },
    },
}));
const horizontalOptions = computed(() => ({
    ...chartOptions.value,
    indexAxis: 'y' as const,
}));
const enrollmentChart = computed(() => ({
    labels: data.value.enrollmentBySemester.map((item) => item.label),
    datasets: [
        {
            label: 'Enrollments',
            data: data.value.enrollmentBySemester.map(
                (item) => item.enrollments,
            ),
            backgroundColor: colors.value.green,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
const gradeLabels = ['90–100', '80–89', '75–79', '60–74', 'Below 60'];
const gradeChart = computed(() => ({
    labels: gradeLabels,
    datasets: [
        {
            label: 'Released grades',
            data: gradeLabels.map(
                (label) =>
                    data.value.gradeDistribution.find(
                        (item) => item.label === label,
                    )?.count ?? 0,
            ),
            backgroundColor: colors.value.green,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
const assignmentChart = computed(() => ({
    labels: data.value.assignmentAnalysis.map((item) => item.title),
    datasets: [
        {
            label: 'Submitted',
            data: data.value.assignmentAnalysis.map((item) => item.submitted),
            backgroundColor: colors.value.blue,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
const sectionChart = computed(() => ({
    labels: data.value.topSections.map((item) => item.name),
    datasets: [
        {
            label: 'Active enrollments',
            data: data.value.topSections.map((item) => item.enrollments),
            backgroundColor: colors.value.green,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
async function loadReports() {
    if (loading.value) {
        return;
    }

    if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
        filterError.value = 'Start date must be before or equal to end date.';

        return;
    }

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);

        for (const [key, value] of Object.entries({
            semester_id: semesterId.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        })) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        }

        window.history.replaceState({}, '', url);
    }

    loading.value = true;
    error.value = null;
    filterError.value = null;

    try {
        const response = await axios.get<ReportsResponse>(
            '/admin/reports/data',
            {
                params: {
                    semester_id: semesterId.value || undefined,
                    date_from: dateFrom.value || undefined,
                    date_to: dateTo.value || undefined,
                },
            },
        );

        if (!response.data.success) {
            throw new Error(response.data.message);
        }

        data.value = response.data.data ?? emptyData();
    } catch (err: any) {
        error.value =
            err.response?.data?.message ||
            err.message ||
            'Failed to load reports.';
        showApiError(err);
    } finally {
        loading.value = false;
    }
}
function resetFilters() {
    semesterId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    loadReports();
}
function formatRate(value: number | null) {
    return value === null ? 'No denominator' : `${value}%`;
}
onMounted(() => {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        semesterId.value = params.get('semester_id') ?? '';
        dateFrom.value = params.get('date_from') ?? '';
        dateTo.value = params.get('date_to') ?? '';
    }

    loadReports();
});
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Panel', href: '/admin' },
            { title: 'Reports', href: '/admin/reports' },
        ],
    },
});
</script>

<template>
    <Head title="System Reports" />
    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10"
                >
                    <ClipboardList class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold sm:text-2xl">
                        System Reports
                    </h1>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        Analyze academic performance, enrollment, submissions,
                        and platform activity.
                    </p>
                </div>
            </div>
        </div>
        <Card class="border-primary/20 bg-primary/[.03]"
            ><CardContent class="p-3"
                ><div class="flex flex-col gap-2 md:flex-row md:items-end">
                    <label class="grid min-w-0 flex-1 gap-1 text-xs font-medium"
                        >Semester<select
                            v-model="semesterId"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">All semesters</option>
                            <option
                                v-for="semester in data.semesters"
                                :key="semester.id"
                                :value="semester.id"
                            >
                                {{ semester.label }}
                            </option>
                        </select></label
                    ><label
                        class="grid min-w-0 flex-1 gap-1 text-xs font-medium"
                        >Start date<input
                            v-model="dateFrom"
                            type="date"
                            class="h-9 rounded-md border bg-background px-3 text-sm" /></label
                    ><label
                        class="grid min-w-0 flex-1 gap-1 text-xs font-medium"
                        >End date<input
                            v-model="dateTo"
                            type="date"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                    /></label>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            :disabled="loading"
                            @click="loadReports"
                            >Apply filters</Button
                        ><Button
                            size="sm"
                            variant="outline"
                            :disabled="loading"
                            @click="resetFilters"
                            >Reset</Button
                        >
                    </div>
                </div>
                <p v-if="filterError" class="mt-2 text-xs text-destructive">
                    {{ filterError }}
                </p>
                <p
                    v-else-if="semesterId || dateFrom || dateTo"
                    class="mt-2 text-xs text-primary"
                >
                    Filtered report scope active
                </p></CardContent
            ></Card
        >
        <div
            v-if="loading"
            class="flex items-center justify-center rounded-xl border bg-card p-10 text-sm text-muted-foreground"
        >
            <Loader2 class="mr-2 h-4 w-4 animate-spin" />Loading reports…
        </div>
        <div
            v-else-if="error"
            class="rounded-xl border border-destructive/30 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">{{ error }}</p>
            <Button
                class="mt-3"
                size="sm"
                variant="outline"
                @click="loadReports"
                >Try again</Button
            >
        </div>
        <template v-else>
            <div
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7"
            >
                <Card
                    v-for="metric in [
                        {
                            label: 'Users',
                            value: data.totals.users,
                            icon: Users,
                            help: 'Accounts in scope',
                        },
                        {
                            label: 'Sections',
                            value: data.totals.sections,
                            icon: Layers,
                            help: 'Sections in scope',
                        },
                        {
                            label: 'Enrollments',
                            value: data.totals.enrollments,
                            icon: GraduationCap,
                            help: 'Active enrollments',
                        },
                        {
                            label: 'Modules',
                            value: data.totals.modules,
                            icon: BookOpen,
                            help: 'Modules in scope',
                        },
                        {
                            label: 'Assignments',
                            value: data.totals.assignments,
                            icon: ClipboardList,
                            help: 'Assignments in scope',
                        },
                        {
                            label: 'Submissions',
                            value: data.totals.submissions,
                            icon: FileCheck,
                            help: 'Submitted records',
                        },
                        {
                            label: 'Graded',
                            value: data.totals.graded,
                            icon: CheckCircle2,
                            help: 'Released grades',
                        },
                    ]"
                    :key="metric.label"
                    class="min-w-0"
                    ><CardContent class="p-3 sm:p-4"
                        ><component
                            :is="metric.icon"
                            class="mb-2 h-5 w-5 text-primary"
                        />
                        <p class="text-xl font-bold sm:text-2xl">
                            {{ metric.value }}
                        </p>
                        <p class="text-xs font-medium">{{ metric.label }}</p>
                        <p class="mt-1 text-[10px] text-muted-foreground">
                            {{ metric.help }}
                        </p></CardContent
                    ></Card
                >
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Enrollment by semester</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.enrollmentBySemester.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No enrollment data for this scope.
                        </div>
                        <div v-else class="h-64">
                            <Bar
                                :data="enrollmentChart"
                                :options="horizontalOptions"
                                aria-label="Enrollment by semester horizontal bar chart"
                            /></div></CardContent></Card
                ><Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Released grade distribution</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.gradeDistribution.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No released grades for this scope.
                        </div>
                        <div v-else class="h-64">
                            <Bar
                                :data="gradeChart"
                                :options="chartOptions"
                                aria-label="Released grade distribution bar chart"
                            /></div></CardContent></Card
                ><Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Submissions by assignment</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.assignmentAnalysis.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No assignment submissions for this scope.
                        </div>
                        <div v-else class="h-72">
                            <Bar
                                :data="assignmentChart"
                                :options="horizontalOptions"
                                aria-label="Submissions by assignment horizontal bar chart"
                            />
                        </div>
                        <div
                            v-if="data.assignmentAnalysis.length"
                            class="mt-3 space-y-1 border-t pt-3 text-xs text-muted-foreground"
                        >
                            <div
                                v-for="assignment in data.assignmentAnalysis.slice(
                                    0,
                                    5,
                                )"
                                :key="assignment.id"
                                class="flex justify-between gap-3"
                            >
                                <span
                                    class="truncate"
                                    :title="assignment.title"
                                    >{{ assignment.title }}</span
                                ><span class="shrink-0"
                                    >{{ assignment.submitted }} /
                                    {{ assignment.expected || '—' }} ·
                                    {{
                                        formatRate(assignment.completion_rate)
                                    }}</span
                                >
                            </div>
                        </div></CardContent
                    ></Card
                ><Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Top sections by enrollment</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.topSections.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No sections for this scope.
                        </div>
                        <div v-else class="h-72">
                            <Bar
                                :data="sectionChart"
                                :options="horizontalOptions"
                                aria-label="Top sections by enrollment horizontal bar chart"
                            />
                        </div>
                        <div
                            v-if="data.topSections.length"
                            class="mt-3 space-y-1 border-t pt-3 text-xs text-muted-foreground"
                        >
                            <div
                                v-for="section in data.topSections.slice(0, 5)"
                                :key="section.id"
                                class="flex justify-between gap-3"
                            >
                                <span
                                    class="truncate"
                                    :title="`${section.subject ?? ''} · ${section.name}`"
                                    >{{ section.subject ?? section.name }}</span
                                ><span class="shrink-0">{{
                                    section.enrollments
                                }}</span>
                            </div>
                        </div></CardContent
                    ></Card
                >
            </div>
            <Card
                ><CardHeader
                    ><CardTitle class="text-base"
                        >Academic insights</CardTitle
                    ></CardHeader
                ><CardContent
                    ><div
                        v-if="!data.insights.length"
                        class="flex items-center gap-2 py-3 text-sm text-muted-foreground"
                    >
                        <CheckCircle2 class="h-4 w-4 text-primary" />No
                        rule-based insights for this scope.
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="insight in data.insights"
                            :key="insight.title"
                            class="flex gap-3 rounded-lg border p-3"
                        >
                            <AlertTriangle
                                class="mt-0.5 h-4 w-4 shrink-0 text-orange-500"
                            />
                            <div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="secondary">{{
                                        insight.type
                                    }}</Badge>
                                    <p class="text-sm font-medium">
                                        {{ insight.title }}
                                    </p>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ insight.detail }}
                                </p>
                            </div>
                        </div>
                    </div></CardContent
                ></Card
            >
        </template>
    </div>
</template>
