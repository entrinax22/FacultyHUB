<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, Save, CheckCircle2, XCircle, Clock, AlertCircle, Lock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Student = { id: number; student_no: string; first_name: string; last_name: string };
type Record = { id: number; student_id: number; status: string; remarks: string | null };
type AttendanceSession = { id: number; date: string; topic: string | null; is_closed: boolean };
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
};

type StudentRow = { student: Student; record: Record | null };

const props = defineProps<{
    section: Section;
    session: AttendanceSession;
    students: StudentRow[];
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

type StatusMap = Record<number, string>;
type RemarksMap = Record<number, string>;

const statuses = ref<StatusMap>(
    Object.fromEntries(props.students.map(r => [r.student.id, r.record?.status ?? 'present']))
);
const remarks = ref<RemarksMap>(
    Object.fromEntries(props.students.map(r => [r.student.id, r.record?.remarks ?? '']))
);
const saving = ref(false);

const statusOptions = [
    { value: 'present', label: 'P', title: 'Present', color: 'bg-green-500 text-white' },
    { value: 'late',    label: 'L', title: 'Late',    color: 'bg-orange-400 text-white' },
    { value: 'absent',  label: 'A', title: 'Absent',  color: 'bg-red-500 text-white' },
    { value: 'excused', label: 'E', title: 'Excused', color: 'bg-blue-400 text-white' },
];

function setStatus(studentId: number, status: string) {
    if (!props.session.is_closed) statuses.value[studentId] = status;
}

function markAll(status: string) {
    if (props.session.is_closed) return;
    router.post(`/attendance-sessions/${props.session.id}/mark-all`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            props.students.forEach(r => { statuses.value[r.student.id] = status; });
        },
    });
}

function save() {
    if (props.session.is_closed) return;
    saving.value = true;
    const records = props.students.map(r => ({
        student_id: r.student.id,
        status: statuses.value[r.student.id] ?? 'present',
        remarks: remarks.value[r.student.id] ?? '',
    }));
    router.post(`/attendance-sessions/${props.session.id}/bulk`, { records }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}

function close() {
    if (confirm('Close this session? It will become read-only.')) {
        router.post(`/attendance-sessions/${props.session.id}/close`);
    }
}

const summary = computed(() => {
    const counts = { present: 0, late: 0, absent: 0, excused: 0 };
    Object.values(statuses.value).forEach(s => { counts[s as keyof typeof counts]++; });
    return counts;
});

function statusButtonClass(studentId: number, val: string): string {
    const active = statuses.value[studentId] === val;
    const opt = statusOptions.find(o => o.value === val)!;
    return active
        ? `${opt.color} border-transparent`
        : 'bg-transparent text-muted-foreground border-input hover:bg-muted/40';
}
</script>

<template>
    <Head :title="`Attendance — ${new Date(session.date).toLocaleDateString()}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="`/sections/${section.id}/attendance`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ new Date(session.date).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ section.subject.code }} · {{ section.name }}
                        <span v-if="session.topic"> · {{ session.topic }}</span>
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Badge v-if="session.is_closed" variant="secondary">Closed</Badge>
                <template v-else>
                    <Button variant="outline" size="sm" @click="close">
                        <Lock class="mr-1.5 h-4 w-4" />
                        Close Session
                    </Button>
                    <Button size="sm" :disabled="saving" @click="save">
                        <Save class="mr-1.5 h-4 w-4" />
                        {{ saving ? 'Saving…' : 'Save Attendance' }}
                    </Button>
                </template>
            </div>
        </div>

        <!-- Summary + bulk actions -->
        <div class="flex flex-wrap items-center gap-4">
            <!-- Status counts -->
            <div class="flex gap-3 text-sm">
                <span class="flex items-center gap-1 text-green-600"><span class="font-bold">{{ summary.present }}</span> Present</span>
                <span class="flex items-center gap-1 text-orange-500"><span class="font-bold">{{ summary.late }}</span> Late</span>
                <span class="flex items-center gap-1 text-red-600"><span class="font-bold">{{ summary.absent }}</span> Absent</span>
                <span class="flex items-center gap-1 text-blue-500"><span class="font-bold">{{ summary.excused }}</span> Excused</span>
            </div>

            <!-- Bulk mark -->
            <div v-if="!session.is_closed" class="ml-auto flex items-center gap-2">
                <span class="text-xs text-muted-foreground">Mark all:</span>
                <Button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    variant="outline"
                    size="sm"
                    class="h-7 text-xs"
                    @click="markAll(opt.value)"
                >
                    {{ opt.title }}
                </Button>
            </div>
        </div>

        <!-- Attendance table -->
        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">#</th>
                        <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">Student</th>
                        <th class="px-4 py-2.5 text-center font-medium text-muted-foreground">Status</th>
                        <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="(row, i) in students"
                        :key="row.student.id"
                        class="hover:bg-muted/20 transition-colors"
                        :class="{
                            'bg-red-50/30': statuses[row.student.id] === 'absent',
                            'bg-orange-50/30': statuses[row.student.id] === 'late',
                            'bg-blue-50/20': statuses[row.student.id] === 'excused',
                        }"
                    >
                        <td class="px-4 py-2 text-center text-xs text-muted-foreground">{{ i + 1 }}</td>
                        <td class="px-4 py-2">
                            <p class="font-medium">{{ row.student.last_name }}, {{ row.student.first_name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ row.student.student_no }}</p>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-center gap-1">
                                <button
                                    v-for="opt in statusOptions"
                                    :key="opt.value"
                                    type="button"
                                    :title="opt.title"
                                    :disabled="session.is_closed"
                                    class="h-8 w-8 rounded-md border text-xs font-bold transition-colors disabled:opacity-60"
                                    :class="statusButtonClass(row.student.id, opt.value)"
                                    @click="setStatus(row.student.id, opt.value)"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <Input
                                v-model="remarks[row.student.id]"
                                placeholder="Optional remark"
                                :disabled="session.is_closed"
                                class="h-7 text-xs"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Save button (bottom) -->
        <div v-if="!session.is_closed" class="flex gap-3">
            <Button :disabled="saving" @click="save">
                <Save class="mr-1.5 h-4 w-4" />
                {{ saving ? 'Saving…' : 'Save Attendance' }}
            </Button>
            <p class="self-center text-xs text-muted-foreground">P = Present · L = Late · A = Absent · E = Excused</p>
        </div>
    </div>
</template>
