<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import {
    CheckCircle,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';

import BaseTable from '@/components/BaseTable.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Semester = {
    id: string;
    name: string;
    school_year: string;
    start_date: string;
    end_date: string;
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

type SemestersResponse = {
    success: boolean;
    message: string;
    data: Semester[];
    pagination: Pagination;
    filters: {
        search: string | null;
    };
};

const columns = [
    {
        key: 'name',
        label: 'Semester',
    },
    {
        key: 'school_year',
        label: 'School Year',
    },
    {
        key: 'start_date',
        label: 'Start Date',
    },
    {
        key: 'end_date',
        label: 'End Date',
    },
    {
        key: 'is_active',
        label: 'Status',
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

const semesters = ref<Semester[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');

const loading = ref(false);
const error = ref<string | null>(null);
const deleting = ref<string | null>(null);
const activating = ref<string | null>(null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Semesters', href: '/admin/semesters' },
        ],
    },
});

const dateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

function formatDate(value: string) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return dateFormatter.format(date);
}

async function loadSemesters(
    page = 1,
    perPage?: number,
) {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get<SemestersResponse>(
            '/admin/semesters/data',
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
            semesters.value = response.data.data;
            pagination.value = response.data.pagination;
        } else {
            error.value =
                response.data.message ||
                'Failed to load semesters.';
        }
    } catch (err: any) {
        console.error('Failed to load semesters:', err);

        error.value =
            err.response?.data?.message ||
            'Failed to load semesters. Please try again.';

        showApiError(err);
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    loadSemesters(1);
}

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

    loadSemesters(page);
}

function changePerPage(perPage: number) {
    loadSemesters(1, perPage);
}

async function setActive(id: string) {
    if (
        !confirm(
            'Set this semester as the active semester?',
        )
    ) {
        return;
    }

    activating.value = id;

    try {
        const response = await axios.put(
            `/admin/semesters/set-active/${id}`,
        );

        showApiToast(response);

        if (response.data.success) {
            await loadSemesters(
                pagination.value?.current_page ?? 1,
                pagination.value?.per_page ?? 20,
            );
        }
    } catch (err: any) {
        console.error(
            'Failed to set active semester:',
            err,
        );

        showApiError(err);
    } finally {
        activating.value = null;
    }
}

async function deleteSemester(id: string) {
    if (
        !confirm(
            'Delete this semester? This cannot be undone.',
        )
    ) {
        return;
    }

    deleting.value = id;

    try {
        const response = await axios.delete(
            `/admin/semesters/delete/${id}`,
        );

        showApiToast(response);

        if (response.data.success) {
            const currentPage =
                pagination.value?.current_page ?? 1;

            const perPage =
                pagination.value?.per_page ?? 20;

            await loadSemesters(
                currentPage,
                perPage,
            );
        }
    } catch (err: any) {
        console.error(
            'Failed to delete semester:',
            err,
        );

        showApiError(err);
    } finally {
        deleting.value = null;
    }
}

onMounted(() => {
    loadSemesters();
});
</script>

<template>
    <Head title="Semesters" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">
                    Semesters
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ pagination?.total ?? 0 }}
                    total semesters
                </p>
            </div>

            <Button as-child>
                <Link href="/admin/semesters/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Semester
                </Link>
            </Button>
        </div>

        <div
            v-if="error"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <BaseTable
            :columns="columns"
            :data="semesters"
            :pagination="pagination ?? undefined"
            empty-text="No semesters found."
            :per-page-options="[10, 20, 25, 50, 100]"
            :loading="loading"
            @update:page="changePage"
            @update:per-page="changePerPage"
        >
            <template #toolbar>
                <div class="flex w-full flex-wrap gap-3">
                    <div class="relative min-w-48 flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            v-model="search"
                            placeholder="Search semester or school year…"
                            class="pl-9"
                            @keydown.enter="applyFilters"
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

            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium">
                        {{ row.name }}
                    </p>
                </div>
            </template>

            <template #cell-school_year="{ row }">
                <span>
                    {{ row.school_year }}
                </span>
            </template>

            <template #cell-start_date="{ row }">
                <span class="text-sm">
                    {{ formatDate(row.start_date) }}
                </span>
            </template>

            <template #cell-end_date="{ row }">
                <span class="text-sm">
                    {{ formatDate(row.end_date) }}
                </span>
            </template>

            <template #cell-is_active="{ row }">
                <Badge
                    :variant="
                        row.is_active
                            ? 'default'
                            : 'secondary'
                    "
                >
                    {{
                        row.is_active
                            ? 'Active'
                            : 'Inactive'
                    }}
                </Badge>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button
                        v-if="!row.is_active"
                        variant="ghost"
                        size="sm"
                        :disabled="
                            activating === row.id
                        "
                        @click="setActive(row.id)"
                    >
                        <CheckCircle
                            class="h-4 w-4"
                        />
                    </Button>

                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="`/admin/semesters/edit/${row.id}`"
                        >
                            <Pencil
                                class="h-4 w-4"
                            />
                        </Link>
                    </Button>

                    <Button
                        v-if="!row.is_active"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        :disabled="
                            deleting === row.id
                        "
                        @click="
                            deleteSemester(row.id)
                        "
                    >
                        <Trash2
                            class="h-4 w-4"
                        />
                    </Button>
                </div>
            </template>
        </BaseTable>
    </div>
</template>

