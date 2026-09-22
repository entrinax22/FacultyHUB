<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

import {
    AlertTriangle,
    ArrowLeft,
    FileDown,
    Loader2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { showApiError } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Student = {
    id: string;
    student_no: string | null;
    first_name: string;
    last_name: string;
    middle_name: string | null;
};

type Row = {
    student: Student;

    present: number;
    late: number;
    absent: number;
    excused: number;
    attended: number;
    total: number;
    percentage: number | null;
};

type Section = {
    id: string;
    name: string;

    subject: {
        id: string;
        code: string;
        name: string;
    } | null;

    semester: {
        id: string;
        name: string;
    } | null;
};

type SummaryResponse = {
    success: boolean;
    message: string;

    data: {
        section: Section;
        total_sessions: number;
        rows: Row[];
    };
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    section: Section;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sections',
                href: '/sections',
            },
            {
                title: 'Attendance',
                href: '#',
            },
            {
                title: 'Summary',
                href: '#',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const rows = ref<Row[]>([]);
const totalSessions = ref(0);
const loading = ref(true);
const absenceLimit = ref(3);

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

const statusBadge = (
    row: Row,
): {
    label: string;
    variant:
        | 'default'
        | 'secondary'
        | 'destructive'
        | 'outline';
} => {
    if (row.absent >= absenceLimit.value) {
        return {
            label: 'Flagged',
            variant: 'destructive',
        };
    }

    if (row.absent >= absenceLimit.value - 1) {
        return {
            label: 'Warning',
            variant: 'outline',
        };
    }

    return {
        label: 'Good',
        variant: 'default',
    };
};

/*
|--------------------------------------------------------------------------
| Percentage Color
|--------------------------------------------------------------------------
*/

function percentageColor(
    pct: number | null,
): string {
    if (pct === null) {
        return 'text-muted-foreground';
    }

    if (pct >= 80) {
        return 'text-green-600 font-semibold';
    }

    if (pct >= 60) {
        return 'text-orange-500 font-semibold';
    }

    return 'text-red-600 font-semibold';
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

const flaggedCount = computed(() => {
    return rows.value.filter(
        (row) =>
            row.absent >= absenceLimit.value,
    ).length;
});

const classAvg = computed(() => {
    const validRows = rows.value.filter(
        (row) =>
            row.percentage !== null,
    );

    if (!validRows.length) {
        return null;
    }

    const total = validRows.reduce(
        (sum, row) =>
            sum + (row.percentage ?? 0),
        0,
    );

    return (
        total / validRows.length
    ).toFixed(1);
});

/*
|--------------------------------------------------------------------------
| Load Summary
|--------------------------------------------------------------------------
*/

async function loadSummary() {
    loading.value = true;

    try {
        const response =
            await axios.get<SummaryResponse>(
                `/sections/${props.section.id}/attendance/summary/data`,
            );

        const data =
            response.data.data;

        rows.value =
            data.rows ?? [];

        totalSessions.value =
            data.total_sessions ?? 0;
    } catch (error) {
        console.error(
            'Failed to load attendance summary:',
            error,
        );

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Export PDF
|--------------------------------------------------------------------------
*/

function exportPdf() {
    window.open(
        `/sections/${props.section.id}/attendance/summary/pdf`,
        '_blank',
    );
}

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSummary();
});
</script>

<template>
    <Head
        :title="`Attendance Summary — ${section.name}`"
    />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
    >
        <!-- ============================================================ -->
        <!-- Header -->
        <!-- ============================================================ -->

        <div
            class="flex items-start justify-between"
        >
            <div
                class="flex items-start gap-3"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="-ml-2 mt-0.5"
                >
                    <Link
                        :href="`/sections/${section.id}/attendance`"
                    >
                        <ArrowLeft
                            class="h-4 w-4"
                        />
                    </Link>
                </Button>

                <div>
                    <h1
                        class="text-xl font-semibold"
                    >
                        Attendance Summary
                    </h1>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        {{ section.subject?.code ?? '—' }}

                        ·

                        {{ section.name }}

                        ·

                        {{ totalSessions }}
                        session{{
                            totalSessions !== 1
                                ? 's'
                                : ''
                        }}
                    </p>
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                @click="exportPdf"
            >
                <FileDown
                    class="mr-2 h-4 w-4"
                />

                Export PDF
            </Button>
        </div>

        <!-- ============================================================ -->
        <!-- Loading -->
        <!-- ============================================================ -->

        <div
            v-if="loading"
            class="flex min-h-[300px] items-center justify-center rounded-xl border"
        >
            <div
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <Loader2
                    class="h-4 w-4 animate-spin"
                />

                Loading attendance summary...
            </div>
        </div>

        <template v-else>
            <!-- ======================================================== -->
            <!-- Controls + Stats -->
            <!-- ======================================================== -->

            <div
                class="flex flex-wrap items-end gap-6"
            >
                <!-- Absence Limit -->

                <div class="grid gap-1.5">
                    <Label
                        for="limit"
                        class="text-sm"
                    >
                        Max Allowed Absences
                    </Label>

                    <div
                        class="flex items-center gap-2"
                    >
                        <Input
                            id="limit"
                            v-model.number="
                                absenceLimit
                            "
                            type="number"
                            min="1"
                            max="20"
                            class="w-20"
                        />

                        <span
                            class="text-sm text-muted-foreground"
                        >
                            sessions
                        </span>
                    </div>
                </div>

                <!-- Statistics -->

                <div
                    class="flex gap-4 text-sm"
                >
                    <!-- Flagged -->

                    <div class="text-center">
                        <p
                            class="text-2xl font-bold text-red-600"
                        >
                            {{ flaggedCount }}
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Flagged students
                        </p>
                    </div>

                    <!-- Class Average -->

                    <div class="text-center">
                        <p
                            class="text-2xl font-bold"
                        >
                            {{ classAvg ?? '—' }}%
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Class average
                        </p>
                    </div>

                    <!-- Students -->

                    <div class="text-center">
                        <p
                            class="text-2xl font-bold"
                        >
                            {{ rows.length }}
                        </p>

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Students
                        </p>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- Flagged Alert -->
            <!-- ======================================================== -->

            <div
                v-if="flaggedCount > 0"
                class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 print:hidden"
            >
                <AlertTriangle
                    class="h-4 w-4 shrink-0"
                />

                {{ flaggedCount }}
                student{{
                    flaggedCount !== 1
                        ? 's'
                        : ''
                }}
                ha{{
                    flaggedCount !== 1
                        ? 've'
                        : 's'
                }}
                reached or exceeded
                {{ absenceLimit }}
                absences.
            </div>

            <!-- ======================================================== -->
            <!-- Empty State -->
            <!-- ======================================================== -->

            <div
                v-if="rows.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                No student data yet. Open
                attendance sessions to start
                recording.
            </div>

            <!-- ======================================================== -->
            <!-- Table -->
            <!-- ======================================================== -->

            <div
                v-else
                class="overflow-hidden rounded-xl border"
            >
                <!-- Print Header -->

                <div
                    class="hidden border-b p-3 text-center print:block"
                >
                    <h2 class="font-bold">
                        ATTENDANCE SUMMARY —
                        {{
                            section.subject?.code ?? ''
                        }}
                        {{ section.name }}
                    </h2>

                    <p class="text-sm">
                        {{
                            section.semester?.name ??
                            ''
                        }}

                        · Total Sessions:
                        {{ totalSessions }}
                    </p>
                </div>

                <table
                    class="w-full text-sm"
                >
                    <!-- Table Header -->

                    <thead
                        class="border-b bg-muted/50"
                    >
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                #
                            </th>

                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Student
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-green-600"
                            >
                                Present
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-orange-500"
                            >
                                Late
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-red-600"
                            >
                                Absent
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-blue-500"
                            >
                                Excused
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Attendance %
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground print:hidden"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>

                    <!-- Table Body -->

                    <tbody class="divide-y">
                        <tr
                            v-for="(
                                row, index
                            ) in rows"
                            :key="
                                row.student.id
                            "
                            class="transition-colors hover:bg-muted/20"
                            :class="
                                row.absent >=
                                absenceLimit
                                    ? 'bg-red-50/40'
                                    : ''
                            "
                        >
                            <!-- Number -->

                            <td
                                class="px-4 py-2.5 text-center text-xs text-muted-foreground"
                            >
                                {{ index + 1 }}
                            </td>

                            <!-- Student -->

                            <td
                                class="px-4 py-2.5"
                            >
                                <p
                                    class="font-medium"
                                >
                                    {{
                                        row
                                            .student
                                            .last_name
                                    }},
                                    {{
                                        row
                                            .student
                                            .first_name
                                    }}

                                    <span
                                        v-if="
                                            row
                                                .student
                                                .middle_name
                                        "
                                    >
                                        {{
                                            row
                                                .student
                                                .middle_name
                                        }}
                                    </span>
                                </p>

                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    Student No:
                                    {{
                                        row
                                            .student
                                            .student_no ??
                                        'No student number'
                                    }}
                                </p>
                            </td>

                            <!-- Present -->

                            <td
                                class="px-4 py-2.5 text-center font-medium text-green-600"
                            >
                                {{ row.present }}
                            </td>

                            <!-- Late -->

                            <td
                                class="px-4 py-2.5 text-center font-medium text-orange-500"
                            >
                                {{ row.late }}
                            </td>

                            <!-- Absent -->

                            <td
                                class="px-4 py-2.5 text-center font-bold"
                                :class="
                                    row.absent >=
                                    absenceLimit
                                        ? 'text-red-600'
                                        : ''
                                "
                            >
                                {{ row.absent }}
                            </td>

                            <!-- Excused -->

                            <td
                                class="px-4 py-2.5 text-center text-blue-500"
                            >
                                {{ row.excused }}
                            </td>

                            <!-- Attendance Percentage -->

                            <td
                                class="px-4 py-2.5 text-center"
                                :class="
                                    percentageColor(
                                        row.percentage,
                                    )
                                "
                            >
                                <div
                                    v-if="
                                        row.percentage !==
                                        null
                                    "
                                    class="flex flex-col items-center gap-0.5"
                                >
                                    <span>
                                        {{
                                            row.percentage.toFixed(
                                                1,
                                            )
                                        }}%
                                    </span>

                                    <div
                                        class="h-1 w-20 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full"
                                            :class="
                                                row.percentage >=
                                                80
                                                    ? 'bg-green-500'
                                                    : row.percentage >=
                                                        60
                                                      ? 'bg-orange-400'
                                                      : 'bg-red-500'
                                            "
                                            :style="{
                                                width: `${Math.min(
                                                    row.percentage,
                                                    100,
                                                )}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Status -->

                            <td
                                class="px-4 py-2.5 text-center print:hidden"
                            >
                                <Badge
                                    :variant="
                                        statusBadge(
                                            row,
                                        ).variant
                                    "
                                >
                                    {{
                                        statusBadge(
                                            row,
                                        ).label
                                    }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ======================================================== -->
            <!-- Footer Note -->
            <!-- ======================================================== -->

            <p
                class="text-xs text-muted-foreground print:hidden"
            >
                Attendance % = (Present + Late) /
                Total Sessions. Adjust "Max Allowed
                Absences" to change the flagging
                threshold.
            </p>
        </template>
    </div>
</template>