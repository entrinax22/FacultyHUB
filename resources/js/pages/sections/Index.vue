<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
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

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

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
        semesters: Semester[];
    };
};

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
| State
|--------------------------------------------------------------------------
*/

const sections = ref<Section[]>([]);
const semesters = ref<Semester[]>([]);

const selectedSemesterId = ref('');
const search = ref('');

const pagination = ref<Pagination | null>(null);

const loading = ref(false);
const deletingId = ref<string | null>(null);

/*
|--------------------------------------------------------------------------
| Load Sections
|--------------------------------------------------------------------------
*/

async function loadSections(page = 1) {
    loading.value = true;

    try {
        const response = await axios.get<ApiResponse>(
            '/sections/data',
            {
                params: {
                    page,
                    per_page: pagination.value?.per_page ?? 12,
                    search: search.value || undefined,
                    semester_id:
                        selectedSemesterId.value || undefined,
                },
            },
        );

        sections.value = response.data.data;
        pagination.value = response.data.pagination;

        if (response.data.filters?.semesters) {
            semesters.value = response.data.filters.semesters;
        }
    } catch (error) {
        console.error('Failed to load sections:', error);
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

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        loadSections(1);
    }, 400);
});

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

    loadSections(1);
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function changePage(page: number) {
    if (
        loading.value ||
        !pagination.value ||
        page < 1 ||
        page > pagination.value.last_page
    ) {
        return;
    }

    loadSections(page);
}

function previousPage() {
    if (
        pagination.value &&
        pagination.value.current_page > 1
    ) {
        changePage(
            pagination.value.current_page - 1,
        );
    }
}

function nextPage() {
    if (
        pagination.value &&
        pagination.value.has_more_pages
    ) {
        changePage(
            pagination.value.current_page + 1,
        );
    }
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
        const response = await axios.delete(
            `/sections/delete/${section.id}`,
        );

        showApiToast(response);

        await loadSections(
            pagination.value?.current_page ?? 1,
        );
    } catch (error) {
        console.error(
            'Failed to delete section:',
            error,
        );

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
            <!-- Title -->
            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">
                    Sections
                </h1>

                <p
                    class="mt-1 text-xs text-muted-foreground sm:text-sm"
                >
                    Manage class sections per semester
                </p>
            </div>

            <!-- Actions -->
            <div
                class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto"
            >
                <!-- Semester -->
                <Select
                    :model-value="selectedSemesterId"
                    @update:model-value="changeSemester"
                >
                    <SelectTrigger
                        class="h-9 w-full sm:w-64"
                    >
                        <SelectValue
                            placeholder="Select semester"
                        />
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
                <Button
                    as-child
                    class="w-full sm:w-auto"
                >
                    <Link href="/sections/create">
                        <Plus
                            class="mr-2 h-4 w-4 shrink-0"
                        />
                        New Section
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SEARCH -->
        <!-- ========================================================= -->

        <div class="relative w-full sm:max-w-md">
            <Search
                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />

            <input
                v-model="search"
                type="text"
                placeholder="Search sections, subjects, or faculty..."
                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm outline-none transition focus:ring-1 focus:ring-ring"
            />
        </div>

        <!-- ========================================================= -->
        <!-- LOADING -->
        <!-- ========================================================= -->

        <div
            v-if="loading && sections.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6"
        >
            <div
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <Loader2
                    class="h-4 w-4 animate-spin"
                />

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
                        search
                            ? 'No sections found.'
                            : 'No sections yet.'
                    }}
                </p>

                <p
                    v-if="!search"
                    class="mt-1"
                >
                    Create your first section to get started.
                </p>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- SECTION GRID -->
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
                <!-- Section Header -->
                <div
                    class="flex min-w-0 items-start justify-between gap-3"
                >
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
                            section.semester.is_active
                                ? 'default'
                                : 'secondary'
                        "
                        class="shrink-0 text-xs"
                    >
                        {{ section.semester.name }}
                    </Badge>
                </div>

                <!-- ================================================= -->
                <!-- SECTION INFORMATION -->
                <!-- ================================================= -->

                <div
                    class="mt-4 space-y-2 text-xs text-muted-foreground"
                >
                    <!-- Schedule -->
                    <div
                        v-if="section.schedule"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <BookOpen
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        />

                        <span class="break-words">
                            {{ section.schedule }}
                        </span>
                    </div>

                    <!-- Room -->
                    <div
                        v-if="section.room"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <span
                            class="w-3.5 shrink-0 text-center"
                        >
                            •
                        </span>

                        <span class="break-words">
                            Room {{ section.room }}
                        </span>
                    </div>

                    <!-- Students -->
                    <div
                        class="flex items-center gap-2"
                    >
                        <Users
                            class="h-3.5 w-3.5 shrink-0"
                        />

                        <span>
                            {{ section.enrollments_count }}
                            student{{
                                section.enrollments_count !== 1
                                    ? 's'
                                    : ''
                            }}
                        </span>
                    </div>

                    <!-- Faculty -->
                    <div
                        v-if="section.faculty"
                        class="flex min-w-0 items-start gap-2"
                    >
                        <span
                            class="w-3.5 shrink-0 text-center"
                        >
                            •
                        </span>

                        <span
                            class="truncate"
                            :title="section.faculty.name"
                        >
                            {{ section.faculty.name }}
                        </span>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- ACTIONS -->
                <!-- ================================================= -->

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
                            <Pencil
                                class="h-3.5 w-3.5"
                            />
                        </Link>
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 w-9 p-0 text-destructive hover:text-destructive"
                        :disabled="
                            deletingId === section.id
                        "
                        :aria-label="`Delete ${section.name}`"
                        @click="deleteSection(section)"
                    >
                        <Loader2
                            v-if="
                                deletingId === section.id
                            "
                            class="h-3.5 w-3.5 animate-spin"
                        />

                        <Trash2
                            v-else
                            class="h-3.5 w-3.5"
                        />
                    </Button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- PAGINATION -->
        <!-- ========================================================= -->

        <div
            v-if="
                pagination &&
                pagination.total > 0
            "
            class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
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

            <!-- Pagination Buttons -->
            <div
                class="flex items-center justify-center gap-2 sm:justify-end"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        loading ||
                        pagination.current_page <= 1
                    "
                    @click="previousPage"
                >
                    <ChevronLeft
                        class="mr-1 h-4 w-4"
                    />

                    Previous
                </Button>

                <div
                    class="min-w-20 text-center text-xs text-muted-foreground"
                >
                    Page
                    <span
                        class="font-medium text-foreground"
                    >
                        {{ pagination.current_page }}
                    </span>
                    of
                    <span
                        class="font-medium text-foreground"
                    >
                        {{ pagination.last_page }}
                    </span>
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        loading ||
                        !pagination.has_more_pages
                    "
                    @click="nextPage"
                >
                    Next

                    <ChevronRight
                        class="ml-1 h-4 w-4"
                    />
                </Button>
            </div>
        </div>

        <!-- Loading indicator while changing page -->
        <div
            v-if="loading && sections.length > 0"
            class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
        >
            <Loader2
                class="h-3.5 w-3.5 animate-spin"
            />
            Updating sections...
        </div>
    </div>
</template>