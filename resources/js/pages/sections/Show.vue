<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
    Users,
    Plus,
    Trash2,
    Search,
    Layers,
    ClipboardList,
    BarChart2,
    CalendarCheck,
    X,
    Loader2,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Student = {
    id: string;
    name?: string | null;
    email: string;

    student_no?: string | null;
    first_name?: string | null;
    last_name?: string | null;
    course?: string | null;
    year_level?: number | null;
};

type Enrollment = {
    id: string;
    status?: string | null;
    student: Student;
};

type Section = {
    id: string;
    name: string;
    schedule: string | null;
    room: string | null;
    enrollments_count: number;

    semester: {
        id: string;
        name: string;
        school_year: string;
        is_active?: boolean;
    };

    subject: {
        id: string;
        code: string;
        name: string;
    };

    faculty: {
        id: string;
        name: string;
        email?: string;
    };

    enrollments: Enrollment[];
};

type SearchResult = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
    course: string;
    year_level: number;
};

type SectionDataResponse = {
    success: boolean;
    message: string;
    data: Section;
};

type StudentSearchResponse = {
    success: boolean;
    message?: string;
    data: SearchResult[];
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    sectionId: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Sections',
                href: '/sections',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref<Section | null>(null);
const enrollments = ref<Enrollment[]>([]);
const loading = ref(true);

const enrollTab = ref<'single' | 'bulk'>('single');

/*
|--------------------------------------------------------------------------
| Single Enrollment
|--------------------------------------------------------------------------
*/

const studentNo = ref('');
const enrollError = ref('');
const enrolling = ref(false);

/*
|--------------------------------------------------------------------------
| Bulk Enrollment
|--------------------------------------------------------------------------
*/

const bulkStudentNos = ref('');
const bulkError = ref('');
const bulkEnrolling = ref(false);

/*
|--------------------------------------------------------------------------
| Student Search
|--------------------------------------------------------------------------
*/

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const selectedStudent = ref<SearchResult | null>(null);
const showDropdown = ref(false);
const searchingStudents = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

/*
|--------------------------------------------------------------------------
| Roster Search
|--------------------------------------------------------------------------
*/

const rosterSearch = ref('');

/*
|--------------------------------------------------------------------------
| Student Display Helpers
|--------------------------------------------------------------------------
*/

function getStudentName(student: Student): string {
    if (student.name) {
        return student.name;
    }

    if (student.last_name || student.first_name) {
        return [
            student.last_name,
            student.first_name,
        ]
            .filter(Boolean)
            .join(', ');
    }

    return student.email || 'Unknown Student';
}

function getStudentNumber(student: Student): string {
    return student.student_no ?? '—';
}

function getStudentCourse(student: Student): string {
    return student.course ?? '—';
}

function getStudentYear(student: Student): string {
    return student.year_level
        ? `Year ${student.year_level}`
        : '—';
}

/*
|--------------------------------------------------------------------------
| Load Section
|--------------------------------------------------------------------------
*/

async function loadSection() {
    loading.value = true;

    try {
        const response =
            await axios.get<SectionDataResponse>(
                `/sections/data/${props.sectionId}`,
            );

        const data = response.data.data;

        section.value = data;
        enrollments.value = data.enrollments ?? [];
    } catch (error) {
        console.error(
            'Failed to load section:',
            error,
        );

        section.value = null;
        enrollments.value = [];

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Computed Roster
|--------------------------------------------------------------------------
*/

const filteredEnrollments = computed(() => {
    const q = rosterSearch.value
        .toLowerCase()
        .trim();

    if (!q) {
        return enrollments.value;
    }

    return enrollments.value.filter((enrollment) => {
        const student = enrollment.student;

        const values = [
            getStudentNumber(student),
            getStudentName(student),
            getStudentCourse(student),
            getStudentYear(student),
            student.email,
        ];

        return values.some((value) =>
            value
                .toLowerCase()
                .includes(q),
        );
    });
});

/*
|--------------------------------------------------------------------------
| Student Search
|--------------------------------------------------------------------------
*/

function onSearchInput() {
    selectedStudent.value = null;
    studentNo.value = '';
    enrollError.value = '';

    const q = searchQuery.value.trim();

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (!q) {
        searchResults.value = [];
        showDropdown.value = false;
        searchingStudents.value = false;
        return;
    }

    searchingStudents.value = true;

    searchTimer = setTimeout(async () => {
        try {
            const response =
                await axios.get<StudentSearchResponse>(
                    `/sections/${props.sectionId}/students/search`,
                    {
                        params: {
                            q,
                        },
                    },
                );

            searchResults.value =
                response.data.data ?? [];

            showDropdown.value = true;
        } catch (error) {
            console.error(
                'Failed to search students:',
                error,
            );

            searchResults.value = [];
            showDropdown.value = true;
        } finally {
            searchingStudents.value = false;
        }
    }, 250);
}

function selectStudent(
    student: SearchResult,
) {
    selectedStudent.value = student;

    studentNo.value =
        student.student_no;

    searchQuery.value =
        `${student.student_no} — ${student.last_name}, ${student.first_name}`;

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
    studentNo.value = '';
    searchQuery.value = '';
    searchResults.value = [];
    enrollError.value = '';
}

/*
|--------------------------------------------------------------------------
| Single Enrollment
|--------------------------------------------------------------------------
*/

async function enrollStudent() {
    if (
        !section.value ||
        !selectedStudent.value
    ) {
        return;
    }

    enrollError.value = '';
    enrolling.value = true;

    try {
        const response = await axios.post(
            `/sections/${props.sectionId}/enroll`,
            {
                student_no: studentNo.value,
            },
        );

        showApiToast(response);

        clearSelection();

        await loadSection();
    } catch (error: any) {
        console.error(
            'Failed to enroll student:',
            error,
        );

        if (
            error.response?.status === 422
        ) {
            enrollError.value =
                error.response.data.errors
                    ?.student_no?.[0] ??
                error.response.data.message ??
                'Unable to enroll student.';
        } else {
            showApiError(error);
        }
    } finally {
        enrolling.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Bulk Enrollment
|--------------------------------------------------------------------------
*/

async function submitBulk() {
    if (!section.value) {
        return;
    }

    if (!bulkStudentNos.value.trim()) {
        return;
    }

    bulkError.value = '';
    bulkEnrolling.value = true;

    try {
        const response = await axios.post(
            `/sections/${props.sectionId}/bulk-enroll`,
            {
                student_nos:
                    bulkStudentNos.value,
            },
        );

        showApiToast(response);

        bulkStudentNos.value = '';

        await loadSection();
    } catch (error: any) {
        console.error(
            'Failed to bulk enroll students:',
            error,
        );

        if (
            error.response?.status === 422
        ) {
            bulkError.value =
                error.response.data.errors
                    ?.student_nos?.[0] ??
                error.response.data.message ??
                'Unable to enroll students.';
        } else {
            showApiError(error);
        }
    } finally {
        bulkEnrolling.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Unenroll
|--------------------------------------------------------------------------
*/

const unenrollingId =
    ref<string | null>(null);

async function unenroll(
    enrollment: Enrollment,
) {
    const studentName =
        getStudentName(
            enrollment.student,
        );

    if (
        !confirm(
            `Remove ${studentName} from this section?`,
        )
    ) {
        return;
    }

    if (unenrollingId.value) {
        return;
    }

    unenrollingId.value =
        enrollment.id;

    try {
        const response =
            await axios.delete(
                `/enrollments/${enrollment.id}`,
            );

        showApiToast(response);

        await loadSection();
    } catch (error) {
        console.error(
            'Failed to remove student:',
            error,
        );

        showApiError(error);
    } finally {
        unenrollingId.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSection();
});
</script>



<template>
    <Head
        :title="section?.name ?? 'Section'"
    />

    <!-- ============================================================= -->
    <!-- LOADING -->
    <!-- ============================================================= -->

    <div
        v-if="loading"
        class="flex min-h-full flex-1 items-center justify-center p-6"
    >
        <div
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <Loader2
                class="h-4 w-4 animate-spin"
            />
            Loading section...
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- CONTENT -->
    <!-- ============================================================= -->

    <div
        v-else-if="section"
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <div
            class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="min-w-0">
                <h1
                    class="truncate text-xl font-semibold sm:text-2xl"
                >
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
                    <Link
                        :href="`/sections/${section.id}/modules`"
                    >
                        <Layers
                            class="mr-1.5 h-3.5 w-3.5 shrink-0"
                        />
                        <span>Modules</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link
                        :href="`/sections/${section.id}/assignments`"
                    >
                        <ClipboardList
                            class="mr-1.5 h-3.5 w-3.5 shrink-0"
                        />
                        <span>Assignments</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link
                        :href="`/sections/${section.id}/attendance`"
                    >
                        <CalendarCheck
                            class="mr-1.5 h-3.5 w-3.5 shrink-0"
                        />
                        <span>Attendance</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="w-full lg:w-auto"
                    as-child
                >
                    <Link
                        :href="`/sections/${section.id}/class-record`"
                    >
                        <BarChart2
                            class="mr-1.5 h-3.5 w-3.5 shrink-0"
                        />
                        <span>Class Record</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="col-span-2 w-full sm:col-span-1 lg:w-auto"
                    as-child
                >
                    <Link
                        :href="`/sections/edit/${section.id}`"
                    >
                        Edit Section
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SECTION INFO -->
        <!-- ========================================================= -->

        <div
            class="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="min-w-0">
                <p
                    class="text-xs text-muted-foreground"
                >
                    Semester
                </p>

                <p class="truncate font-medium">
                    {{ section.semester.name }}
                </p>

                <p
                    class="text-xs text-muted-foreground"
                >
                    {{ section.semester.school_year }}
                </p>
            </div>

            <div
                v-if="section.schedule"
                class="min-w-0"
            >
                <p
                    class="text-xs text-muted-foreground"
                >
                    Schedule
                </p>

                <p
                    class="break-words font-medium"
                >
                    {{ section.schedule }}
                </p>
            </div>

            <div
                v-if="section.room"
                class="min-w-0"
            >
                <p
                    class="text-xs text-muted-foreground"
                >
                    Room
                </p>

                <p
                    class="break-words font-medium"
                >
                    {{ section.room }}
                </p>
            </div>

            <div class="min-w-0">
                <p
                    class="text-xs text-muted-foreground"
                >
                    Faculty
                </p>

                <p class="truncate font-medium">
                    {{ section.faculty.name }}
                </p>
            </div>

            <div class="min-w-0">
                <p
                    class="text-xs text-muted-foreground"
                >
                    Enrolled
                </p>

                <p class="font-medium">
                    {{ section.enrollments_count }}
                    student{{
                        section.enrollments_count !== 1
                            ? 's'
                            : ''
                    }}
                </p>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- ENROLL STUDENTS -->
        <!-- ========================================================= -->

        <div
            class="space-y-4 rounded-xl border bg-card p-4 sm:p-5"
        >
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
                        @click="
                            enrollTab = 'single'
                        "
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
                        @click="
                            enrollTab = 'bulk'
                        "
                    >
                        Bulk
                    </button>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- SINGLE ENROLL -->
            <!-- ===================================================== -->

            <div
                v-if="enrollTab === 'single'"
                class="flex flex-col gap-3 lg:flex-row"
            >
                <div
                    class="relative min-w-0 flex-1"
                >
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

                    <!-- Searching -->

                    <div
                        v-if="searchingStudents"
                        class="absolute right-3 top-1/2 -translate-y-1/2"
                    >
                        <Loader2
                            class="h-4 w-4 animate-spin text-muted-foreground"
                        />
                    </div>

                    <!-- Search Dropdown -->

                    <div
                        v-if="
                            showDropdown &&
                            searchResults.length
                        "
                        class="absolute left-0 top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border bg-popover shadow-lg"
                    >
                        <button
                            v-for="student in searchResults"
                            :key="student.id"
                            type="button"
                            class="flex w-full flex-col gap-1 px-4 py-3 text-left text-sm transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-3"
                            @mousedown.prevent="
                                selectStudent(
                                    student,
                                )
                            "
                        >
                            <span
                                class="shrink-0 font-mono text-xs text-muted-foreground sm:w-24"
                            >
                                {{ student.student_no }}
                            </span>

                            <span
                                class="font-medium"
                            >
                                {{ student.last_name }},
                                {{ student.first_name }}
                            </span>

                            <span
                                class="text-xs text-muted-foreground sm:ml-auto"
                            >
                                {{ student.course }}
                                · Year
                                {{ student.year_level }}
                            </span>
                        </button>
                    </div>

                    <!-- No Results -->

                    <div
                        v-if="
                            showDropdown &&
                            !searchingStudents &&
                            searchResults.length === 0 &&
                            searchQuery.length >= 1
                        "
                        class="absolute left-0 top-full z-50 mt-1 w-full rounded-xl border bg-popover px-4 py-3 text-sm text-muted-foreground shadow-lg"
                    >
                        No matching students found.
                    </div>

                    <!-- Error -->

                    <p
                        v-if="enrollError"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ enrollError }}
                    </p>
                </div>

                <!-- Selected Student -->

                <div
                    v-if="selectedStudent"
                    class="flex min-w-0 items-center justify-between gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-sm lg:max-w-xs"
                >
                    <span
                        class="truncate font-medium"
                    >
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
                        !selectedStudent ||
                        enrolling
                    "
                    @click="enrollStudent"
                >
                    <Loader2
                        v-if="enrolling"
                        class="mr-2 h-4 w-4 animate-spin"
                    />

                    <Plus
                        v-else
                        class="mr-2 h-4 w-4"
                    />

                    {{
                        enrolling
                            ? 'Enrolling...'
                            : 'Enroll'
                    }}
                </Button>
            </div>

            <!-- ===================================================== -->
            <!-- BULK ENROLL -->
            <!-- ===================================================== -->

            <div
                v-else
                class="space-y-3"
            >
                <textarea
                    v-model="bulkStudentNos"
                    rows="5"
                    placeholder="Paste student IDs, one per line (e.g.):&#10;2020-00001&#10;2020-00002&#10;2020-00003"
                    class="flex min-h-[120px] w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                ></textarea>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <Button
                        class="w-full sm:w-auto"
                        :disabled="
                            !bulkStudentNos.trim() ||
                            bulkEnrolling
                        "
                        @click="submitBulk"
                    >
                        <Loader2
                            v-if="bulkEnrolling"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        <Plus
                            v-else
                            class="mr-2 h-4 w-4"
                        />

                        {{
                            bulkEnrolling
                                ? 'Enrolling...'
                                : 'Enroll All'
                        }}
                    </Button>

                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Separate IDs by new lines,
                        commas, or semicolons.
                    </p>
                </div>

                <p
                    v-if="bulkError"
                    class="text-sm text-destructive"
                >
                    {{ bulkError }}
                </p>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STUDENT LIST -->
        <!-- ========================================================= -->

        <div class="space-y-3">
            <!-- Roster Header -->

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2
                    class="flex items-center gap-2 font-semibold"
                >
                    <Users class="h-4 w-4" />
                    Enrolled Students
                </h2>

                <div
                    class="relative w-full sm:w-64"
                >
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
                v-else-if="
                    filteredEnrollments.length === 0
                "
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No students match
                "{{ rosterSearch }}".
            </div>

            <!-- ===================================================== -->
            <!-- DESKTOP TABLE -->
            <!-- ===================================================== -->

            <div
                v-else
                class="hidden overflow-hidden rounded-xl border md:block"
            >
                <div
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[760px] text-sm"
                    >
                        <thead
                            class="border-b bg-muted/50"
                        >
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
                                    Email
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

                        <tbody
                            class="divide-y"
                        >
                            <tr
                                v-for="(
                                    enrollment, i
                                ) in filteredEnrollments"
                                :key="
                                    enrollment.id
                                "
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                >
                                    {{ i + 1 }}
                                </td>

                                <td
                                    class="px-4 py-3 font-mono text-xs"
                                >
                                    {{
                                        getStudentNumber(
                                            enrollment.student,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-4 py-3"
                                >
                                    <Link
                                        :href="`/students/${enrollment.student.id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{
                                            getStudentName(
                                                enrollment.student,
                                            )
                                        }}
                                    </Link>
                                </td>

                                <td
                                    class="px-4 py-3 text-muted-foreground"
                                >
                                    {{
                                        enrollment.student.email
                                    }}
                                </td>

                                <td
                                    class="px-4 py-3"
                                >
                                    <Badge
                                        :variant="
                                            enrollment.status ===
                                            'active'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            enrollment.status ??
                                            'active'
                                        }}
                                    </Badge>
                                </td>

                                <td
                                    class="px-4 py-3 text-right"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="
                                            unenrollingId ===
                                            enrollment.id
                                        "
                                        :aria-label="`Remove ${getStudentName(enrollment.student)}`"
                                        @click="
                                            unenroll(
                                                enrollment,
                                            )
                                        "
                                    >
                                        <Loader2
                                            v-if="
                                                unenrollingId ===
                                                enrollment.id
                                            "
                                            class="h-4 w-4 animate-spin"
                                        />

                                        <Trash2
                                            v-else
                                            class="h-4 w-4"
                                        />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- MOBILE STUDENT CARDS -->
            <!-- ===================================================== -->

            <div
                class="space-y-3 md:hidden"
            >
                <div
                    v-for="(
                        enrollment, i
                    ) in filteredEnrollments"
                    :key="enrollment.id"
                    class="rounded-xl border bg-card p-4"
                >
                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <div
                            class="flex min-w-0 items-start gap-3"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground"
                            >
                                {{ i + 1 }}
                            </div>

                            <div
                                class="min-w-0"
                            >
                                <Link
                                    :href="`/students/${enrollment.student.id}`"
                                    class="block truncate font-semibold hover:underline"
                                >
                                    {{
                                        getStudentName(
                                            enrollment.student,
                                        )
                                    }}
                                </Link>

                                <p
                                    class="mt-1 font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        enrollment.student.email
                                    }}
                                </p>
                            </div>
                        </div>

                        <Badge
                            class="shrink-0"
                            :variant="
                                enrollment.status ===
                                'active'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                enrollment.status ??
                                'active'
                            }}
                        </Badge>
                    </div>

                    <div
                        class="mt-4 flex flex-col gap-2 border-t pt-3 text-xs text-muted-foreground"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <span>
                                Student No.
                            </span>

                            <span
                                class="font-medium text-foreground"
                            >
                                {{
                                    getStudentNumber(
                                        enrollment.student,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <span>
                                Course
                            </span>

                            <span
                                class="text-right font-medium text-foreground"
                            >
                                {{
                                    getStudentCourse(
                                        enrollment.student,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <span>
                                Year Level
                            </span>

                            <span
                                class="font-medium text-foreground"
                            >
                                {{
                                    getStudentYear(
                                        enrollment.student,
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="mt-4 border-t pt-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full text-destructive hover:text-destructive"
                            :disabled="
                                unenrollingId ===
                                enrollment.id
                            "
                            @click="
                                unenroll(
                                    enrollment,
                                )
                            "
                        >
                            <Loader2
                                v-if="
                                    unenrollingId ===
                                    enrollment.id
                                "
                                class="mr-2 h-4 w-4 animate-spin"
                            />

                            <Trash2
                                v-else
                                class="mr-2 h-4 w-4"
                            />

                            {{
                                unenrollingId ===
                                enrollment.id
                                    ? 'Removing...'
                                    : 'Remove from Section'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- FAILED TO LOAD -->
    <!-- ============================================================= -->

    <div
        v-else
        class="flex min-h-full flex-1 items-center justify-center p-6"
    >
        <div
            class="rounded-xl border border-dashed p-8 text-center"
        >
            <p class="font-medium">
                Unable to load section.
            </p>

            <Button
                variant="outline"
                class="mt-4"
                @click="loadSection"
            >
                Try Again
            </Button>
        </div>
    </div>
</template>

