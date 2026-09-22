<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';
import {
    Plus,
    Trash2,
    Lock,
    BarChart2,
    Loader2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { showApiToast, showApiError } from '@/lib/flashToast';

type Session = {
    id: string;
    section_id?: string;
    created_by?: string | null;
    date: string;
    topic: string | null;
    is_closed: boolean;
    records_count: number;
    present_count: number;
    absent_count: number;
    late_count: number;
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
        school_year: string;
    } | null;
};

type AttendanceData = {
    section: Section;
    sessions: Session[];
    student_count: number;
};

type AttendanceDataResponse = {
    success: boolean;
    message: string;
    data: AttendanceData;
};

const props = defineProps<{
    section: Section;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Attendance', href: '#' },
        ],
    },
});

const sessions = ref<Session[]>([]);
const studentCount = ref(0);

const loading = ref(true);
const submitting = ref(false);
const showForm = ref(false);

const form = ref({
    date: new Date().toISOString().slice(0, 10),
    topic: '',
});

const errors = ref<{
    date?: string;
    topic?: string;
}>({});

/*
|--------------------------------------------------------------------------
| Load attendance data
|--------------------------------------------------------------------------
*/

async function loadAttendance() {
    loading.value = true;

    try {
        const response = await axios.get<AttendanceDataResponse>(
            `/sections/${props.section.id}/attendance/data`,
        );

        const data = response.data.data;

        sessions.value = Array.isArray(data?.sessions)
            ? data.sessions
            : [];

        studentCount.value = Number(
            data?.student_count ?? 0,
        );
    } catch (error) {
        sessions.value = [];
        studentCount.value = 0;

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Open attendance session
|--------------------------------------------------------------------------
*/

async function openSession() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};

    try {
        const response = await axios.post(
            `/sections/${props.section.id}/attendance`,
            {
                date: form.value.date,
                topic: form.value.topic || null,
            },
        );

        showApiToast(response);

        showForm.value = false;

        form.value = {
            date: new Date().toISOString().slice(0, 10),
            topic: '',
        };

        await loadAttendance();
    } catch (error: any) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        }

        showApiError(error);
    } finally {
        submitting.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Delete attendance session
|--------------------------------------------------------------------------
*/

async function deleteSession(sessionId: string) {
    if (
        !confirm(
            'Delete this attendance session? All records will be removed.',
        )
    ) {
        return;
    }

    try {
        const response = await axios.delete(
            `/attendance-sessions/${sessionId}`,
        );

        showApiToast(response);

        await loadAttendance();
    } catch (error) {
        showApiError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Close attendance session
|--------------------------------------------------------------------------
*/

async function closeSession(sessionId: string) {
    if (
        !confirm(
            'Close this session? It will become read-only.',
        )
    ) {
        return;
    }

    try {
        const response = await axios.post(
            `/attendance-sessions/${sessionId}/close`,
        );

        showApiToast(response);

        await loadAttendance();
    } catch (error) {
        showApiError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Attendance rate
|--------------------------------------------------------------------------
*/

function attendanceRate(session: Session): number {
    if (!studentCount.value) {
        return 0;
    }

    return Math.round(
        (
            (session.present_count + session.late_count) /
            studentCount.value
        ) * 100,
    );
}

/*
|--------------------------------------------------------------------------
| Average attendance
|--------------------------------------------------------------------------
*/

function averageAttendance(): number {
    if (!sessions.value.length) {
        return 0;
    }

    const total = sessions.value.reduce(
        (sum, session) =>
            sum + attendanceRate(session),
        0,
    );

    return Math.round(
        total / sessions.value.length,
    );
}

/*
|--------------------------------------------------------------------------
| Date formatting
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(
        'en-PH',
        {
            weekday: 'short',
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
    loadAttendance();
});
</script>

<template>
    <Head :title="`Attendance — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    Attendance
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ section.subject?.code ?? '—' }}
                    ·
                    {{ section.name }}
                    ·
                    {{ section.semester?.name ?? '—' }}
                </p>
            </div>

            <div class="flex gap-2">

                <!-- Summary -->
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="`/sections/${section.id}/attendance/summary`"
                    >
                        <BarChart2 class="mr-1.5 h-4 w-4" />
                        Summary
                    </Link>
                </Button>

                <!-- Open Session -->
                <Button
                    size="sm"
                    @click="showForm = !showForm"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Open Session
                </Button>
            </div>
        </div>

        <!-- New Session Form -->
        <div
            v-if="showForm"
            class="space-y-4 rounded-xl border bg-muted/10 p-4"
        >
            <h2 class="text-sm font-medium">
                New Attendance Session
            </h2>

            <div class="flex flex-wrap gap-4">

                <!-- Date -->
                <div class="grid gap-1.5">
                    <Label
                        for="date"
                        class="text-xs"
                    >
                        Date
                    </Label>

                    <Input
                        id="date"
                        v-model="form.date"
                        type="date"
                        class="w-44"
                        required
                    />

                    <InputError
                        :message="errors.date"
                    />
                </div>

                <!-- Topic -->
                <div class="grid flex-1 gap-1.5">
                    <Label
                        for="topic"
                        class="text-xs"
                    >
                        Topic
                        <span class="text-muted-foreground">
                            (optional)
                        </span>
                    </Label>

                    <Input
                        id="topic"
                        v-model="form.topic"
                        placeholder="e.g. Introduction to Arrays"
                    />

                    <InputError
                        :message="errors.topic"
                    />
                </div>
            </div>

            <div class="flex gap-2">
                <Button
                    :disabled="submitting"
                    @click="openSession"
                >
                    {{
                        submitting
                            ? 'Opening...'
                            : 'Open Session'
                    }}
                </Button>

                <Button
                    variant="ghost"
                    :disabled="submitting"
                    @click="showForm = false"
                >
                    Cancel
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">
                All enrolled students will be pre-marked as Present.
            </p>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="flex items-center justify-center rounded-xl border p-12 text-sm text-muted-foreground"
        >
            <div class="flex items-center gap-2">
                <Loader2 class="h-4 w-4 animate-spin" />
                Loading attendance sessions...
            </div>
        </div>

        <template v-else>

            <!-- Stats -->
            <div
                v-if="sessions.length"
                class="grid grid-cols-3 gap-3"
            >
                <div class="rounded-xl border p-3 text-center">
                    <p class="text-2xl font-bold">
                        {{ sessions.length }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        Sessions
                    </p>
                </div>

                <div class="rounded-xl border p-3 text-center">
                    <p class="text-2xl font-bold">
                        {{ studentCount }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        Students
                    </p>
                </div>

                <div class="rounded-xl border p-3 text-center">
                    <p class="text-2xl font-bold text-green-600">
                        {{ averageAttendance() }}%
                    </p>

                    <p class="text-xs text-muted-foreground">
                        Avg. Attendance
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="sessions.length === 0"
                class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
            >
                No attendance sessions yet.
                Open your first session above.
            </div>

            <!-- Session List -->
            <div
                v-else
                class="overflow-hidden rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Date
                            </th>

                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Topic
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Present
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Late
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Absent
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Rate
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Status
                            </th>

                            <th
                                class="px-4 py-3 text-right font-medium text-muted-foreground"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="session in sessions"
                            :key="session.id"
                            class="hover:bg-muted/30"
                        >
                            <!-- Date -->
                            <td class="px-4 py-3 font-medium">
                                {{ formatDate(session.date) }}
                            </td>

                            <!-- Topic -->
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ session.topic || '—' }}
                            </td>

                            <!-- Present -->
                            <td
                                class="px-4 py-3 text-center font-medium text-green-600"
                            >
                                {{ session.present_count }}
                            </td>

                            <!-- Late -->
                            <td
                                class="px-4 py-3 text-center font-medium text-orange-500"
                            >
                                {{ session.late_count }}
                            </td>

                            <!-- Absent -->
                            <td
                                class="px-4 py-3 text-center font-medium text-red-600"
                            >
                                {{ session.absent_count }}
                            </td>

                            <!-- Attendance Rate -->
                            <td class="px-4 py-3 text-center">
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <div
                                        class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-green-500"
                                            :style="{
                                                width: `${Math.min(
                                                    attendanceRate(session),
                                                    100,
                                                )}%`,
                                            }"
                                        />
                                    </div>

                                    <span class="text-xs">
                                        {{ attendanceRate(session) }}%
                                    </span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <Badge
                                    :variant="
                                        session.is_closed
                                            ? 'secondary'
                                            : 'default'
                                    "
                                >
                                    {{
                                        session.is_closed
                                            ? 'Closed'
                                            : 'Open'
                                    }}
                                </Badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">

                                    <!-- IMPORTANT:
                                         This goes to the Inertia page,
                                         NOT the /data endpoint.
                                    -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                `/sections/${section.id}/attendance/${session.id}`
                                            "
                                        >
                                            {{
                                                session.is_closed
                                                    ? 'View'
                                                    : 'Take Attendance'
                                            }}
                                        </Link>
                                    </Button>

                                    <!-- Close -->
                                    <Button
                                        v-if="!session.is_closed"
                                        variant="ghost"
                                        size="sm"
                                        title="Close session"
                                        @click="closeSession(session.id)"
                                    >
                                        <Lock class="h-4 w-4" />
                                    </Button>

                                    <!-- Delete -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        title="Delete session"
                                        @click="deleteSession(session.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>