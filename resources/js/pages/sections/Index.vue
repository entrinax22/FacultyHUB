<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import axios from 'axios';

import {
    Plus,
    Pencil,
    Trash2,
    Users,
    BookOpen,
    Search,
    ChevronLeft,
    ChevronRight,
    Loader2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import { showApiToast, showApiError } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

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
        is_active: boolean;
    };

    subject: {
        id: string;
        code: string;
        name: string;
    };

    faculty: {
        id: string;
        name: string;
    };
};

type Semester = {
    id: string;
    name: string;
    school_year: string;
    is_active: boolean;
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

type ApiResponse = {
    success: boolean;
    message: string;
    data: Section[];
    pagination: Pagination;
    filters?: {
        scope?: 'all' | 'mine';
        search?: string | null;
        semester_id?: string | null;
        semesters: Semester[];
    };
};

type PageProps = {
    auth: {
        role: string;
    };
};

/*
|--------------------------------------------------------------------------
| Page Options
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Sections', href: '/sections' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Page / Auth
|--------------------------------------------------------------------------
*/

const page = usePage<PageProps>();

const isAdmin = computed(() => page.props.auth.role === 'admin');

const isFaculty = computed(() => page.props.auth.role === 'faculty');

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const sections = ref<Section[]>([]);
const semesters = ref<Semester[]>([]);

const selectedSemesterId = ref('');
const search = ref('');

/*
|--------------------------------------------------------------------------
| Section Scope
|--------------------------------------------------------------------------
|
| Admin:
|   all  = All Sections
|   mine = My Sections
|
| Faculty:
|   Always mine.
|
*/

const sectionScope = ref<'all' | 'mine'>('all');

const pagination = ref<Pagination>({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: null,
    to: null,
    has_more_pages: false,
    next_page_url: null,
    previous_page_url: null,
    first_page_url: '',
    last_page_url: '',
});

const loading = ref(false);
const deletingId = ref<string | null>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

/*
|--------------------------------------------------------------------------
| Load Sections
|--------------------------------------------------------------------------
*/

async function loadSections(
    pageNumber = 1,
    perPage = pagination.value.per_page,
) {
    loading.value = true;

    try {
        /*
        |--------------------------------------------------------------------------
        | Faculty can only request their own sections.
        |
        | Admin can use the selected scope.
        |--------------------------------------------------------------------------
        */

        const scope = isFaculty.value ? 'mine' : sectionScope.value;

        const response = await axios.get<ApiResponse>('/sections/data', {
            params: {
                page: pageNumber,

                per_page: perPage,

                search: search.value.trim() || undefined,

                semester_id: selectedSemesterId.value || undefined,

                scope,
            },
        });

        if (!response.data.success) {
            showApiError(response.data.message);
            return;
        }

        sections.value = response.data.data ?? [];

        pagination.value = response.data.pagination;

        if (response.data.filters?.semesters) {
            semesters.value = response.data.filters.semesters;
        }

        /*
        |--------------------------------------------------------------------------
        | Keep frontend scope synchronized with
        | the backend response.
        |--------------------------------------------------------------------------
        */

        if (isAdmin.value && response.data.filters?.scope) {
            sectionScope.value = response.data.filters.scope;
        }
    } catch (error) {
        console.error('Failed to load sections:', error);

        sections.value = [];

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

watch(search, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        loadSections(1, pagination.value.per_page);
    }, 400);
});

/*
|--------------------------------------------------------------------------
| Section Scope
|--------------------------------------------------------------------------
*/

function changeSectionScope(value: unknown) {
    if (!isAdmin.value || (value !== 'all' && value !== 'mine')) {
        return;
    }

    sectionScope.value = value;

    loadSections(1, pagination.value.per_page);
}

/*
|--------------------------------------------------------------------------
| Semester Filter
|--------------------------------------------------------------------------
*/

