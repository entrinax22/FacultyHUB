<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

import {
    Plus,
    Eye,
    EyeOff,
    Pencil,
    Trash2,
    Clock,
    Search,
} from 'lucide-vue-next';

import BaseTable from '@/components/BaseTable.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Assignment = {
    id: string;
    title: string;
    type: 'essay' | 'mcq' | 'code';
    period: 'midterm' | 'finals' | null;
    category: 'quiz' | 'exam' | 'activity' | 'project' | null;
    due_date: string | null;
    max_score: number;
    is_published: boolean;
    submissions_count: number;
};

type Section = {
    id: string;
    name: string;
    subject: {
        code: string;
        name: string;
    };
    semester: {
        name: string;
        school_year: string;
    };
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
};

type AssignmentsResponse = {
    success: boolean;
    message: string;
    data: Assignment[];
    pagination: Pagination;
};

const props = defineProps<{
    section: Section;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const assignments = ref<Assignment[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');
const loading = ref(false);
const error = ref<string | null>(null);

/*
|--------------------------------------------------------------------------
| Table Columns
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: 'title',
        label: 'Title',
    },
    {
        key: 'type',
        label: 'Type',
    },
    {
        key: 'due_date',
        label: 'Due Date',
    },
    {
        key: 'max_score',
        label: 'Max Score',
        class: 'text-center',
    },
    {
        key: 'submissions_count',
        label: 'Submissions',
        class: 'text-center',
    },
    {
        key: 'is_published',
        label: 'Status',
        class: 'text-center',
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
| Labels
|--------------------------------------------------------------------------
*/

const typeLabel: Record<string, string> = {
    essay: 'Essay',
    mcq: 'Multiple Choice',
    code: 'Code',
};

const typeVariant: Record<
    string,
    'default' | 'secondary' | 'outline'
> = {
    essay: 'default',
    mcq: 'secondary',
    code: 'outline',
};

/*
|--------------------------------------------------------------------------
| Formatters
|--------------------------------------------------------------------------
*/

function formatDate(date: string | null): string {
    if (!date) {
        return 'No deadline';
    }

    return new Date(date).toLocaleDateString();
}

/*
|--------------------------------------------------------------------------
| Load Assignments
|--------------------------------------------------------------------------
*/

async function loadAssignments(
    page = 1,
    perPage?: number,
) {
    loading.value = true;
    error.value = null;

    try {
        const response =
            await axios.get<AssignmentsResponse>(
                `/sections/${props.section.id}/assignments/data`,
                {
                    params: {
                        page,
                        per_page:
                            perPage ??
                            pagination.value?.per_page ??
                            20,
                        search:
                            search.value || undefined,
                    },
                },
            );

        if (response.data.success) {
            assignments.value =
                response.data.data;

            pagination.value =
                response.data.pagination;
        } else {
            error.value =
                response.data.message ||
                'Failed to load assignments.';
        }
    } catch (err: any) {
        console.error(
            'LOAD ASSIGNMENTS ERROR:',
            err,
        );

        error.value =
            err.response?.data?.message ||
            'Failed to load assignments. Please try again.';
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

function applyFilters() {
    loadAssignments(1);
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function changePage(page: number) {
    if (!pagination.value) {
        return;
    }

    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page
    ) {
        return;
    }

    loadAssignments(page);
}

function changePerPage(perPage: number) {
    loadAssignments(1, perPage);
}

/*
|--------------------------------------------------------------------------
| Toggle Publish
|--------------------------------------------------------------------------
*/

async function togglePublish(
    assignment: Assignment,
) {
    try {
        const response = await axios.post(
            `/assignments/${assignment.id}/toggle-publish`,
        );

        showApiToast(response);

        await loadAssignments(
            pagination.value?.current_page ?? 1,
        );
    } catch (error) {
        console.error(
            'TOGGLE ASSIGNMENT PUBLISH ERROR:',
            error,
        );

        showApiError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Delete Assignment
|--------------------------------------------------------------------------
*/

async function deleteAssignment(
    assignment: Assignment,
) {
    if (
        !confirm(
            `Delete "${assignment.title}"? All submissions will be removed.`,
        )
    ) {
        return;
    }

    try {
        const response = await axios.delete(
            `/assignments/${assignment.id}`,
        );

        showApiToast(response);

        const currentPage =
            pagination.value?.current_page ?? 1;

        const total =
            pagination.value?.total ?? 0;

        const perPage =
            pagination.value?.per_page ?? 20;

        const newLastPage = Math.max(
            1,
            Math.ceil(
                Math.max(total - 1, 0) /
                    perPage,
            ),
        );

        await loadAssignments(
            Math.min(
                currentPage,
                newLastPage,
            ),
        );
    } catch (error) {
        console.error(
            'DELETE ASSIGNMENT ERROR:',
            error,
        );

        showApiError(error);
    }
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadAssignments();
});
</script>

<template>
    <Head
        :title="`Assignments — ${section.name}`"
    />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
    >

        <!-- Header -->
        <div
            class="flex items-start justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold"
                >
                    Assignments
                </h1>

                <p
                    class="text-sm text-muted-foreground"
                >
                    {{ section.subject.code }}
                    ·
                    {{ section.name }}
                    ·
                    {{ section.semester.name }}
                    {{ section.semester.school_year }}
                </p>
            </div>

            <Button as-child>
                <Link
                    :href="`/sections/${section.id}/assignments/create`"
                >
                    <Plus
                        class="mr-2 h-4 w-4"
                    />

                    New Assignment
                </Link>
            </Button>
        </div>

        <!-- Table -->
        <BaseTable
            :columns="columns"
            :data="assignments"
            :pagination="
                pagination ?? undefined
            "
            empty-text="No assignments yet. Create your first assignment."
            :per-page-options="[
                10,
                20,
                25,
                50,
                100,
            ]"
            :loading="loading"
            @update:page="changePage"
            @update:per-page="changePerPage"
        >

            <!-- Toolbar -->
            <template #toolbar>
                <div
                    class="flex w-full flex-wrap gap-3"
                >
                    <div
                        class="relative min-w-48 flex-1"
                    >
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            v-model="search"
                            placeholder="Search assignments..."
                            class="pl-9"
                            @keydown.enter="
                                applyFilters
                            "
                        />
                    </div>

                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="applyFilters"
                    >
                        Search
                    </Button>
                </div>
            </template>

            <!-- Title -->
            <template #cell-title="{ row }">
                <div
                    class="flex flex-col gap-0.5"
                >
                    <Link
                        :href="`/assignments/${row.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ row.title }}
                    </Link>

                    <div
                        class="flex flex-wrap gap-1"
                    >

                        <!-- Category -->
                        <Badge
                            v-if="row.category"
                            variant="outline"
                            class="w-fit text-xs capitalize"
                            :class="{
                                'border-violet-400 text-violet-600':
                                    row.category === 'quiz',

                                'border-red-400 text-red-600':
                                    row.category === 'exam',

                                'border-green-400 text-green-600':
                                    row.category === 'activity',

                                'border-orange-400 text-orange-600':
                                    row.category === 'project',
                            }"
                        >
                            {{ row.category }}
                        </Badge>

                        <!-- Period -->
                        <Badge
                            v-if="row.period"
                            variant="outline"
                            class="w-fit text-xs capitalize"
                            :class="
                                row.period ===
                                'midterm'
                                    ? 'border-blue-400 text-blue-600'
                                    : 'border-amber-400 text-amber-600'
                            "
                        >
                            {{ row.period }}
                        </Badge>
                    </div>
                </div>
            </template>

            <!-- Type -->
            <template #cell-type="{ row }">
                <Badge
                    :variant="
                        typeVariant[row.type]
                    "
                >
                    {{ typeLabel[row.type] }}
                </Badge>
            </template>

            <!-- Due Date -->
            <template #cell-due_date="{ row }">
                <span
                    v-if="row.due_date"
                    class="flex items-center gap-1 text-muted-foreground"
                >
                    <Clock
                        class="h-3.5 w-3.5"
                    />

                    {{ formatDate(row.due_date) }}
                </span>

                <span
                    v-else
                    class="text-xs text-muted-foreground"
                >
                    No deadline
                </span>
            </template>

            <!-- Max Score -->
            <template
                #cell-max_score="{ row }"
            >
                <div class="text-center">
                    {{ row.max_score }}
                </div>
            </template>

            <!-- Submissions -->
            <template
                #cell-submissions_count="{ row }"
            >
                <div class="text-center">
                    {{ row.submissions_count }}
                </div>
            </template>

            <!-- Status -->
            <template
                #cell-is_published="{ row }"
            >
                <div class="text-center">
                    <Badge
                        :variant="
                            row.is_published
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{
                            row.is_published
                                ? 'Published'
                                : 'Draft'
                        }}
                    </Badge>
                </div>
            </template>

            <!-- Actions -->
            <template #cell-actions="{ row }">
                <div
                    class="flex justify-end gap-1"
                >

                    <!-- Publish / Unpublish -->
                    <Button
                        variant="ghost"
                        size="sm"
                        :title="
                            row.is_published
                                ? 'Unpublish'
                                : 'Publish'
                        "
                        :disabled="loading"
                        @click="
                            togglePublish(row)
                        "
                    >
                        <EyeOff
                            v-if="row.is_published"
                            class="h-4 w-4"
                        />

                        <Eye
                            v-else
                            class="h-4 w-4"
                        />
                    </Button>

                    <!-- Edit -->
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="`/assignments/${row.id}/edit`"
                        >
                            <Pencil
                                class="h-4 w-4"
                            />
                        </Link>
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        :disabled="loading"
                        @click="
                            deleteAssignment(row)
                        "
                    >
                        <Trash2
                            class="h-4 w-4"
                        />
                    </Button>

                </div>
            </template>

        </BaseTable>

        <!-- Error -->
        <div
            v-if="error"
            class="text-sm text-destructive"
        >
            {{ error }}
        </div>
    </div>
</template>
