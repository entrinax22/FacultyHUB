<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Trash2, Lock, BarChart2, Users, CheckCircle2, XCircle, Clock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Session = {
    id: number; date: string; topic: string | null; is_closed: boolean;
    records_count: number; present_count: number; absent_count: number; late_count: number;
};
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

const props = defineProps<{ section: Section; sessions: Session[]; studentCount: number }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Attendance', href: '#' },
        ],
    },
});

const showForm = ref(false);
const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    topic: '',
});

function openSession() {
    form.post(`/sections/${props.section.id}/attendance`, {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function deleteSession(id: number) {
    if (confirm('Delete this attendance session? All records will be removed.')) {
        router.delete(`/attendance-sessions/${id}`);
    }
}

function closeSession(id: number) {
    if (confirm('Close this session? It will become read-only.')) {
        router.post(`/attendance-sessions/${id}/close`);
    }
}

function attendanceRate(s: Session): number {
    if (!props.studentCount) return 0;
    return Math.round(((s.present_count + s.late_count) / props.studentCount) * 100);
}
</script>

<template>
    <Head :title="`Attendance — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Attendance</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/attendance/summary`">
                        <BarChart2 class="mr-1.5 h-4 w-4" />
                        Summary
                    </Link>
                </Button>
                <Button size="sm" @click="showForm = !showForm">
                    <Plus class="mr-2 h-4 w-4" />
                    Open Session
                </Button>
            </div>
        </div>

        <!-- New session form -->
        <div v-if="showForm" class="rounded-xl border p-4 space-y-4 bg-muted/10">
            <h2 class="font-medium text-sm">New Attendance Session</h2>
            <div class="flex flex-wrap gap-4">
                <div class="grid gap-1.5">
                    <Label for="date" class="text-xs">Date</Label>
                    <Input id="date" type="date" v-model="form.date" class="w-44" required />
                    <InputError :message="form.errors.date" />
                </div>
                <div class="grid flex-1 gap-1.5">
                    <Label for="topic" class="text-xs">Topic <span class="text-muted-foreground">(optional)</span></Label>
                    <Input id="topic" v-model="form.topic" placeholder="e.g. Introduction to Arrays" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button :disabled="form.processing" @click="openSession">Open Session</Button>
                <Button variant="ghost" @click="showForm = false">Cancel</Button>
            </div>
            <p class="text-xs text-muted-foreground">All enrolled students will be pre-marked as Present.</p>
        </div>

        <!-- Stats bar -->
        <div v-if="sessions.length" class="grid grid-cols-3 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ sessions.length }}</p>
                <p class="text-xs text-muted-foreground">Sessions</p>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold">{{ studentCount }}</p>
                <p class="text-xs text-muted-foreground">Students</p>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <p class="text-2xl font-bold text-green-600">
                    {{ sessions.length ? Math.round(sessions.reduce((a, s) => a + attendanceRate(s), 0) / sessions.length) : 0 }}%
                </p>
                <p class="text-xs text-muted-foreground">Avg. Attendance</p>
            </div>
        </div>

        <!-- Session list -->
        <div v-if="sessions.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No attendance sessions yet. Open your first session above.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Topic</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Present</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Late</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Absent</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Rate</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="s in sessions" :key="s.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3 font-medium">{{ new Date(s.date).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ s.topic || '—' }}</td>
                        <td class="px-4 py-3 text-center text-green-600 font-medium">{{ s.present_count }}</td>
                        <td class="px-4 py-3 text-center text-orange-500 font-medium">{{ s.late_count }}</td>
                        <td class="px-4 py-3 text-center text-red-600 font-medium">{{ s.absent_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full bg-green-500" :style="{ width: `${attendanceRate(s)}%` }" />
                                </div>
                                <span class="text-xs">{{ attendanceRate(s) }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Badge :variant="s.is_closed ? 'secondary' : 'default'">
                                {{ s.is_closed ? 'Closed' : 'Open' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/sections/${section.id}/attendance/${s.id}`">
                                        {{ s.is_closed ? 'View' : 'Take Attendance' }}
                                    </Link>
                                </Button>
                                <Button v-if="!s.is_closed" variant="ghost" size="sm" title="Close session" @click="closeSession(s.id)">
                                    <Lock class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="deleteSession(s.id)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
