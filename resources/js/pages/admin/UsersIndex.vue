<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

import { ref, onMounted } from 'vue';
import {
    Users,
    Search,
    Shield,
    GraduationCap,
    BookOpen,
    CheckCircle,
} from 'lucide-vue-next';

import BaseTable from '@/components/BaseTable.vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type User = {
    id: string;
    name: string;
    email: string;
    role: string;
    created_at: string;
    email_verified_at: string | null;
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

type UsersResponse = {
    success: boolean;
    message: string;
    data: User[];
    pagination: Pagination;
    filters: {
        role: string | null;
        search: string | null;
    };
};

const columns = [
    {
        key: 'user',
        label: 'User',
    },
    {
        key: 'role',
        label: 'Role',
    },
    {
        key: 'created_at',
        label: 'Joined',
        class: 'hidden sm:table-cell',
    },
    {
        key: 'email_verified_at',
        label: 'Verified',
        class: 'hidden sm:table-cell',
    },
    {
        key: 'actions',
        label: 'Actions',
        class: 'text-right',
        headerClass: 'text-right',
    },
];

const users = ref<User[]>([]);
const pagination = ref<Pagination | null>(null);

const search = ref('');
const roleFilter = ref('all');

const loading = ref(false);
const error = ref<string | null>(null);

const editingId = ref<string | null>(null);

const roleForm = useForm({
    role: '',
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Panel', href: '/admin' },
            { title: 'Users', href: '/admin/users' },
        ],
    },
});

/**
 * Load users from API
 */
async function loadUsers(page = 1, perPage?: number) {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get<UsersResponse>(
            '/admin/users/data',
            {
                params: {
                    page,
                    per_page: perPage ?? pagination.value?.per_page ?? 20,
                    search: search.value || undefined,
                    role:
                        roleFilter.value === 'all'
                            ? undefined
                            : roleFilter.value,
                },
            },
        );

        if (response.data.success) {
            users.value = response.data.data;
            pagination.value = response.data.pagination;
        } else {
            error.value =
                response.data.message || 'Failed to load users.';
        }
    } catch (err: any) {
        console.error(err);

        error.value =
            err.response?.data?.message ||
            'Failed to load users. Please try again.';
    } finally {
        loading.value = false;
    }
}

/**
 * Apply search and role filters
 */
function applyFilters() {
    loadUsers(1);
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

    loadUsers(page);
}

/**
 * Change number of records per page
 */
function changePerPage(perPage: number) {
    loadUsers(1, perPage);
}

/**
 * Role icon
 */
function roleIcon(role: string) {
    return (
        {
            admin: Shield,
            faculty: BookOpen,
            student: GraduationCap,
        }[role] ?? Users
    );
}

/**
 * Role badge
 */
function roleBadgeVariant(
    role: string,
): 'default' | 'secondary' | 'outline' {
    return (
        ({
            admin: 'default',
            faculty: 'secondary',
            student: 'outline',
        }[role] as 'default' | 'secondary' | 'outline') ?? 'outline'
    );
}

/**
 * Open role editor
 */
function openEdit(user: User) {
    editingId.value = user.id;
    roleForm.role = user.role;
}

/**
 * Save role
 */
async function saveRole(userId: string) {
    try {
        const response = await axios.put(
            `/admin/users/${userId}/role`,
            {
                role: roleForm.role,
            },
        );

        showApiToast(response);

        editingId.value = null;

        await loadUsers(
            pagination.value?.current_page ?? 1
        );

    } catch (err: any) {
        console.error('UPDATE ROLE ERROR:', err);

        showApiError(err);
    }
}

/**
 * Initial load
 */
onMounted(() => {
    loadUsers();
});
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <Users class="h-5 w-5 text-primary" />
            </div>

            <div>
                <h1 class="text-xl font-semibold">
                    User Management
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ pagination?.total ?? 0 }} total users
                </p>
            </div>
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
            :data="users"
            :pagination="pagination ?? undefined"
            empty-text="No users found."
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
                            placeholder="Search name or email…"
                            class="pl-9"
                            @keydown.enter="applyFilters"
                        />
                    </div>

                    <!-- Role -->
                    <Select
                        v-model="roleFilter"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="All roles" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">
                                All roles
                            </SelectItem>

                            <SelectItem value="admin">
                                Admin
                            </SelectItem>

                            <SelectItem value="faculty">
                                Faculty
                            </SelectItem>

                            <SelectItem value="student">
                                Student
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Search button -->
                    <Button
                        variant="outline"
                        :disabled="loading"
                        @click="applyFilters"
                    >
                        Search
                    </Button>
                </div>
            </template>

            <!-- User -->
            <template #cell-user="{ row }">
                <p class="font-medium">
                    {{ row.name }}
                </p>

                <p class="text-xs text-muted-foreground">
                    {{ row.email }}
                </p>
            </template>

            <!-- Role -->
            <template #cell-role="{ row }">
                <div
                    v-if="editingId === row.id"
                    class="flex items-center gap-2"
                >
                    <Select v-model="roleForm.role">
                        <SelectTrigger class="h-8 w-32 text-xs">
                            <SelectValue />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="admin">
                                Admin
                            </SelectItem>

                            <SelectItem value="faculty">
                                Faculty
                            </SelectItem>

                            <SelectItem value="student">
                                Student
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        size="sm"
                        class="h-7 px-2 text-xs"
                        :disabled="roleForm.processing"
                        @click="saveRole(row.id)"
                    >
                        <CheckCircle class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 px-2 text-xs"
                        @click="editingId = null"
                    >
                        ✕
                    </Button>
                </div>

                <Badge
                    v-else
                    :variant="roleBadgeVariant(row.role)"
                    class="text-xs capitalize"
                >
                    <component
                        :is="roleIcon(row.role)"
                        class="mr-1 h-3 w-3"
                    />

                    {{ row.role }}
                </Badge>
            </template>

            <!-- Joined -->
            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground">
                    {{ row.created_at }}
                </span>
            </template>

            <!-- Verified -->
            <template #cell-email_verified_at="{ row }">
                <span
                    v-if="row.email_verified_at"
                    class="text-xs text-green-600"
                >
                    Verified
                </span>

                <span
                    v-else
                    class="text-xs text-orange-500"
                >
                    Unverified
                </span>
            </template>

            <!-- Actions -->
            <template #cell-actions="{ row }">
                <Button
                    size="sm"
                    variant="ghost"
                    class="h-7 text-xs"
                    @click="openEdit(row)"
                >
                    Change Role
                </Button>
            </template>
        </BaseTable>
    </div>
</template>