<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Users, BookOpen, Plus, Trash2, Search, Layers, ClipboardList, BarChart2, CalendarCheck } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Student = {
    id: number;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
};

type Enrollment = {
    id: number;
    status: string;
    student: Student;
};

type Section = {
    id: number;
    name: string;
    schedule: string | null;
    room: string | null;
    semester: { id: number; name: string; school_year: string };
    subject: { id: number; code: string; name: string };
    faculty: { id: number; name: string };
};

const props = defineProps<{
    section: Section;
    enrollments: Enrollment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Sections', href: '/sections' },
        ],
    },
});

// ── Enrollment search ──────────────────────────────────────────────────────
type SearchResult = { id: number; student_no: string; first_name: string; last_name: string; course: string; year_level: number };

const enrollForm = useForm({ student_no: '' });
const bulkForm = useForm({ student_nos: '' });
const enrollTab = ref<'single' | 'bulk'>('single');

function submitBulk() {
    bulkForm.post(`/sections/${props.section.id}/bulk-enroll`, {
        onSuccess: () => { bulkForm.reset(); },
    });
}
const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const selectedStudent = ref<SearchResult | null>(null);
const showDropdown = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function onSearchInput() {
    selectedStudent.value = null;
    enrollForm.student_no = '';
    const q = searchQuery.value.trim();
    if (searchTimer) clearTimeout(searchTimer);
    if (q.length < 1) { searchResults.value = []; showDropdown.value = false; return; }
    searchTimer = setTimeout(async () => {
        const res = await fetch(`/sections/${props.section.id}/students/search?q=${encodeURIComponent(q)}`);
        searchResults.value = await res.json();
        showDropdown.value = true;
    }, 250);
}

function selectStudent(s: SearchResult) {
    selectedStudent.value = s;
    enrollForm.student_no = s.student_no;
    searchQuery.value = `${s.student_no} — ${s.last_name}, ${s.first_name}`;
    showDropdown.value = false;
}

function onSearchBlur() {
    setTimeout(() => { showDropdown.value = false; }, 150);
}

function onSearchFocus() {
    if (searchResults.value.length) showDropdown.value = true;
}

function clearSelection() {
    selectedStudent.value = null;
    enrollForm.student_no = '';
    searchQuery.value = '';
    searchResults.value = [];
}

function enrollStudent() {
    enrollForm.post(`/sections/${props.section.id}/enroll`, {
        onSuccess: () => { clearSelection(); },
    });
}

// ── Roster search ──────────────────────────────────────────────────────────
const rosterSearch = ref('');

const filteredEnrollments = computed(() => {
    const q = rosterSearch.value.toLowerCase().trim();
    if (!q) return props.enrollments;
    return props.enrollments.filter((e) =>
        e.student.student_no.toLowerCase().includes(q) ||
        e.student.first_name.toLowerCase().includes(q) ||
        e.student.last_name.toLowerCase().includes(q)
    );
});

function unenroll(enrollmentId: number, studentName: string) {
    if (confirm(`Remove ${studentName} from this section?`)) {
        router.delete(`/enrollments/${enrollmentId}`);
    }
}
</script>

