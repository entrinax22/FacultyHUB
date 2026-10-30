<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import {
    AlertTriangle,
    Bot,
    CheckCircle2,
    FileCheck,
    GraduationCap,
    Layers,
    Shield,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import DashboardMetricCard from '@/components/admin/DashboardMetricCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAppearance } from '@/composables/useAppearance';
import { showApiError } from '@/lib/flashToast';

type Point = { label?: string; date?: string; count: number };
type AlertItem = { type: 'warning' | 'error'; title: string; detail: string };
type DashboardData = {
    stats: {
        users: number;
        usersByRole: Record<string, number>;
        faculty: number;
        students: number;
        sections: number;
        enrollments: number;
        submissions: number;
        pendingGrading: number;
        submissionCompletionRate: number;
        aiProcessing: number;
    };
    activeSemester: { id: string; name: string; school_year: string } | null;
    selectedSemester: { name: string; school_year: string } | null;
    semesters: { id: string; label: string }[];
    semesterStats: {
        id: string;
        name: string;
        school_year: string;
        sections_count: number;
        enrollments_count: number;
        is_active: boolean;
    }[];
    submissionTrend: Point[];
    submissionStatuses: Point[];
    gradeDistribution: Point[];
    facultyActivity: Point[];
    aiOutcomes: { type: string; status: string; count: number }[];
    alerts: AlertItem[];
};
type ApiResponse = { success: boolean; message: string; data: DashboardData };

ChartJS.register(
    ArcElement,
    BarElement,
    CategoryScale,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
);
const emptyData = (): DashboardData => ({
    stats: {
        users: 0,
        usersByRole: {},
        faculty: 0,
        students: 0,
        sections: 0,
        enrollments: 0,
        submissions: 0,
        pendingGrading: 0,
        submissionCompletionRate: 0,
        aiProcessing: 0,
    },
    activeSemester: null,
    selectedSemester: null,
    semesters: [],
    semesterStats: [],
    submissionTrend: [],
    submissionStatuses: [],
    gradeDistribution: [],
    facultyActivity: [],
    aiOutcomes: [],
    alerts: [],
});
const data = ref<DashboardData>(emptyData());
const loading = ref(true);
const error = ref<string | null>(null);
const filterError = ref<string | null>(null);
const semesterId = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const { resolvedAppearance } = useAppearance();
const selectedSemesterLabel = computed(() => {
    if (semesterId.value) {
        return (
            data.value.semesters.find(
                (semester) => semester.id === semesterId.value,
            )?.label ??
            (data.value.selectedSemester
                ? `${data.value.selectedSemester.name} ${data.value.selectedSemester.school_year}`
                : 'Selected semester')
        );
    }

    return data.value.activeSemester
        ? `${data.value.activeSemester.name} ${data.value.activeSemester.school_year}`
        : 'All semesters';
});
const metrics = computed(() => [
    {
        title: 'Total users',
        value: data.value.stats.users,
        description: `${data.value.stats.faculty} faculty Â· ${data.value.stats.students} students; filtered by account creation date.`,
        icon: Users,
    },
    {
        title: 'Sections',
        value: data.value.stats.sections,
        description: 'Selected semester, filtered by section creation date.',
        icon: Layers,
    },
    {
        title: 'Enrollments',
        value: data.value.stats.enrollments,
        description:
            'Active enrollments, filtered by enrollment creation date.',
        icon: GraduationCap,
    },
    {
        title: 'Completion rate',
        value: `${data.value.stats.submissionCompletionRate}%`,
        description:
            'Submitted, in grading, graded, or approved / expected slots.',
        icon: CheckCircle2,
    },
    {
        title: 'AI processing',
        value: data.value.stats.aiProcessing,
        description: 'Assignment runs, filtered by run creation date.',
        icon: Bot,
    },
]);
const colors = computed(() =>
    resolvedAppearance.value === 'dark'
        ? {
              text: '#d1fae5',
              grid: 'rgba(167,243,208,.16)',
              green: '#34d399',
              greenSoft: 'rgba(52,211,153,.22)',
              blue: '#60a5fa',
              amber: '#fbbf24',
              red: '#f87171',
              slate: '#94a3b8',
          }
        : {
              text: '#374151',
              grid: 'rgba(17,24,39,.10)',
              green: '#15803d',
              greenSoft: 'rgba(21,128,61,.16)',
              blue: '#2563eb',
              amber: '#d97706',
              red: '#dc2626',
              slate: '#64748b',
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
const trendChart = computed(() => ({
    labels: data.value.submissionTrend.map((item) => item.date),
    datasets: [
        {
            label: 'Submissions',
            data: data.value.submissionTrend.map((item) => item.count),
            borderColor: colors.value.green,
            backgroundColor: colors.value.greenSoft,
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5,
        },
    ],
}));
const formatLabel = (label: string) => label.replaceAll('_', ' ');
const statusLabels = computed(() => [
    ...new Set(
        [
            'approved',
            'graded',
            'pending',
            'grading',
            ...data.value.submissionStatuses.map((item) => item.label ?? ''),
        ].filter(Boolean),
    ),
]);
const statusTotal = computed(() =>
    data.value.submissionStatuses.reduce((sum, item) => sum + item.count, 0),
);
const statusChart = computed(() => ({
    labels: statusLabels.value.map(formatLabel),
    datasets: [
        {
            label: 'Submissions',
            data: statusLabels.value.map(
                (label) =>
                    data.value.submissionStatuses.find(
                        (item) => item.label === label,
                    )?.count ?? 0,
            ),
            backgroundColor: [
                colors.value.green,
                colors.value.blue,
                colors.value.amber,
                colors.value.slate,
                colors.value.red,
            ],
            borderWidth: 0,
        },
    ],
}));
const doughnutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    cutout: '64%',
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: {
                color: colors.value.text,
                usePointStyle: true,
                boxWidth: 8,
            },
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const count = Number(context.raw ?? 0);
                    const percent = statusTotal.value
                        ? Math.round((count / statusTotal.value) * 100)
                        : 0;

                    return ` ${formatLabel(String(context.label))}: ${count} (${percent}%)`;
                },
            },
        },
    },
}));
const gradeLabels = ['90-100', '80-89', '75-79', '60-74', 'Below 60'];
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
            borderRadius: 5,
            barPercentage: 0.65,
        },
    ],
}));
const aiStatuses = computed(() => [
    ...new Set([
        'completed',
        'pending',
        'processing',
        'failed',
        'cancelled',
        ...data.value.aiOutcomes.map((item) => item.status),
    ]),
]);
const aiChart = computed(() => ({
    labels: aiStatuses.value.map(formatLabel),
    datasets: ['grading', 'plagiarism'].map((type, index) => ({
        label: type === 'grading' ? 'AI grading' : 'Plagiarism',
        data: aiStatuses.value.map(
            (status) =>
                data.value.aiOutcomes.find(
                    (item) => item.type === type && item.status === status,
                )?.count ?? 0,
        ),
        backgroundColor: index === 0 ? colors.value.green : colors.value.blue,
        borderRadius: 4,
    })),
}));
const enrollmentChart = computed(() => ({
    labels: data.value.semesterStats.map(
        (item) => `${item.name} ${item.school_year}`,
    ),
    datasets: [
        {
            label: 'Active enrollments',
            data: data.value.semesterStats.map(
                (item) => item.enrollments_count,
            ),
            backgroundColor: colors.value.green,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
const facultyChart = computed(() => ({
    labels: data.value.facultyActivity.map((item) => item.label),
    datasets: [
        {
            label: 'Released grades',
            data: data.value.facultyActivity.map((item) => item.count),
            backgroundColor: colors.value.blue,
            borderRadius: 4,
            barPercentage: 0.65,
        },
    ],
}));
const submissionTrendSummary = computed(() => {
    const points = data.value.submissionTrend;

    if (!points.length) {
        return 'No submission dates are available.';
    }

    const total = points.reduce((sum, point) => sum + point.count, 0);

    return `${total} submissions across ${points.length} dates with activity, from ${points[0].date} through ${points[points.length - 1].date}.`;
});
const submissionStatusSummary = computed(() =>
    data.value.submissionStatuses
        .filter((item) => item.count > 0)
        .map((item) => `${formatLabel(item.label ?? '')}: ${item.count}`)
        .join('; '),
);
const gradeSummary = computed(() =>
    data.value.gradeDistribution
        .map((item) => `${item.label}: ${item.count}`)
        .join('; '),
);
const aiSummary = computed(() =>
    data.value.aiOutcomes
        .map(
            (item) =>
                `${formatLabel(item.type)} ${formatLabel(item.status)}: ${item.count}`,
        )
        .join('; '),
);
const enrollmentSummary = computed(() =>
    data.value.semesterStats
        .map(
            (item) =>
                `${item.name} ${item.school_year}: ${item.enrollments_count} active enrollments`,
        )
        .join('; '),
);
const facultySummary = computed(() =>
    data.value.facultyActivity
        .map((item) => `${item.label}: ${item.count} released grades`)
        .join('; '),
);

async function loadDashboard() {
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
        const response = await axios.get<ApiResponse>('/admin/dashboard/data', {
            params: {
                semester_id: semesterId.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
        });

        if (!response.data.success) {
            throw new Error(response.data.message);
        }

        data.value = response.data.data ?? emptyData();
    } catch (err: any) {
        error.value =
            err.response?.data?.message ||
            err.message ||
            'Failed to load dashboard.';
        showApiError(err);
    } finally {
        loading.value = false;
    }
}
function resetFilters() {
    semesterId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    loadDashboard();
}
onMounted(() => {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        semesterId.value = params.get('semester_id') ?? '';
        dateFrom.value = params.get('date_from') ?? '';
        dateTo.value = params.get('date_to') ?? '';
    }

    loading.value = false;
    loadDashboard();
});
defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin Panel', href: '/admin' }] },
});
</script>

<template>
    <Head title="Admin Dashboard" />
    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <PageHeader
            title="Admin Dashboard"
            :description="`Analytics and system monitoring · ${selectedSemesterLabel}`"
            :icon="Shield"
        >
            <template #actions>
                <Button size="sm" variant="outline" as-child>
                    <Link href="/admin/users">
                        <Users class="mr-2 size-4" aria-hidden="true" />
                        Manage Users
                    </Link>
                </Button>
                <Button size="sm" as-child>
                    <Link href="/admin/reports">
                        <FileCheck class="mr-2 size-4" aria-hidden="true" />
                        View Reports
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card class="border-primary/20 bg-primary/[.03]">
            <CardContent class="space-y-3 p-3 sm:p-4">
                <div
                    class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_auto] lg:items-end"
                >
                    <div class="grid min-w-0 gap-1.5">
                        <label
                            for="dashboard-semester"
                            class="text-sm font-medium"
                            >Semester</label
                        >
                        <select
                            id="dashboard-semester"
                            v-model="semesterId"
                            aria-describedby="dashboard-filter-help"
                            class="h-9 min-w-0 rounded-md border bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">
                                {{
                                    data.activeSemester
                                        ? 'Active semester'
                                        : 'All semesters'
                                }}
                            </option>
                            <option
                                v-for="semester in data.semesters"
                                :key="semester.id"
                                :value="semester.id"
                            >
                                {{ semester.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label
                            for="dashboard-date-from"
                            class="text-sm font-medium"
                            >From date</label
                        >
                        <input
                            id="dashboard-date-from"
                            v-model="dateFrom"
                            type="date"
                            :aria-invalid="Boolean(filterError)"
                            :aria-describedby="
                                filterError
                                    ? 'dashboard-filter-error'
                                    : 'dashboard-filter-help'
                            "
                            class="h-9 min-w-0 rounded-md border bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <label
                            for="dashboard-date-to"
                            class="text-sm font-medium"
                            >Through date</label
                        >
                        <input
                            id="dashboard-date-to"
                            v-model="dateTo"
                            type="date"
                            :aria-invalid="Boolean(filterError)"
                            :aria-describedby="
                                filterError
                                    ? 'dashboard-filter-error'
                                    : 'dashboard-filter-help'
                            "
                            class="h-9 min-w-0 rounded-md border bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                    <div
                        class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1"
                    >
                        <Button
                            type="button"
                            size="sm"
                            :disabled="loading"
                            @click="loadDashboard"
                        >
                            Apply filters
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="loading"
                            @click="resetFilters"
                        >
                            Reset
                        </Button>
                    </div>
                </div>
                <p
                    id="dashboard-filter-help"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Date ranges use creation dates for users, sections,
                    enrollments, assignments, and AI runs; submission dates for
                    submissions; and update dates for released grades.
                </p>
                <p
                    v-if="filterError"
                    id="dashboard-filter-error"
                    role="alert"
                    aria-live="polite"
                    class="text-sm text-destructive"
                >
                    {{ filterError }}
                </p>
                <p
                    v-else-if="semesterId || dateFrom || dateTo"
                    class="text-xs text-primary"
                    aria-live="polite"
                >
                    Filtered analytics for {{ selectedSemesterLabel }}.
                </p>
            </CardContent>
        </Card>

        <div
            v-if="loading"
            role="status"
            aria-live="polite"
            aria-busy="true"
            class="space-y-5"
        >
            <span class="sr-only"
                >Loading admin dashboard metrics and charts.</span
            >
            <div
                aria-hidden="true"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
            >
                <Card
                    v-for="index in 5"
                    :key="`metric-skeleton-${index}`"
                    class="min-w-0 p-3 sm:p-4"
                >
                    <Skeleton
                        class="mb-3 h-4 w-2/3 motion-reduce:animate-none"
                    />
                    <Skeleton
                        class="mb-2 h-8 w-1/3 motion-reduce:animate-none"
                    />
                    <Skeleton class="h-3 w-full motion-reduce:animate-none" />
                </Card>
            </div>
            <div aria-hidden="true" class="grid gap-4 lg:grid-cols-2">
                <Card
                    v-for="index in 6"
                    :key="`chart-skeleton-${index}`"
                    class="p-4"
                >
                    <Skeleton
                        class="mb-4 h-5 w-1/2 motion-reduce:animate-none"
                    />
                    <Skeleton class="h-56 w-full motion-reduce:animate-none" />
                </Card>
            </div>
        </div>

        <section
            v-else-if="error"
            aria-labelledby="dashboard-load-error-title"
            class="rounded-xl border border-destructive/30 bg-destructive/5 p-6 text-center"
        >
            <h2 id="dashboard-load-error-title" class="text-base font-semibold">
                Dashboard data could not be loaded
            </h2>
            <p
                role="alert"
                aria-live="assertive"
                class="mt-2 text-sm text-destructive"
            >
                {{ error }}
            </p>
            <Button
                type="button"
                class="mt-3"
                size="sm"
                variant="outline"
                @click="loadDashboard"
            >
                Try Again
            </Button>
        </section>

        <template v-else>
            <section
                aria-labelledby="dashboard-metrics-heading"
                class="space-y-3"
            >
                <h2
                    id="dashboard-metrics-heading"
                    class="text-lg font-semibold tracking-tight"
                >
                    At a glance
                </h2>
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                >
                    <DashboardMetricCard
                        v-for="metric in metrics"
                        :key="metric.title"
                        :title="metric.title"
                        :value="metric.value"
                        :description="metric.description"
                        :icon="metric.icon"
                    />
                </div>
            </section>

            <section
                aria-labelledby="dashboard-submissions-heading"
                class="space-y-3"
            >
                <h2
                    id="dashboard-submissions-heading"
                    class="text-lg font-semibold tracking-tight"
                >
                    Submission and grading overview
                </h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Student submission trend</CardTitle
                            >
                            <CardDescription
                                >Daily submissions by submitted date. The chart
                                shows the latest 90 dates with
                                activity.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.submissionTrend.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No submission activity for this filter.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Line
                                    :data="trendChart"
                                    :options="chartOptions"
                                    aria-label="Student submission trend line chart"
                                    aria-describedby="submission-trend-summary"
                                />
                                <p
                                    id="submission-trend-summary"
                                    class="sr-only"
                                >
                                    {{ submissionTrendSummary }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Assignment submission status</CardTitle
                            >
                            <CardDescription
                                >Submission records grouped by workflow
                                status.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.submissionStatuses.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No submissions for this filter.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Doughnut
                                    :data="statusChart"
                                    :options="doughnutOptions"
                                    aria-label="Assignment submission status doughnut chart"
                                    aria-describedby="submission-status-summary"
                                />
                                <p
                                    id="submission-status-summary"
                                    class="sr-only"
                                >
                                    {{
                                        submissionStatusSummary ||
                                        'No status counts are available.'
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section
                aria-labelledby="dashboard-academic-heading"
                class="space-y-3"
            >
                <h2
                    id="dashboard-academic-heading"
                    class="text-lg font-semibold tracking-tight"
                >
                    Academic and system activity
                </h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Academic grade distribution</CardTitle
                            >
                            <CardDescription
                                >Released grades grouped by percentage
                                band.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.gradeDistribution.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No released grades for this filter.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Bar
                                    :data="gradeChart"
                                    :options="chartOptions"
                                    aria-label="Academic grade distribution bar chart"
                                    aria-describedby="grade-distribution-summary"
                                />
                                <p
                                    id="grade-distribution-summary"
                                    class="sr-only"
                                >
                                    {{ gradeSummary }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >AI processing outcomes</CardTitle
                            >
                            <CardDescription
                                >Assignment-related grading and plagiarism runs
                                by status.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.aiOutcomes.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No AI processing runs for this filter.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Bar
                                    :data="aiChart"
                                    :options="chartOptions"
                                    aria-label="AI processing outcomes grouped bar chart"
                                    aria-describedby="ai-outcomes-summary"
                                />
                                <p id="ai-outcomes-summary" class="sr-only">
                                    {{ aiSummary }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Enrollment by semester</CardTitle
                            >
                            <CardDescription
                                >Active enrollments across the six latest
                                semesters, independent of the filters
                                above.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.semesterStats.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No semester data available.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Bar
                                    :data="enrollmentChart"
                                    :options="horizontalOptions"
                                    aria-label="Enrollment by semester horizontal bar chart"
                                    aria-describedby="enrollment-summary"
                                />
                                <p id="enrollment-summary" class="sr-only">
                                    {{ enrollmentSummary }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Faculty grading activity</CardTitle
                            >
                            <CardDescription
                                >Released grades by rank; labels are anonymous
                                positions, not faculty names.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="!data.facultyActivity.length"
                                class="py-10 text-center text-sm text-muted-foreground"
                            >
                                No released grading activity.
                            </div>
                            <div v-else class="h-64 min-w-0">
                                <Bar
                                    :data="facultyChart"
                                    :options="horizontalOptions"
                                    aria-label="Faculty grading activity horizontal bar chart"
                                    aria-describedby="faculty-activity-summary"
                                />
                                <p
                                    id="faculty-activity-summary"
                                    class="sr-only"
                                >
                                    {{ facultySummary }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <section aria-labelledby="dashboard-alerts-heading">
                <Card>
                    <CardHeader>
                        <CardTitle
                            id="dashboard-alerts-heading"
                            class="text-base"
                            >System alerts</CardTitle
                        >
                        <CardDescription
                            >Rule-based signals from existing completion,
                            grading, participation, and AI processing
                            thresholds.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="!data.alerts.length"
                            class="flex items-center gap-2 py-4 text-sm text-muted-foreground"
                        >
                            <CheckCircle2
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            No rule-based alerts for this filter.
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="alert in data.alerts"
                                :key="alert.title"
                                class="flex min-w-0 gap-3 rounded-lg border p-3"
                            >
                                <AlertTriangle
                                    class="mt-0.5 size-4 shrink-0"
                                    :class="
                                        alert.type === 'error'
                                            ? 'text-destructive'
                                            : 'text-orange-500'
                                    "
                                    aria-hidden="true"
                                />
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Badge
                                            :variant="
                                                alert.type === 'error'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            "
                                            >{{ alert.type }}</Badge
                                        >
                                        <p class="text-sm font-medium">
                                            {{ alert.title }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ alert.detail }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </template>
    </div>
</template>
