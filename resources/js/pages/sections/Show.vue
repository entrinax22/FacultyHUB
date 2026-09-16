<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Users,
    BookOpen,
    Plus,
    Trash2,
    Search,
    Layers,
    ClipboardList,
    BarChart2,
    CalendarCheck,
    X,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    semester: {
        id: number;
        name: string;
        school_year: string;
    };
    subject: {
        id: number;
        code: string;
        name: string;
    };
    faculty: {
        id: number;
        name: string;
    };
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

// ── Enrollment ─────────────────────────────────────────────────────────────

type SearchResult = {
    id: number;
    student_no: string;
    first_name: string;
    last_name: string;
    course: string;
    year_level: number;
};

const enrollForm = useForm({
    student_no: '',
});

const bulkForm = useForm({
    student_nos: '',
});

const enrollTab = ref<'single' | 'bulk'>('single');

function submitBulk() {
    bulkForm.post(`/sections/${props.section.id}/bulk-enroll`, {
        onSuccess: () => {
            bulkForm.reset();
        },
    });
}

// ── Student Search ─────────────────────────────────────────────────────────

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const selectedStudent = ref<SearchResult | null>(null);
const showDropdown = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function onSearchInput() {
    selectedStudent.value = null;
    enrollForm.student_no = '';

    const q = searchQuery.value.trim();

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (q.length < 1) {
        searchResults.value = [];
        showDropdown.value = false;
        return;
    }

    searchTimer = setTimeout(async () => {
        const res = await fetch(
            `/sections/${props.section.id}/students/search?q=${encodeURIComponent(q)}`,
        );

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
    setTimeout(() => {
        showDropdown.value = false;
    }, 150);
}

function onSearchFocus() {
    if (searchResults.value.length) {
        showDropdown.value = true;
    }
}

function clearSelection() {
    selectedStudent.value = null;
    enrollForm.student_no = '';
    searchQuery.value = '';
    searchResults.value = [];
}

function enrollStudent() {
    enrollForm.post(`/sections/${props.section.id}/enroll`, {
        onSuccess: () => {
            clearSelection();
        },
    });
}

// ── Roster Search ──────────────────────────────────────────────────────────

const rosterSearch = ref('');

const filteredEnrollments = computed(() => {
    const q = rosterSearch.value.toLowerCase().trim();

    if (!q) {
        return props.enrollments;
    }

    return props.enrollments.filter((e) =>
        e.student.student_no.toLowerCase().includes(q) ||
        e.student.first_name.toLowerCase().includes(q) ||
        e.student.last_name.toLowerCase().includes(q),
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

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ============================================================= -->
        <!-- HEADER -->
        <!-- ============================================================= -->

        <div
            class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold sm:text-2xl">
                    {{ section.name }}
                </h1>

                <p
                    class="mt-1 truncate text-xs text-muted-foreground sm:text-sm"
                    :title="`${section.subject.code} — ${section.subject.name}`"
                >
                    {{ section.subject.code }}
                    —
                    {{ section.subject.name }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div
                class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:w-auto lg:flex-wrap"
            >
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link :href="`/sections/${section.id}/modules`">
                        <Layers class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                        <span>Modules</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link :href="`/sections/${section.id}/assignments`">
                        <ClipboardList class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                        <span>Assignments</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link :href="`/sections/${section.id}/attendance`">
                        <CalendarCheck class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                        <span>Attendance</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link :href="`/sections/${section.id}/class-record`">
                        <BarChart2 class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                        <span>Class Record</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="col-span-2 w-full sm:col-span-1 lg:w-auto"
                    as-child
                >
                    <Link :href="`/sections/${section.id}/edit`">
                        Edit Section
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- SECTION INFO -->
        <!-- ============================================================= -->

        <div
            class="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">
                    Semester
                </p>

                <p class="truncate font-medium">
                    {{ section.semester.name }}
                </p>

                <p class="text-xs text-muted-foreground">
                    {{ section.semester.school_year }}
                </p>
            </div>

            <div
                v-if="section.schedule"
                class="min-w-0"
            >
                <p class="text-xs text-muted-foreground">
                    Schedule
                </p>

                <p class="break-words font-medium">
                    {{ section.schedule }}
                </p>
            </div>

            <div
                v-if="section.room"
                class="min-w-0"
            >
                <p class="text-xs text-muted-foreground">
                    Room
                </p>

                <p class="break-words font-medium">
                    {{ section.room }}
                </p>
            </div>

            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">
                    Faculty
                </p>

                <p class="truncate font-medium">
                    {{ section.faculty.name }}
                </p>
            </div>

            <div class="min-w-0">
                <p class="text-xs text-muted-foreground">
                    Enrolled
                </p>

                <p class="font-medium">
                    {{ enrollments.length }}
                    student{{ enrollments.length !== 1 ? 's' : '' }}
                </p>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- ENROLL STUDENTS -->
        <!-- ============================================================= -->

        <div class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
            <!-- Enrollment Header -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="font-semibold">
                    Enroll Students
                </h2>

                <div
                    class="grid w-full grid-cols-2 overflow-hidden rounded-lg border text-sm sm:w-auto"
                >
                    <button
                        type="button"
                        class="px-3 py-2 transition-colors"
                        :class="
                            enrollTab === 'single'
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted/50'
                        "
                        @click="enrollTab = 'single'"
                    >
                        Single
                    </button>

                    <button
                        type="button"
                        class="px-3 py-2 transition-colors"
                        :class="
                            enrollTab === 'bulk'
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted/50'
                        "
                        @click="enrollTab = 'bulk'"
                    >
                        Bulk
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SINGLE ENROLL -->
            <!-- ========================================================= -->

            <div
                v-if="enrollTab === 'single'"
                class="flex flex-col gap-3 lg:flex-row"
            >
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />

                    <Input
                        v-model="searchQuery"
                        class="w-full pl-9"
                        placeholder="Search by student no. or name…"
                        autocomplete="off"
                        @input="onSearchInput"
                        @blur="onSearchBlur"
                        @focus="onSearchFocus"
                    />

                    <!-- Search Dropdown -->
                    <div
                        v-if="showDropdown && searchResults.length"
                        class="absolute left-0 top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border bg-popover shadow-lg"
                    >
                        <button
                            v-for="s in searchResults"
                            :key="s.id"
                            type="button"
                            class="flex w-full flex-col gap-1 px-4 py-3 text-left text-sm transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-3"
                            @mousedown.prevent="selectStudent(s)"
                        >
                            <span
                                class="shrink-0 font-mono text-xs text-muted-foreground sm:w-24"
                            >
                                {{ s.student_no }}
                            </span>

                            <span class="font-medium">
                                {{ s.last_name }}, {{ s.first_name }}
                            </span>

                            <span
                                class="text-xs text-muted-foreground sm:ml-auto"
                            >
                                {{ s.course }} · Year {{ s.year_level }}
                            </span>
                        </button>
                    </div>

                    <!-- No Results -->
                    <div
                        v-if="
                            showDropdown &&
                            searchResults.length === 0 &&
                            searchQuery.length >= 1
                        "
                        class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border bg-popover px-4 py-3 text-sm text-muted-foreground shadow-lg"
                    >
                        No matching students found.
                    </div>

                    <InputError
                        :message="enrollForm.errors.student_no"
                        class="mt-1"
                    />
                </div>

                <!-- Selected Student -->
                <div
                    v-if="selectedStudent"
                    class="flex min-w-0 items-center justify-between gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-sm lg:max-w-xs"
                >
                    <span class="truncate font-medium">
                        {{ selectedStudent.last_name }},
                        {{ selectedStudent.first_name }}
                    </span>

                    <button
                        type="button"
                        class="shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                        aria-label="Clear selected student"
                        @click="clearSelection"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <Button
                    class="w-full shrink-0 lg:w-auto"
                    :disabled="
                        !selectedStudent || enrollForm.processing
                    "
                    @click="enrollStudent"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Enroll
                </Button>
            </div>

            <!-- ========================================================= -->
            <!-- BULK ENROLL -->
            <!-- ========================================================= -->

            <div
                v-else
                class="space-y-3"
            >
                <textarea
                    v-model="bulkForm.student_nos"
                    rows="5"
                    placeholder="Paste student IDs, one per line (e.g.):&#10;2020-00001&#10;2020-00002&#10;2020-00003"
                    class="flex min-h-[120px] w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button
                        class="w-full sm:w-auto"
                        :disabled="
                            !bulkForm.student_nos.trim() ||
                            bulkForm.processing
                        "
                        @click="submitBulk"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Enroll All
                    </Button>

                    <p class="text-xs text-muted-foreground">
                        Separate IDs by new lines, commas, or semicolons.
                    </p>
                </div>

                <InputError
                    :message="bulkForm.errors.student_nos"
                />
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- STUDENT LIST -->
        <!-- ============================================================= -->

        <div class="space-y-3">
            <!-- Roster Header -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <Users class="h-4 w-4" />
                    Enrolled Students
                </h2>

                <div class="relative w-full sm:w-64">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />

                    <Input
                        v-model="rosterSearch"
                        placeholder="Search student..."
                        class="w-full pl-9"
                    />
                </div>
            </div>

            <!-- No Students -->
            <div
                v-if="enrollments.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No students enrolled yet.
            </div>

            <!-- No Search Results -->
            <div
                v-else-if="filteredEnrollments.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No students match "{{ rosterSearch }}".
            </div>

            <!-- ========================================================= -->
            <!-- DESKTOP TABLE -->
            <!-- ========================================================= -->

            <div
                v-else
                class="hidden overflow-hidden rounded-xl border md:block"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th
                                    class="w-12 px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    #
                                </th>

                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Student No.
                                </th>

                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Name
                                </th>

                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Course / Year
                                </th>

                                <th
                                    class="px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Status
                                </th>

                                <th
                                    class="w-20 px-4 py-3 text-right font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                            <tr
                                v-for="(enrollment, i) in filteredEnrollments"
                                :key="enrollment.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                >
                                    {{ i + 1 }}
                                </td>

                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ enrollment.student.student_no }}
                                </td>

                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/students/${enrollment.student.id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{ enrollment.student.last_name }},
                                        {{ enrollment.student.first_name }}
                                    </Link>
                                </td>

                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                >
                                    {{ enrollment.student.course }}
                                    —
                                    Year {{ enrollment.student.year_level }}
                                </td>

                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            enrollment.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{ enrollment.status }}
                                    </Badge>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        :aria-label="`Remove ${enrollment.student.first_name} ${enrollment.student.last_name}`"
                                        @click="
                                            unenroll(
                                                enrollment.id,
                                                `${enrollment.student.first_name} ${enrollment.student.last_name}`,
                                            )
                                        "
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MOBILE STUDENT CARDS -->
            <!-- ========================================================= -->

            <div class="space-y-3 md:hidden">
                <div
                    v-for="(enrollment, i) in filteredEnrollments"
                    :key="enrollment.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground"
                            >
                                {{ i + 1 }}
                            </div>

                            <div class="min-w-0">
                                <Link
                                    :href="`/students/${enrollment.student.id}`"
                                    class="block truncate font-semibold hover:underline"
                                >
                                    {{ enrollment.student.last_name }},
                                    {{ enrollment.student.first_name }}
                                </Link>

                                <p
                                    class="mt-1 font-mono text-xs text-muted-foreground"
                                >
                                    {{ enrollment.student.student_no }}
                                </p>
                            </div>
                        </div>

                        <Badge
                            class="shrink-0"
                            :variant="
                                enrollment.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{ enrollment.status }}
                        </Badge>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-2 border-t pt-3 text-xs text-muted-foreground"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span>Course</span>

                            <span
                                class="text-right font-medium text-foreground"
                            >
                                {{ enrollment.student.course }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span>Year Level</span>

                            <span
                                class="font-medium text-foreground"
                            >
                                Year {{ enrollment.student.year_level }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 border-t pt-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full text-destructive hover:text-destructive"
                            @click="
                                unenroll(
                                    enrollment.id,
                                    `${enrollment.student.first_name} ${enrollment.student.last_name}`,
                                )
                            "
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Remove from Section
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>