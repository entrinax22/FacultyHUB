<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

import {
    ArrowLeft,
    Save,
    Lock,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
};

type AttendanceRecord = {
    id: string;
    student_id: string;
    status: string;
    remarks: string | null;
};

type AttendanceSession = {
    id: string;
    date: string;
    topic: string | null;
    is_closed: boolean;
};

type Section = {
    id: string;
    name: string;
    subject: {
        code: string;
        name: string;
    };
};

type StudentRow = {
    student: Student;
    record: AttendanceRecord | null;
};

type AttendanceSessionDataResponse = {
    success: boolean;
    message: string;
    data: {
        section: Section;
        session: AttendanceSession;
        students: StudentRow[];
        records: Record<string, AttendanceRecord>;
    };
};

const props = defineProps<{
    section: Section;
    session: AttendanceSession;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Attendance', href: '#' },
            { title: 'Session', href: '#' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const students = ref<StudentRow[]>([]);

const loading = ref(true);
const saving = ref(false);
const bulkUpdating = ref(false);

const session = ref<AttendanceSession>(props.session);

/*
|--------------------------------------------------------------------------
| Attendance state
|--------------------------------------------------------------------------
*/

type StatusMap = Record<string, string>;
type RemarksMap = Record<string, string>;

const statuses = ref<StatusMap>({});
const remarks = ref<RemarksMap>({});

/*
|--------------------------------------------------------------------------
| Status options
|--------------------------------------------------------------------------
*/

const statusOptions = [
    {
        value: 'present',
        label: 'P',
        title: 'Present',
        color: 'bg-green-500 text-white',
    },
    {
        value: 'late',
        label: 'L',
        title: 'Late',
        color: 'bg-orange-400 text-white',
    },
    {
        value: 'absent',
        label: 'A',
        title: 'Absent',
        color: 'bg-red-500 text-white',
    },
    {
        value: 'excused',
        label: 'E',
        title: 'Excused',
        color: 'bg-blue-400 text-white',
    },
];

/*
|--------------------------------------------------------------------------
| Load session data
|--------------------------------------------------------------------------
*/

async function loadSession() {
    loading.value = true;

    try {
        const response =
            await axios.get<AttendanceSessionDataResponse>(
                `/sections/${props.section.id}/attendance/${props.session.id}/data`,
            );

        const data = response.data.data;

        students.value = Array.isArray(data?.students)
            ? data.students
            : [];

        if (data?.session) {
            session.value = data.session;
        }

        initializeAttendance();
    } catch (error) {
        students.value = [];

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Initialize attendance state
|--------------------------------------------------------------------------
*/

function initializeAttendance() {
    const newStatuses: StatusMap = {};
    const newRemarks: RemarksMap = {};

    students.value.forEach((row) => {
        const studentId = row.student.id;

        newStatuses[studentId] =
            row.record?.status ?? 'present';

        newRemarks[studentId] =
            row.record?.remarks ?? '';
    });

    statuses.value = newStatuses;
    remarks.value = newRemarks;
}

/*
|--------------------------------------------------------------------------
| Set individual status
|--------------------------------------------------------------------------
*/

function setStatus(
    studentId: string,
    status: string,
) {
    if (session.value.is_closed) {
        return;
    }

    statuses.value[studentId] = status;
}

/*
|--------------------------------------------------------------------------
| Mark all students
|--------------------------------------------------------------------------
*/

async function markAll(status: string) {
    if (
        session.value.is_closed ||
        bulkUpdating.value
    ) {
        return;
    }

    bulkUpdating.value = true;

    try {
        const response = await axios.post(
            `/attendance-sessions/${session.value.id}/mark-all`,
            {
                status,
            },
        );

        students.value.forEach((row) => {
            statuses.value[row.student.id] = status;
        });

        showApiToast(response);
    } catch (error) {
        showApiError(error);
    } finally {
        bulkUpdating.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Save attendance
|--------------------------------------------------------------------------
*/

async function save() {
    if (
        session.value.is_closed ||
        saving.value
    ) {
        return;
    }

    saving.value = true;

    const records = students.value.map((row) => ({
        student_id: row.student.id,
        status:
            statuses.value[row.student.id] ??
            'present',
        remarks:
            remarks.value[row.student.id] ?? '',
    }));

    try {
        const response = await axios.post(
            `/attendance-sessions/${session.value.id}/bulk`,
            {
                records,
            },
        );

        showApiToast(response);

        await loadSession();
    } catch (error) {
        showApiError(error);
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Close session
|--------------------------------------------------------------------------
*/

async function closeSession() {
    if (session.value.is_closed) {
        return;
    }

    if (
        !confirm(
            'Close this session? It will become read-only.',
        )
    ) {
        return;
    }

    try {
        const response = await axios.post(
            `/attendance-sessions/${session.value.id}/close`,
        );

        showApiToast(response);

        session.value.is_closed = true;
    } catch (error) {
        showApiError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

const summary = computed(() => {
    const counts = {
        present: 0,
        late: 0,
        absent: 0,
        excused: 0,
    };

    Object.values(statuses.value).forEach((status) => {
        if (
            status in counts
        ) {
            counts[
                status as keyof typeof counts
            ]++;
        }
    });

    return counts;
});

/*
|--------------------------------------------------------------------------
| Status button styling
|--------------------------------------------------------------------------
*/

function statusButtonClass(
    studentId: string,
    value: string,
): string {
    const active =
        statuses.value[studentId] === value;

    const option = statusOptions.find(
        (item) => item.value === value,
    );

    if (!option) {
        return 'bg-transparent text-muted-foreground border-input hover:bg-muted/40';
    }

    return active
        ? `${option.color} border-transparent`
        : 'bg-transparent text-muted-foreground border-input hover:bg-muted/40';
}

/*
|--------------------------------------------------------------------------
| Date formatting
|--------------------------------------------------------------------------
*/

function formatDate(
    date: string,
    long = false,
): string {
    return new Date(date).toLocaleDateString(
        'en-PH',
        long
            ? {
                  weekday: 'long',
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
              }
            : {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric',
              },
    );
}

/*
|--------------------------------------------------------------------------
| Initial load
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSession();
});
</script>

<template>
    <Head
        :title="`Attendance — ${formatDate(session.date)}`"
    />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">

        <!-- Header -->
        <div
            class="flex items-start justify-between"
        >
            <div class="flex items-start gap-3">

                <!-- Back -->
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="-ml-2 mt-0.5"
                >
                    <Link
                        :href="
                            `/sections/${section.id}/attendance`
                        "
                    >
                        <ArrowLeft
                            class="h-4 w-4"
                        />
                    </Link>
                </Button>

                <!-- Session information -->
                <div>
                    <h1
                        class="text-xl font-semibold"
                    >
                        {{
                            formatDate(
                                session.date,
                                true,
                            )
                        }}
                    </h1>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        {{ section.subject.code }}
                        ·
                        {{ section.name }}

                        <span
                            v-if="session.topic"
                        >
                            · {{ session.topic }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Header actions -->
            <div class="flex gap-2">

                <Badge
                    v-if="session.is_closed"
                    variant="secondary"
                >
                    Closed
                </Badge>

                <template v-else>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                            saving ||
                            bulkUpdating
                        "
                        @click="closeSession"
                    >
                        <Lock
                            class="mr-1.5 h-4 w-4"
                        />
                        Close Session
                    </Button>

                    <Button
                        size="sm"
                        :disabled="
                            saving ||
                            bulkUpdating
                        "
                        @click="save"
                    >
                        <Save
                            class="mr-1.5 h-4 w-4"
                        />

                        {{
                            saving
                                ? 'Saving…'
                                : 'Save Attendance'
                        }}
                    </Button>
                </template>
            </div>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded-xl border p-12 text-center text-sm text-muted-foreground"
        >
            Loading attendance...
        </div>

        <template v-else>

            <!-- Summary + bulk actions -->
            <div
                class="flex flex-wrap items-center gap-4"
            >
                <!-- Status counts -->
                <div
                    class="flex flex-wrap gap-3 text-sm"
                >
                    <span
                        class="flex items-center gap-1 text-green-600"
                    >
                        <span class="font-bold">
                            {{ summary.present }}
                        </span>
                        Present
                    </span>

                    <span
                        class="flex items-center gap-1 text-orange-500"
                    >
                        <span class="font-bold">
                            {{ summary.late }}
                        </span>
                        Late
                    </span>

                    <span
                        class="flex items-center gap-1 text-red-600"
                    >
                        <span class="font-bold">
                            {{ summary.absent }}
                        </span>
                        Absent
                    </span>

                    <span
                        class="flex items-center gap-1 text-blue-500"
                    >
                        <span class="font-bold">
                            {{ summary.excused }}
                        </span>
                        Excused
                    </span>
                </div>

                <!-- Bulk actions -->
                <div
                    v-if="!session.is_closed"
                    class="ml-auto flex items-center gap-2"
                >
                    <span
                        class="text-xs text-muted-foreground"
                    >
                        Mark all:
                    </span>

                    <Button
                        v-for="option in statusOptions"
                        :key="option.value"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="
                            bulkUpdating ||
                            saving
                        "
                        @click="
                            markAll(option.value)
                        "
                    >
                        {{ option.title }}
                    </Button>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="students.length === 0"
                class="rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground"
            >
                No active students are enrolled
                in this section.
            </div>

            <!-- Attendance table -->
            <div
                v-else
                class="overflow-hidden rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50"
                    >
                        <tr>
                            <th
                                class="px-4 py-2.5 text-left font-medium text-muted-foreground"
                            >
                                #
                            </th>

                            <th
                                class="px-4 py-2.5 text-left font-medium text-muted-foreground"
                            >
                                Student
                            </th>

                            <th
                                class="px-4 py-2.5 text-center font-medium text-muted-foreground"
                            >
                                Status
                            </th>

                            <th
                                class="px-4 py-2.5 text-left font-medium text-muted-foreground"
                            >
                                Remarks
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="(row, index) in students"
                            :key="row.student.id"
                            class="transition-colors hover:bg-muted/20"
                            :class="{
                                'bg-red-50/30':
                                    statuses[
                                        row.student.id
                                    ] === 'absent',

                                'bg-orange-50/30':
                                    statuses[
                                        row.student.id
                                    ] === 'late',

                                'bg-blue-50/20':
                                    statuses[
                                        row.student.id
                                    ] === 'excused',
                            }"
                        >
                            <!-- Number -->
                            <td
                                class="px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ index + 1 }}
                            </td>

                            <!-- Student -->
                            <td class="px-4 py-2">
                                <p class="font-medium">
                                    {{
                                        row.student.last_name
                                    }},
                                    {{
                                        row.student.first_name
                                    }}
                                </p>

                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        row.student.student_no
                                    }}
                                </p>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-2">
                                <div
                                    class="flex justify-center gap-1"
                                >
                                    <button
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        type="button"
                                        :title="option.title"
                                        :disabled="
                                            session.is_closed ||
                                            saving ||
                                            bulkUpdating
                                        "
                                        class="h-8 w-8 rounded-md border text-xs font-bold transition-colors disabled:opacity-60"
                                        :class="
                                            statusButtonClass(
                                                row.student.id,
                                                option.value,
                                            )
                                        "
                                        @click="
                                            setStatus(
                                                row.student.id,
                                                option.value,
                                            )
                                        "
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </td>

                            <!-- Remarks -->
                            <td class="px-4 py-2">
                                <Input
                                    v-model="
                                        remarks[
                                            row.student.id
                                        ]
                                    "
                                    placeholder="Optional remark"
                                    :disabled="
                                        session.is_closed ||
                                        saving ||
                                        bulkUpdating
                                    "
                                    class="h-7 text-xs"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom save -->
            <div
                v-if="!session.is_closed"
                class="flex gap-3"
            >
                <Button
                    :disabled="
                        saving ||
                        bulkUpdating ||
                        students.length === 0
                    "
                    @click="save"
                >
                    <Save
                        class="mr-1.5 h-4 w-4"
                    />

                    {{
                        saving
                            ? 'Saving…'
                            : 'Save Attendance'
                    }}
                </Button>

                <p
                    class="self-center text-xs text-muted-foreground"
                >
                    P = Present · L = Late · A =
                    Absent · E = Excused
                </p>
            </div>
        </template>
    </div>
</template>