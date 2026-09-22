<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import {
    Plus,
    Pencil,
    Trash2,
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

type Subject = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    units: number;
    sections_count: number;
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

type SubjectsResponse = {
    success: boolean;
    message: string;
    data: Subject[];
    pagination: Pagination;
    filters: {
        search: string | null;
    };
};

const columns = [
    {
        key: 'code',
        label: 'Code',
    },
    {
        key: 'name',
        label: 'Name',
    },
    {
        key: 'units',
        label: 'Units',
        class: 'text-center',
        headerClass: 'text-center',
    },
    {
        key: 'sections_count',
        label: 'Sections',
        class: 'text-center',
        headerClass: 'text-center',
    },
    {
        key: 'actions',
        label: 'Actions',
        class: 'text-right',
        headerClass: 'text-right',
    },
];

const subjects = ref<Subject[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');

const loading = ref(false);
const error = ref<string | null>(null);
const deleting = ref<string | null>(null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Subjects', href: '/subjects' },
        ],
    },
});

/**
 * Load subjects from API
 */
async function loadSubjects(
    page = 1,
    perPage?: number,
) {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get<SubjectsResponse>(
            '/subjects/data',
            {
                params: {
                    page,

                    per_page:
                        perPage ??
                        pagination.value?.per_page ??
                        20,

                    search:
                        search.value ||
                        undefined,
                },
            },
        );

        if (response.data.success) {
            subjects.value = response.data.data;
            pagination.value = response.data.pagination;
        } else {
            error.value =
                response.data.message ||
                'Failed to load subjects.';
        }
    } catch (err: any) {
        console.error(
            'Failed to load subjects:',
            err,
        );

        error.value =
            err.response?.data?.message ||
            'Failed to load subjects. Please try again.';

        showApiError(err);
    } finally {
        loading.value = false;
    }
}

/**
 * Apply search filter
 */
function applyFilters() {
    loadSubjects(1);
}

/**
 * Change page
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

    loadSubjects(page);
}

/**
 * Change number of records per page
 */
function changePerPage(perPage: number) {
    loadSubjects(1, perPage);
}

/**
 * Delete subject
 */
async function deleteSubject(id: string) {
    if (!confirm('Delete this subject?')) {
        return;
    }

    deleting.value = id;

    try {
        const response = await axios.delete(
            `/subjects/delete/${id}`,
        );

        showApiToast(response);

        if (response.data.success) {
            await loadSubjects(
                pagination.value?.current_page ?? 1,
                pagination.value?.per_page ?? 20,
            );
        }
    } catch (err: any) {
        console.error(
            'Failed to delete subject:',
            err,
        );

        showApiError(err);
    } finally {
        deleting.value = null;
    }
}

/**
 * Initial load
 */
onMounted(() => {
    loadSubjects();
});
</script>

<template>
    <Head title="Subjects" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">
                    Subjects
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ pagination?.total ?? 0 }}
                    total subjects
                </p>
            </div>

            <Button as-child>
                <Link href="/subjects/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Subject
                </Link>
            </Button>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <!-- Table -->
        <BaseTable
            :columns="columns"
            :data="subjects"
            :pagination="pagination ?? undefined"
            empty-text="No subjects found."
            :per-page-options="[10, 20, 25, 50, 100]"
            :loading="loading"
            @update:page="changePage"
            @update:per-page="changePerPage"
        >

            <!-- Toolbar -->
            <template #toolbar>
                <div class="flex w-full flex-wrap gap-3">

                    <!-- Search -->
                    <div class="relative min-w-48 flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            v-model="search"
                            placeholder="Search code, name, or description…"
                            class="pl-9"
                            @keydown.enter="applyFilters"
                        />
                    </div>

                    <!-- Search Button -->
                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="applyFilters"
                    >
                        Search
                    </Button>

                </div>
            </template>

            <!-- Code -->
            <template #cell-code="{ row }">
                <Badge variant="outline">
                    {{ row.code }}
                </Badge>
            </template>

            <!-- Name -->
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium">
                        {{ row.name }}
                    </p>

                    <p
                        v-if="row.description"
                        class="line-clamp-1 text-xs text-muted-foreground"
                    >
                        {{ row.description }}
                    </p>
                </div>
            </template>

            <!-- Units -->
            <template #cell-units="{ row }">
                <span>
                    {{ row.units }}
                </span>
            </template>

            <!-- Sections -->
            <template #cell-sections_count="{ row }">
                <span>
                    {{ row.sections_count }}
                </span>
            </template>

            <!-- Actions -->
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">

                    <!-- Edit -->
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="`/subjects/edit/${row.id}`"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        :disabled="deleting === row.id"
                        @click="deleteSubject(row.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>

                </div>
            </template>

        </BaseTable>
    </div>
</template>