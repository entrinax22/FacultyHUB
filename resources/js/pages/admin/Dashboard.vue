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
    CheckCircle2,
    FileCheck,
    Loader2,
    Shield,
    Users,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    activeSemester: { name: string; school_year: string } | null;
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
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10"
                >
                    <Shield class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold sm:text-2xl">
                        Admin Dashboard
                    </h1>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        Analytics and system monitoring<span
                            v-if="data.activeSemester"
                        >
                            · {{ data.activeSemester.name }}
                            {{ data.activeSemester.school_year }}</span
                        >
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex">
                <Button size="sm" variant="outline" as-child
                    ><Link href="/admin/users"
                        ><Users class="mr-2 h-4 w-4" />Manage Users</Link
                    ></Button
                ><Button size="sm" as-child
                    ><Link href="/admin/reports"
                        ><FileCheck class="mr-2 h-4 w-4" />View Reports</Link
                    ></Button
                >
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
                            <option value="">Active semester</option>
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
                            @click="loadDashboard"
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
                    Filtered analytics scope active
                </p></CardContent
            ></Card
        >
        <div
            v-if="loading"
            class="flex items-center justify-center rounded-xl border bg-card p-10 text-sm text-muted-foreground"
        >
            <Loader2 class="mr-2 h-4 w-4 animate-spin" />Loading analytics…
        </div>
        <div
            v-else-if="error"
            class="rounded-xl border border-destructive/30 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm font-medium text-destructive">{{ error }}</p>
            <Button
                class="mt-3"
                size="sm"
                variant="outline"
                @click="loadDashboard"
                >Try Again</Button
            >
        </div>
        <template v-else>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Card
                    ><CardHeader class="p-3 pb-1"
                        ><CardTitle class="text-xs text-muted-foreground"
                            >Total users</CardTitle
                        ></CardHeader
                    ><CardContent class="p-3 pt-1"
                        ><p class="text-2xl font-bold">
                            {{ data.stats.users }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ data.stats.faculty }} faculty ·
                            {{ data.stats.students }} students
                        </p></CardContent
                    ></Card
                ><Card
                    ><CardHeader class="p-3 pb-1"
                        ><CardTitle class="text-xs text-muted-foreground"
                            >Active sections</CardTitle
                        ></CardHeader
                    ><CardContent class="p-3 pt-1"
                        ><p class="text-2xl font-bold">
                            {{ data.stats.sections }}
                        </p></CardContent
                    ></Card
                ><Card
                    ><CardHeader class="p-3 pb-1"
                        ><CardTitle class="text-xs text-muted-foreground"
                            >Enrollments</CardTitle
                        ></CardHeader
                    ><CardContent class="p-3 pt-1"
                        ><p class="text-2xl font-bold">
                            {{ data.stats.enrollments }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            active
                        </p></CardContent
                    ></Card
                ><Card
                    ><CardHeader class="p-3 pb-1"
                        ><CardTitle class="text-xs text-muted-foreground"
                            >Completion rate</CardTitle
                        ></CardHeader
                    ><CardContent class="p-3 pt-1"
                        ><p class="text-2xl font-bold text-primary">
                            {{ data.stats.submissionCompletionRate }}%
                        </p>
                        <p class="text-xs text-muted-foreground">
                            completed / expected slots
                        </p></CardContent
                    ></Card
                ><Card
                    ><CardHeader class="p-3 pb-1"
                        ><CardTitle class="text-xs text-muted-foreground"
                            >AI processing</CardTitle
                        ></CardHeader
                    ><CardContent class="p-3 pt-1"
                        ><p class="text-2xl font-bold">
                            {{ data.stats.aiProcessing }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            runs in scope
                        </p></CardContent
                    ></Card
                >
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Student submission trend</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.submissionTrend.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No submission activity for this filter.
                        </div>
                        <div v-else class="h-64">
                            <Line
                                :data="trendChart"
                                :options="chartOptions"
                                aria-label="Student submission trend line chart"
                            /></div></CardContent
                ></Card>
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Assignment submission status</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.submissionStatuses.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No submissions for this filter.
                        </div>
                        <div v-else class="h-64">
                            <Doughnut
                                :data="statusChart"
                                :options="doughnutOptions"
                                aria-label="Assignment submission status doughnut chart"
                            /></div></CardContent
                ></Card>
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Academic grade distribution</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.gradeDistribution.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No released grades for this filter.
                        </div>
                        <div v-else class="h-64">
                            <Bar
                                :data="gradeChart"
                                :options="chartOptions"
                                aria-label="Academic grade distribution bar chart"
                            /></div></CardContent
                ></Card>
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >AI processing outcomes</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.aiOutcomes.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No AI processing runs for this filter.
                        </div>
                        <div v-else class="h-64">
                            <Bar
                                :data="aiChart"
                                :options="chartOptions"
                                aria-label="AI processing outcomes grouped bar chart"
                            /></div></CardContent
                ></Card>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <Card
                    ><CardHeader
                        ><CardTitle class="text-base"
                            >Enrollment by semester</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.semesterStats.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No semester data available.
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
                            >Faculty grading activity</CardTitle
                        ></CardHeader
                    ><CardContent
                        ><div
                            v-if="!data.facultyActivity.length"
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            No released grading activity.
                        </div>
                        <div v-else class="h-64">
                            <Bar
                                :data="facultyChart"
                                :options="horizontalOptions"
                                aria-label="Faculty grading activity horizontal bar chart"
                            /></div></CardContent
                ></Card>
            </div>
            <Card
                ><CardHeader class="flex flex-row items-center justify-between"
                    ><CardTitle class="text-base">System analysis</CardTitle
                    ><Link
                        href="/admin/reports"
                        class="text-xs font-medium text-primary hover:underline"
                        >View reports</Link
                    ></CardHeader
                ><CardContent
                    ><div
                        v-if="!data.alerts.length"
                        class="flex items-center gap-2 py-4 text-sm text-muted-foreground"
                    >
                        <CheckCircle2 class="h-4 w-4 text-primary" />No
                        rule-based alerts for this filter.
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="alert in data.alerts"
                            :key="alert.title"
                            class="flex gap-3 rounded-lg border p-3"
                        >
                            <AlertTriangle
                                class="mt-0.5 h-4 w-4 shrink-0"
                                :class="
                                    alert.type === 'error'
                                        ? 'text-destructive'
                                        : 'text-orange-500'
                                "
                            />
                            <div>
                                <div class="flex items-center gap-2">
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
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ alert.detail }}
                                </p>
                            </div>
                        </div>
                    </div></CardContent
                ></Card
            >
        </template>
    </div>
</template>
