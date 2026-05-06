<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, AlertTriangle, Download } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Student = { id: number; student_no: string; first_name: string; last_name: string };
type Row = {
    student: Student;
    present: number; late: number; absent: number; excused: number;
    attended: number; total: number; percentage: number | null;
};
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

const props = defineProps<{ section: Section; totalSessions: number; rows: Row[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Attendance', href: '#' },
            { title: 'Summary', href: '#' },
        ],
    },
});

const absenceLimit = ref(3);

function statusBadge(row: Row): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
    if (row.absent >= absenceLimit.value) return { label: 'Flagged', variant: 'destructive' };
    if (row.absent >= absenceLimit.value - 1) return { label: 'Warning', variant: 'outline' };
    return { label: 'Good', variant: 'default' };
}

function percentageColor(pct: number | null): string {
    if (pct === null) return 'text-muted-foreground';
    if (pct >= 80) return 'text-green-600 font-semibold';
    if (pct >= 60) return 'text-orange-500 font-semibold';
    return 'text-red-600 font-semibold';
}

const flaggedCount = computed(() => props.rows.filter(r => r.absent >= absenceLimit.value).length);
const classAvg = computed(() => {
    const valid = props.rows.filter(r => r.percentage !== null);
    if (!valid.length) return null;
    return (valid.reduce((a, r) => a + (r.percentage ?? 0), 0) / valid.length).toFixed(1);
});

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Attendance Summary — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="`/sections/${section.id}/attendance`"><ArrowLeft class="h-4 w-4" /></Link>
                </Button>
                <div>
                    <h1 class="text-xl font-semibold">Attendance Summary</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ section.subject.code }} · {{ section.name }} · {{ totalSessions }} session{{ totalSessions !== 1 ? 's' : '' }}
                    </p>
                </div>
            </div>
            <Button variant="outline" size="sm" @click="print">
                <Download class="mr-1.5 h-4 w-4" />
                Print / Export
            </Button>
        </div>

        <!-- Controls + stats -->
        <div class="flex flex-wrap items-end gap-6">
            <div class="grid gap-1.5">
                <Label for="limit" class="text-sm">Max Allowed Absences</Label>
                <div class="flex items-center gap-2">
                    <Input id="limit" type="number" v-model="absenceLimit" min="1" max="20" class="w-20" />
                    <span class="text-sm text-muted-foreground">sessions</span>
                </div>
            </div>
            <div class="flex gap-4 text-sm">
                <div class="text-center">
                    <p class="text-2xl font-bold text-red-600">{{ flaggedCount }}</p>
                    <p class="text-xs text-muted-foreground">Flagged students</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ classAvg ?? '—' }}%</p>
                    <p class="text-xs text-muted-foreground">Class average</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ rows.length }}</p>
                    <p class="text-xs text-muted-foreground">Students</p>
                </div>
            </div>
        </div>

        <!-- Flagged alert -->
        <div v-if="flaggedCount > 0" class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 print:hidden">
            <AlertTriangle class="h-4 w-4 shrink-0" />
            {{ flaggedCount }} student{{ flaggedCount !== 1 ? 's' : '' }} ha{{ flaggedCount !== 1 ? 've' : 's' }} reached or exceeded {{ absenceLimit }} absences.
        </div>

        <!-- Table -->
        <div v-if="rows.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            No student data yet. Open attendance sessions to start recording.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <!-- Print header -->
            <div class="hidden print:block p-3 border-b text-center">
                <h2 class="font-bold">ATTENDANCE SUMMARY — {{ section.subject.code }} {{ section.name }}</h2>
                <p class="text-sm">{{ section.semester.name }} {{ section.semester.school_year }} · Total Sessions: {{ totalSessions }}</p>
            </div>

            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">#</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student</th>
                        <th class="px-4 py-3 text-center font-medium text-green-600">Present</th>
                        <th class="px-4 py-3 text-center font-medium text-orange-500">Late</th>
                        <th class="px-4 py-3 text-center font-medium text-red-600">Absent</th>
                        <th class="px-4 py-3 text-center font-medium text-blue-500">Excused</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Attendance %</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground print:hidden">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="(row, i) in rows"
                        :key="row.student.id"
                        class="hover:bg-muted/20 transition-colors"
                        :class="row.absent >= absenceLimit ? 'bg-red-50/40' : ''"
                    >
                        <td class="px-4 py-2.5 text-center text-xs text-muted-foreground">{{ i + 1 }}</td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium">{{ row.student.last_name }}, {{ row.student.first_name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ row.student.student_no }}</p>
                        </td>
                        <td class="px-4 py-2.5 text-center font-medium text-green-600">{{ row.present }}</td>
                        <td class="px-4 py-2.5 text-center font-medium text-orange-500">{{ row.late }}</td>
                        <td class="px-4 py-2.5 text-center font-bold" :class="row.absent >= absenceLimit ? 'text-red-600' : ''">
                            {{ row.absent }}
                        </td>
                        <td class="px-4 py-2.5 text-center text-blue-500">{{ row.excused }}</td>
                        <td class="px-4 py-2.5 text-center" :class="percentageColor(row.percentage)">
                            <div v-if="row.percentage !== null" class="flex flex-col items-center gap-0.5">
                                <span>{{ row.percentage.toFixed(1) }}%</span>
                                <div class="h-1 w-20 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full"
                                        :class="row.percentage >= 80 ? 'bg-green-500' : row.percentage >= 60 ? 'bg-orange-400' : 'bg-red-500'"
                                        :style="{ width: `${row.percentage}%` }"
                                    />
                                </div>
                            </div>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-center print:hidden">
                            <Badge :variant="statusBadge(row).variant">{{ statusBadge(row).label }}</Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-muted-foreground print:hidden">
            Attendance % = (Present + Late) / Total Sessions. Adjust "Max Allowed Absences" to change the flagging threshold.
        </p>
    </div>
</template>