function changeSemester(value: unknown) {
    if (typeof value !== 'string') {
        return;
    }

    selectedSemesterId.value = value;

    loadSections(1, pagination.value.per_page);
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function changePage(pageNumber: number) {
    if (
        loading.value ||
        pageNumber < 1 ||
        pageNumber > pagination.value.last_page ||
        pageNumber === pagination.value.current_page
    ) {
        return;
    }

    loadSections(pageNumber, pagination.value.per_page);
}

function previousPage() {
    if (pagination.value.current_page > 1) {
        changePage(pagination.value.current_page - 1);
    }
}

function nextPage() {
    if (pagination.value.has_more_pages) {
        changePage(pagination.value.current_page + 1);
    }
}

function changePerPage(value: unknown) {
    const perPage = Number(value);

    if (!Number.isFinite(perPage) || perPage <= 0) {
        return;
    }

    loadSections(1, perPage);
}

/*
|--------------------------------------------------------------------------
| Delete Section
|--------------------------------------------------------------------------
*/

async function deleteSection(section: Section) {
    if (deletingId.value) {
        return;
    }

    const confirmed = confirm(
        'Delete this section? All enrollments will also be removed.',
    );

    if (!confirmed) {
        return;
    }

    deletingId.value = section.id;

    try {
        const response = await axios.delete(`/sections/delete/${section.id}`);

        showApiToast(response);

        /*
        |--------------------------------------------------------------------------
        | If deleting the last item on the current
        | page, move back one page when necessary.
        |--------------------------------------------------------------------------
        */

        const currentPage = pagination.value.current_page;

        const targetPage =
            currentPage > 1 && sections.value.length === 1
                ? currentPage - 1
                : currentPage;

        await loadSections(targetPage, pagination.value.per_page);
    } catch (error) {
        console.error('Failed to delete section:', error);

        showApiError(error);
    } finally {
        deletingId.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatSemester(semester: Semester) {
    return `${semester.name} ${semester.school_year}`;
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

onMounted(() => {
    /*
    |--------------------------------------------------------------------------
    | Faculty must always start with "mine".
    |--------------------------------------------------------------------------
    */

    if (isFaculty.value) {
        sectionScope.value = 'mine';
    }

    loadSections();
});
</script>

<template>
    <Head title="Sections" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">Sections</h1>

                <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                    Manage class sections per semester
                </p>
            </div>

            <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                <!-- Semester Filter -->

                <Select
                    :model-value="selectedSemesterId"
                    @update:model-value="changeSemester"
                >
                    <SelectTrigger class="h-9 w-full sm:w-64">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem
                            v-for="semester in semesters"
                            :key="semester.id"
                            :value="semester.id"
                        >
                            {{ formatSemester(semester) }}

                            <span
                                v-if="semester.is_active"
                                class="ml-1 text-xs text-green-600"
                            >
                                (Active)
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- New Section -->

                <Button as-child class="w-full sm:w-auto">
                    <Link href="/sections/create">
                        <Plus class="mr-2 h-4 w-4 shrink-0" />

                        New Section
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TOOLBAR -->
        <!-- ========================================================= -->

        <!-- ========================================================= -->
        <!-- TOOLBAR -->
        <!-- ========================================================= -->

        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Search -->

            <div class="relative w-full min-w-0 sm:max-w-md">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />

                <input
                    v-model="search"
                    type="text"
                    placeholder="Search sections, subjects, or faculty..."
                    class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm transition outline-none focus:ring-1 focus:ring-ring"
                />
            </div>

            <!-- Admin Controls -->

            <div v-if="isAdmin" class="flex min-w-0 items-center gap-2">
                <!-- Section Scope -->

                <Select
                    :model-value="sectionScope"
                    @update:model-value="changeSectionScope"
                >
                    <SelectTrigger class="h-10 w-full shrink-0 sm:w-40">
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem value="all"> All Sections </SelectItem>

                        <SelectItem value="mine"> My Sections </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Result Count -->

                <div
                    v-if="pagination.total > 0"
                    class="shrink-0 text-xs whitespace-nowrap text-muted-foreground"
                >
                    {{ pagination.total }}
                    section{{ pagination.total !== 1 ? 's' : '' }}
                </div>
            </div>

            <!-- Faculty Result Count -->

            <div
                v-else-if="pagination.total > 0"
                class="shrink-0 text-xs whitespace-nowrap text-muted-foreground"
            >
                {{ pagination.total }}
                section{{ pagination.total !== 1 ? 's' : '' }}
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- INITIAL LOADING -->
        <!-- ========================================================= -->

        <div
            v-if="loading && sections.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6"
        >
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 class="h-4 w-4 animate-spin" />

                Loading sections...
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- EMPTY STATE -->
        <!-- ========================================================= -->

        <div
            v-else-if="sections.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            <div>
                <p>
                    {{
                        search || selectedSemesterId
                            ? 'No sections found.'
                            : isFaculty
                              ? 'You do not have any sections yet.'
                              : sectionScope === 'mine'
                                ? 'You do not have any sections yet.'
                                : 'No sections yet.'
                    }}
                </p>

                <p
                    v-if="
                        !search &&
                        !selectedSemesterId &&
                        !isFaculty &&
                        sectionScope === 'all'
                    "
                    class="mt-1"
                >
                    Create your first section to get started.
                </p>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SECTION CARDS -->
        <!-- ========================================================= -->

        <div
            v-else
            class="grid min-w-0 grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="section in sections"
                :key="section.id"
                class="flex min-w-0 flex-col rounded-xl border bg-card p-4 transition-colors hover:bg-muted/20"
            >
                <!-- Card Header -->

                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h3
                            class="truncate font-semibold"
                            :title="section.name"
                        >
                            {{ section.name }}
                        </h3>

                        <p
                            class="mt-1 truncate text-sm text-muted-foreground"
                            :title="`${section.subject.code} — ${section.subject.name}`"
                        >
                            {{ section.subject.code }}
                            —
                            {{ section.subject.name }}
                        </p>
                    </div>

                    <Badge
                        :variant="
                            section.semester.is_active ? 'default' : 'secondary'
                        "
                        class="shrink-0 text-xs"
                    >
                        {{ section.semester.name }}
                    </Badge>
                </div>

                <!-- Card Information -->

                <div class="mt-4 space-y-2 text-xs text-muted-foreground">
                    <!-- Schedule -->

                    <div
                        v-if="section.schedule"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <BookOpen class="mt-0.5 h-3.5 w-3.5 shrink-0" />

                        <span class="break-words">
                            {{ section.schedule }}
                        </span>
                    </div>

                    <!-- Room -->

                    <div
                        v-if="section.room"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <span class="w-3.5 shrink-0 text-center"> • </span>

                        <span class="break-words">
                            Room
                            {{ section.room }}
                        </span>
                    </div>

                    <!-- Students -->

                    <div class="flex items-center gap-2">
                        <Users class="h-3.5 w-3.5 shrink-0" />

                        <span>
                            {{ section.enrollments_count }}
                            student{{
                                section.enrollments_count !== 1 ? 's' : ''
                            }}
                        </span>
                    </div>

                    <!-- Faculty -->

                    <div
                        v-if="section.faculty"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <span class="w-3.5 shrink-0 text-center"> • </span>

                        <span class="truncate" :title="section.faculty.name">
                            {{ section.faculty.name }}
                        </span>
                    </div>
                </div>

                <!-- Card Actions -->

                <div
                    class="mt-5 grid grid-cols-[1fr_auto_auto] gap-2 border-t pt-4"
                >
                    <!-- View -->

                    <Button
                        variant="outline"
                        size="sm"
                        class="min-w-0"
                        as-child
                    >
                        <Link
                            :href="`/sections/show/${section.id}`"
                            class="truncate"
                        >
                            View Class
                        </Link>
                    </Button>

                    <!-- Edit -->

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-9 p-0"
                        as-child
                    >
                        <Link
                            :href="`/sections/edit/${section.id}`"
                            :aria-label="`Edit ${section.name}`"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </Link>
                    </Button>

                    <!-- Delete -->

                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-9 p-0 text-destructive hover:text-destructive"
                        :disabled="deletingId === section.id"
                        :aria-label="`Delete ${section.name}`"
                        @click="deleteSection(section)"
                    >
                        <Loader2
                            v-if="deletingId === section.id"
                            class="h-3.5 w-3.5 animate-spin"
                        />

                        <Trash2 v-else class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- PAGINATION -->
        <!-- ========================================================= -->

        <div
            v-if="pagination.total > 0"
            class="flex flex-col gap-4 border-t pt-4"
        >
            <!-- Pagination Controls -->

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <!-- Result Count -->

                <p
                    class="text-center text-xs text-muted-foreground sm:text-left"
                >
                    Showing

                    <span class="font-medium text-foreground">
                        {{ pagination.from ?? 0 }}
                    </span>

                    to

                    <span class="font-medium text-foreground">
                        {{ pagination.to ?? 0 }}
                    </span>

                    of

                    <span class="font-medium text-foreground">
                        {{ pagination.total }}
                    </span>

                    sections
                </p>

                <!-- Per Page -->

                <div
                    class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
                >
                    <span> Rows per page </span>

                    <Select
                        :model-value="String(pagination.per_page)"
                        @update:model-value="changePerPage"
                    >
                        <SelectTrigger class="h-8 w-20">
                            <SelectValue />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="6"> 6 </SelectItem>

                            <SelectItem value="12"> 12 </SelectItem>

                            <SelectItem value="24"> 24 </SelectItem>

                            <SelectItem value="48"> 48 </SelectItem>

                            <SelectItem value="96"> 96 </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Page Navigation -->

                <div
                    class="flex items-center justify-center gap-2 sm:justify-end"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="loading || pagination.current_page <= 1"
                        @click="previousPage"
                    >
                        <ChevronLeft class="mr-1 h-4 w-4" />

                        Previous
                    </Button>

                    <div
                        class="min-w-20 text-center text-xs text-muted-foreground"
                    >
                        Page

                        <span class="font-medium text-foreground">
                            {{ pagination.current_page }}
                        </span>

                        of

                        <span class="font-medium text-foreground">
                            {{ pagination.last_page }}
                        </span>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="loading || !pagination.has_more_pages"
                        @click="nextPage"
                    >
                        Next

                        <ChevronRight class="ml-1 h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- PAGE LOADING -->
        <!-- ========================================================= -->

        <div
            v-if="loading && sections.length > 0"
            class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
        >
            <Loader2 class="h-3.5 w-3.5 animate-spin" />

            Updating sections...
        </div>
    </div>
</template>
