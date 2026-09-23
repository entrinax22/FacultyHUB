<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
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

import BaseTable from '@/components/BaseTable.vue';

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

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
    next_page_url: string | null;
    previous_page_url: string | null;
    first_page_url: string;
    last_page_url: string;
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
    pagination: Pagination | null;
    filters: {
        search: string | null;
    };
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
| BaseTable Columns
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: 'student_no',
        label: 'Student No.',
    },
    {
        key: 'student',
        label: 'Student',
    },
    {
        key: 'email',
        label: 'Email',
        class: 'hidden lg:table-cell',
    },
    {
        key: 'course',
        label: 'Course',
        class: 'hidden md:table-cell',
    },
    {
        key: 'year_level',
        label: 'Year',
        class: 'hidden sm:table-cell',
    },
    {
        key: 'status',
        label: 'Status',
    },
    {
        key: 'actions',
        label: 'Actions',
        class: 'text-right',
        headerClass: 'text-right',
    },
];

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref<Section | null>(null);
const enrollments = ref<Enrollment[]>([]);
const loading = ref(true);

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

const pagination = ref<Pagination>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
    has_more_pages: false,
    next_page_url: null,
    previous_page_url: null,
    first_page_url: '',
    last_page_url: '',
});

/*
|--------------------------------------------------------------------------
| Roster Search
|--------------------------------------------------------------------------
*/

const rosterSearch = ref('');

/*
|--------------------------------------------------------------------------
| Enrollment Tabs
|--------------------------------------------------------------------------
*/

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

async function loadSection(
    page = 1,
    perPage?: number,
) {
    loading.value = true;

    try {
        const response =
            await axios.get<SectionDataResponse>(
                `/sections/data/${props.sectionId}`,
                {
                    params: {
                        page,
                        per_page:
                            perPage ??
                            pagination.value.per_page,
                        search:
                            rosterSearch.value.trim() ||
                            undefined,
                    },
                },
            );

        if (response.data.success) {
            section.value =
                response.data.data;

            enrollments.value =
                response.data.data.enrollments ?? [];

            if (response.data.pagination) {
                pagination.value =
                    response.data.pagination;
            }
        } else {
            showApiError(response.data.message);
        }
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
| Roster Search
|--------------------------------------------------------------------------
*/

function applyRosterSearch() {
    loadSection(
        1,
        pagination.value.per_page,
    );
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function handlePageChange(page: number) {
    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page
    ) {
        return;
    }

    loadSection(
        page,
        pagination.value.per_page,
    );
}

function handlePerPageChange(
    perPage: number,
) {
    loadSection(
        1,
        perPage,
    );
}

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

    searchTimer = setTimeout(
        async () => {
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
        },
        250,
    );
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

        await loadSection(
            1,
            pagination.value.per_page,
        );
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

        await loadSection(
            1,
            pagination.value.per_page,
        );
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

        await loadSection(
            pagination.value.current_page,
            pagination.value.per_page,
        );
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
        v-if="loading && !section"
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

        <BaseTable
            :columns="columns"
            :data="enrollments"
            :pagination="pagination ?? undefined"
            empty-text="No students enrolled yet."
            :per-page-options="[10, 20, 25, 50, 100]"
            :loading="loading"
            @update:page="handlePageChange"
            @update:per-page="handlePerPageChange"
        >
            <!-- ===================================================== -->
            <!-- TOOLBAR -->
            <!-- ===================================================== -->

            <template #toolbar>
                <div
                    class="flex w-full flex-wrap gap-3"
                >
                    <!-- Search -->

                    <div
                        class="relative min-w-48 flex-1"
                    >
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            v-model="rosterSearch"
                            placeholder="Search student..."
                            class="pl-9"
                            @keydown.enter="
                                applyRosterSearch
                            "
                        />
                    </div>

                    <!-- Search Button -->

                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="
                            applyRosterSearch
                        "
                    >
                        Search
                    </Button>
                </div>
            </template>

            <!-- ===================================================== -->
            <!-- STUDENT NUMBER -->
            <!-- ===================================================== -->

            <template
                #cell-student_no="{ row }"
            >
                <span
                    class="font-mono text-xs"
                >
                    {{
                        getStudentNumber(
                            row.student,
                        )
                    }}
                </span>
            </template>

            <!-- ===================================================== -->
            <!-- STUDENT -->
            <!-- ===================================================== -->

            <template
                #cell-student="{ row }"
            >
                <Link
                    :href="`/students/${row.student.id}`"
                    class="font-medium hover:underline"
                >
                    {{
                        getStudentName(
                            row.student,
                        )
                    }}
                </Link>
            </template>

            <!-- ===================================================== -->
            <!-- EMAIL -->
            <!-- ===================================================== -->

            <template
                #cell-email="{ row }"
            >
                <span
                    class="text-muted-foreground"
                >
                    {{ row.student.email }}
                </span>
            </template>

            <!-- ===================================================== -->
            <!-- COURSE -->
            <!-- ===================================================== -->

            <template
                #cell-course="{ row }"
            >
                <span>
                    {{
                        getStudentCourse(
                            row.student,
                        )
                    }}
                </span>
            </template>

            <!-- ===================================================== -->
            <!-- YEAR -->
            <!-- ===================================================== -->

            <template
                #cell-year_level="{ row }"
            >
                <span>
                    {{
                        getStudentYear(
                            row.student,
                        )
                    }}
                </span>
            </template>

            <!-- ===================================================== -->
            <!-- STATUS -->
            <!-- ===================================================== -->

            <template
                #cell-status="{ row }"
            >
                <Badge
                    :variant="
                        row.status === 'active'
                            ? 'default'
                            : 'secondary'
                    "
                    class="text-xs capitalize"
                >
                    {{
                        row.status ??
                        'active'
                    }}
                </Badge>
            </template>

            <!-- ===================================================== -->
            <!-- ACTIONS -->
            <!-- ===================================================== -->

            <template
                #cell-actions="{ row }"
            >
                <div class="text-right">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        :disabled="
                            unenrollingId ===
                            row.id
                        "
                        :aria-label="
                            `Remove ${getStudentName(row.student)}`
                        "
                        @click="
                            unenroll(row)
                        "
                    >
                        <Loader2
                            v-if="
                                unenrollingId ===
                                row.id
                            "
                            class="h-4 w-4 animate-spin"
                        />

                        <Trash2
                            v-else
                            class="h-4 w-4"
                        />
                    </Button>
                </div>
            </template>
        </BaseTable>
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