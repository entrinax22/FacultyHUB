<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { Plus, Pencil, Trash2, Eye } from 'lucide-vue-next';
import { ref, onMounted, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import BaseTable from '@/components/BaseTable.vue';

import { showApiToast, showApiError } from '@/lib/flashToast';

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
    enrollments_count: number;
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

type StudentsResponse = {
    success: boolean;
    message: string;
    data: Student[];
    pagination: Pagination;
};

const columns = [
    {
        key: 'student_no',
        label: 'Student No.',
    },
    {
        key: 'name',
        label: 'Name',
    },
    {
        key: 'email',
        label: 'Email',
    },
    {
        key: 'course_year',
        label: 'Course / Year',
    },
    {
        key: 'enrollments_count',
        label: 'Enrollments',
        align: 'center',
    },
    {
        key: 'actions',
        label: 'Actions',
        align: 'right',
    },
];

const students = ref<Student[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');
const loading = ref(false);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Students', href: '/students' },
        ],
    },
});

async function fetchStudents(
    page = 1,
    perPage = pagination.value?.per_page ?? 20,
) {
    loading.value = true;

    try {
        const response = await axios.get<StudentsResponse>('/students/data', {
            params: {
                page,
                per_page: perPage,
                search: search.value || undefined,
            },
        });

        students.value = response.data.data ?? [];
        pagination.value = response.data.pagination;
    } catch (error) {
        console.error('Failed to load students:', error);
        showApiError(error);
    } finally {
        loading.value = false;
    }
}

function changePage(page: number) {
    fetchStudents(page, pagination.value?.per_page ?? 20);
}

function changePerPage(perPage: number) {
    fetchStudents(1, perPage);
}

function handleSearch() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        fetchStudents(1, pagination.value?.per_page ?? 20);
    }, 300);
}

async function deleteStudent(id: string, name: string) {
    if (!confirm(`Remove ${name} from the system? This cannot be undone.`)) {
        return;
    }

    try {
        const response = await axios.delete(`/students/delete/${id}`);

        showApiToast(response);

        await fetchStudents(
            pagination.value?.current_page ?? 1,
            pagination.value?.per_page ?? 20,
        );
    } catch (error) {
        console.error('Student delete error:', error);
        showApiError(error);
    }
}

onMounted(() => {
    fetchStudents();
});
</script>

<template>
    <Head title="Students" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Students</h1>

                <p v-if="pagination" class="text-sm text-muted-foreground">
                    {{ pagination.total }} total students
                </p>
            </div>

            <Button as-child>
                <Link href="/students/create">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Student
                </Link>
            </Button>
        </div>

        <!-- Table -->
        <BaseTable
            :columns="columns"
            :data="students"
            :pagination="pagination ?? undefined"
            empty-text="No students found."
            :per-page-options="[10, 20, 25, 50, 100]"
            :loading="loading"
            @update:page="changePage"
            @update:per-page="changePerPage"
        >
            <!-- Search -->
            <template #toolbar>
                <div class="w-full max-w-sm">
                    <Input
                        v-model="search"
                        placeholder="Search by name, ID, email, course..."
                        @input="handleSearch"
                    />
                </div>
            </template>

            <!-- Student Number -->
            <template #cell-student_no="{ row }">
                <span class="font-mono text-xs">
                    {{ row.student_no }}
                </span>
            </template>

            <!-- Name -->
            <template #cell-name="{ row }">
                <Link
                    :href="`/students/${row.id}`"
                    class="font-medium hover:underline"
                >
                    {{ row.last_name }}, {{ row.first_name }}
                </Link>
            </template>

            <!-- Email -->
            <template #cell-email="{ row }">
                <span class="text-muted-foreground">
                    {{ row.email }}
                </span>
            </template>

            <!-- Course / Year -->
            <template #cell-course_year="{ row }">
                <span class="text-muted-foreground">
                    {{ row.course }} — Year {{ row.year_level }}
                </span>
            </template>

            <!-- Enrollments -->
            <template #cell-enrollments_count="{ row }">
                <div class="text-center text-muted-foreground">
                    {{ row.enrollments_count }}
                </div>
            </template>

            <!-- Actions -->
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <!-- View -->
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/students/${row.id}`">
                            <Eye class="h-4 w-4" />
                        </Link>
                    </Button>

                    <!-- Edit -->
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/students/edit/${row.id}`">
                            <Pencil class="h-4 w-4" />
                        </Link>
                    </Button>

                    <!-- Delete -->
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="
                            deleteStudent(
                                row.id,
                                `${row.first_name} ${row.last_name}`,
                            )
                        "
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </template>
        </BaseTable>
    </div>
</template>