<template>
    <Head :title="section.name" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ section.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} — {{ section.subject.name }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/modules`">
                        <Layers class="mr-1.5 h-3.5 w-3.5" />
                        Modules
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/assignments`">
                        <ClipboardList class="mr-1.5 h-3.5 w-3.5" />
                        Assignments
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/attendance`">
                        <CalendarCheck class="mr-1.5 h-3.5 w-3.5" />
                        Attendance
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/class-record`">
                        <BarChart2 class="mr-1.5 h-3.5 w-3.5" />
                        Class Record
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/edit`">Edit Section</Link>
                </Button>
            </div>
        </div>

        <!-- Section Info -->
        <div class="grid gap-3 rounded-xl border p-4 sm:grid-cols-3">
            <div>
                <p class="text-xs text-muted-foreground">Semester</p>
                <p class="font-medium">{{ section.semester.name }}</p>
                <p class="text-xs text-muted-foreground">{{ section.semester.school_year }}</p>
            </div>
            <div v-if="section.schedule">
                <p class="text-xs text-muted-foreground">Schedule</p>
                <p class="font-medium">{{ section.schedule }}</p>
            </div>
            <div v-if="section.room">
                <p class="text-xs text-muted-foreground">Room</p>
                <p class="font-medium">{{ section.room }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Faculty</p>
                <p class="font-medium">{{ section.faculty.name }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground">Enrolled</p>
                <p class="font-medium">{{ enrollments.length }} student{{ enrollments.length !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        <!-- Enroll Student -->
        <div class="rounded-xl border p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Enroll Students</h2>
                <div class="flex rounded-lg border overflow-hidden text-sm">
                    <button
                        type="button"
                        class="px-3 py-1.5 transition-colors"
                        :class="enrollTab === 'single' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted/50'"
                        @click="enrollTab = 'single'"
                    >Single</button>
                    <button
                        type="button"
                        class="px-3 py-1.5 transition-colors"
                        :class="enrollTab === 'bulk' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted/50'"
                        @click="enrollTab = 'bulk'"
                    >Bulk</button>
                </div>
            </div>

            <!-- Single enroll -->
            <div v-if="enrollTab === 'single'" class="flex gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                    <Input
                        v-model="searchQuery"
                        class="pl-9"
                        placeholder="Search by student no. or name…"
                        autocomplete="off"
                        @input="onSearchInput"
                        @blur="onSearchBlur"
                        @focus="onSearchFocus"
                    />
                    <div v-if="showDropdown && searchResults.length" class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border bg-popover shadow-lg">
                        <button
                            v-for="s in searchResults"
                            :key="s.id"
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-muted/50 first:rounded-t-xl last:rounded-b-xl"
                            @mousedown.prevent="selectStudent(s)"
                        >
                            <span class="font-mono text-xs text-muted-foreground w-24 shrink-0">{{ s.student_no }}</span>
                            <span class="font-medium">{{ s.last_name }}, {{ s.first_name }}</span>
                            <span class="ml-auto text-xs text-muted-foreground shrink-0">{{ s.course }} · Year {{ s.year_level }}</span>
                        </button>
                    </div>
                    <div v-if="showDropdown && searchResults.length === 0 && searchQuery.length >= 1" class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border bg-popover px-4 py-3 text-sm text-muted-foreground shadow-lg">
                        No matching students found.
                    </div>
                    <InputError :message="enrollForm.errors.student_no" class="mt-1" />
                </div>
                <div v-if="selectedStudent" class="flex items-center gap-2 rounded-lg border bg-muted/30 px-3 text-sm shrink-0">
                    <span class="font-medium">{{ selectedStudent.last_name }}, {{ selectedStudent.first_name }}</span>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="clearSelection">✕</button>
                </div>
                <Button :disabled="!selectedStudent || enrollForm.processing" @click="enrollStudent">
                    <Plus class="mr-2 h-4 w-4" />
                    Enroll
                </Button>
            </div>

            <!-- Bulk enroll -->
            <div v-else class="space-y-3">
                <textarea
                    v-model="bulkForm.student_nos"
                    rows="5"
                    placeholder="Paste student IDs, one per line (e.g.):&#10;2020-00001&#10;2020-00002&#10;2020-00003"
                    class="flex min-h-[110px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"
                />
                <div class="flex items-center gap-3">
                    <Button :disabled="!bulkForm.student_nos.trim() || bulkForm.processing" @click="submitBulk">
                        <Plus class="mr-2 h-4 w-4" />
                        Enroll All
                    </Button>
                    <p class="text-xs text-muted-foreground">Separate IDs by new lines, commas, or semicolons.</p>
                </div>
                <InputError :message="bulkForm.errors.student_nos" />
            </div>
        </div>

        <!-- Student List -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold flex items-center gap-2">
                    <Users class="h-4 w-4" />
                    Enrolled Students
                </h2>
                <div class="relative w-56">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="rosterSearch" placeholder="Search student..." class="pl-9" />
                </div>
            </div>

            <div v-if="enrollments.length === 0" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                No students enrolled yet.
            </div>

            <div v-else class="overflow-hidden rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">#</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student No.</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Course / Year</th>
                            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(enrollment, i) in filteredEnrollments"
                            :key="enrollment.id"
                            class="hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ enrollment.student.student_no }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/students/${enrollment.student.id}`"
                                    class="font-medium hover:underline"
                                >
                                    {{ enrollment.student.last_name }}, {{ enrollment.student.first_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ enrollment.student.course }} — Year {{ enrollment.student.year_level }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="enrollment.status === 'active' ? 'default' : 'secondary'">
                                    {{ enrollment.status }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="unenroll(enrollment.id, `${enrollment.student.first_name} ${enrollment.student.last_name}`)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
