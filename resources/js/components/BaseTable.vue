<script setup lang="ts">
import { computed } from 'vue';
import {
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';

type Column = {
    key: string;
    label: string;
    class?: string;
    headerClass?: string;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    has_more_pages?: boolean;
};

const props = withDefaults(
    defineProps<{
        columns: Column[];
        data: any[];
        pagination?: Pagination;
        emptyText?: string;
        perPageOptions?: number[];
        showPageSize?: boolean;
        showPagination?: boolean;
        loading?: boolean;
    }>(),
    {
        emptyText: 'No records found.',
        perPageOptions: () => [10, 25, 50, 100],
        showPageSize: true,
        showPagination: true,
        loading: false,
    },
);

const emit = defineEmits<{
    'update:page': [page: number];
    'update:perPage': [value: number];
}>();

/*
|--------------------------------------------------------------------------
| Showing From
|--------------------------------------------------------------------------
*/

const showingFrom = computed(() => {
    if (!props.pagination || props.pagination.total === 0) {
        return 0;
    }

    return (
        props.pagination.from ??
        (props.pagination.current_page - 1) *
            props.pagination.per_page +
            1
    );
});

/*
|--------------------------------------------------------------------------
| Showing To
|--------------------------------------------------------------------------
*/

const showingTo = computed(() => {
    if (!props.pagination || props.pagination.total === 0) {
        return 0;
    }

    return (
        props.pagination.to ??
        Math.min(
            props.pagination.current_page *
                props.pagination.per_page,
            props.pagination.total,
        )
    );
});

/*
|--------------------------------------------------------------------------
| Page Numbers
|--------------------------------------------------------------------------
|
| Example:
|
| 1 2 3 ... 10
|
| or
|
| 1 ... 4 5 6 ... 10
|
*/

const pageNumbers = computed<(number | 'ellipsis')[]>(() => {
    if (!props.pagination) {
        return [];
    }

    const current = props.pagination.current_page;
    const last = props.pagination.last_page;

    // Show everything when there are only a few pages
    if (last <= 7) {
        return Array.from(
            { length: last },
            (_, index) => index + 1,
        );
    }

    const pages: (number | 'ellipsis')[] = [];

    // Always show first page
    pages.push(1);

    /*
    |--------------------------------------------------------------------------
    | Near beginning
    |--------------------------------------------------------------------------
    */

    if (current <= 4) {
        pages.push(2, 3, 4, 5);
        pages.push('ellipsis');
        pages.push(last);

        return pages;
    }

    /*
    |--------------------------------------------------------------------------
    | Near end
    |--------------------------------------------------------------------------
    */

    if (current >= last - 3) {
        pages.push('ellipsis');
        pages.push(
            last - 4,
            last - 3,
            last - 2,
            last - 1,
            last,
        );

        return pages;
    }

    /*
    |--------------------------------------------------------------------------
    | Middle
    |--------------------------------------------------------------------------
    */

    pages.push('ellipsis');
    pages.push(current - 1, current, current + 1);
    pages.push('ellipsis');
    pages.push(last);

    return pages;
});

/*
|--------------------------------------------------------------------------
| Page Change
|--------------------------------------------------------------------------
*/

function changePage(page: number) {
    if (!props.pagination || props.loading) {
        return;
    }

    if (
        page < 1 ||
        page > props.pagination.last_page ||
        page === props.pagination.current_page
    ) {
        return;
    }

    emit('update:page', page);
}

/*
|--------------------------------------------------------------------------
| Previous Page
|--------------------------------------------------------------------------
*/

function previousPage() {
    if (!props.pagination) {
        return;
    }

    changePage(props.pagination.current_page - 1);
}

/*
|--------------------------------------------------------------------------
| Next Page
|--------------------------------------------------------------------------
*/

function nextPage() {
    if (!props.pagination) {
        return;
    }

    changePage(props.pagination.current_page + 1);
}

/*
|--------------------------------------------------------------------------
| Per Page
|--------------------------------------------------------------------------
*/

function changePerPage(value: string) {
    emit('update:perPage', Number(value));
}
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-card shadow-sm"
    >
        <!-- =========================================================
             TOOLBAR
        ========================================================== -->
        <div
            v-if="$slots.toolbar || showPageSize"
            class="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <!-- Custom toolbar -->
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <slot name="toolbar" />
            </div>

            <!-- Page size -->
            <div
                v-if="showPageSize"
                class="flex shrink-0 items-center gap-2 text-sm text-muted-foreground"
            >
                <span>Show</span>

                <select
                    :value="pagination?.per_page"
                    :disabled="loading"
                    class="h-8 rounded-md border bg-background px-2 text-sm text-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    @change="
                        changePerPage(
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option
                        v-for="option in perPageOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>

                <span>entries</span>
            </div>
        </div>

        <!-- =========================================================
             TABLE
        ========================================================== -->
        <div class="relative overflow-x-auto">

            <!-- Loading overlay -->
            <div
                v-if="loading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-background/60 backdrop-blur-[1px]"
            >
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <div
                        class="h-4 w-4 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-primary"
                    />

                    Loading...
                </div>
            </div>

            <table class="w-full text-sm">
                <!-- Header -->
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left font-medium"
                            :class="[
                                column.class,
                                column.headerClass,
                            ]"
                        >
                            <slot
                                :name="`header-${column.key}`"
                                :column="column"
                            >
                                {{ column.label }}
                            </slot>
                        </th>
                    </tr>
                </thead>

                <!-- Body -->
                <tbody class="divide-y">

                    <!-- Empty -->
                    <tr v-if="data.length === 0 && !loading">
                        <td
                            :colspan="columns.length"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            <slot name="empty">
                                {{ emptyText }}
                            </slot>
                        </td>
                    </tr>

                    <!-- Rows -->
                    <tr
                        v-for="(row, rowIndex) in data"
                        :key="row.id ?? rowIndex"
                        class="hover:bg-muted/20"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3"
                            :class="column.class"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="row[column.key]"
                                :index="rowIndex"
                            >
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- =========================================================
             PAGINATION
        ========================================================== -->
        <div
            v-if="
                showPagination &&
                pagination &&
                pagination.total > 0
            "
            class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <!-- Results count -->
            <p class="text-sm text-muted-foreground">
                Showing

                <span class="font-medium text-foreground">
                    {{ showingFrom }}
                </span>

                to

                <span class="font-medium text-foreground">
                    {{ showingTo }}
                </span>

                of

                <span class="font-medium text-foreground">
                    {{ pagination.total }}
                </span>

                results
            </p>

            <!-- Pagination controls -->
            <div
                v-if="pagination.last_page > 1"
                class="flex items-center gap-1"
            >
                <!-- Previous -->
                <button
                    type="button"
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                    :disabled="
                        loading ||
                        pagination.current_page === 1
                    "
                    @click="previousPage"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>

                <!-- Page numbers -->
                <template
                    v-for="(page, index) in pageNumbers"
                    :key="`${page}-${index}`"
                >
                    <!-- Ellipsis -->
                    <span
                        v-if="page === 'ellipsis'"
                        class="inline-flex h-8 min-w-8 items-center justify-center px-1 text-sm text-muted-foreground"
                    >
                        ...
                    </span>

                    <!-- Page -->
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-3 text-sm transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                        :class="{
                            'border-primary bg-primary text-primary-foreground hover:bg-primary':
                                page === pagination.current_page,
                        }"
                        :disabled="
                            loading ||
                            page === pagination.current_page
                        "
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>
                </template>

                <!-- Next -->
                <button
                    type="button"
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-50"
                    :disabled="
                        loading ||
                        pagination.current_page ===
                            pagination.last_page
                    "
                    @click="nextPage"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- =========================================================
             SINGLE PAGE
        ========================================================== -->
        <div
            v-else-if="
                pagination &&
                pagination.total > 0
            "
            class="border-t px-4 py-3"
        >
            <p class="text-sm text-muted-foreground">
                Showing

                <span class="font-medium text-foreground">
                    {{ showingFrom }}
                </span>

                to

                <span class="font-medium text-foreground">
                    {{ showingTo }}
                </span>

                of

                <span class="font-medium text-foreground">
                    {{ pagination.total }}
                </span>

                results
            </p>
        </div>
    </div>
</template>