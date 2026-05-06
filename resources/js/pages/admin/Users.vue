<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Users, Search, Shield, GraduationCap, BookOpen, CheckCircle } from 'lucide-vue-next';
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
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
    email_verified_at: string | null;
};

type Paginator = {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    users: Paginator;
    filters: { role?: string; search?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Panel', href: '/admin' },
            { title: 'Users', href: '/admin/users' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? 'all');
const editingId = ref<number | null>(null);

function applyFilters() {
    router.get('/admin/users', {
        search: search.value || undefined,
        role: roleFilter.value === 'all' ? undefined : roleFilter.value,
    }, { preserveState: true, replace: true });
}

function roleIcon(role: string) {
    return { admin: Shield, faculty: BookOpen, student: GraduationCap }[role] ?? Users;
}

function roleBadgeVariant(role: string): 'default' | 'secondary' | 'outline' {
    return { admin: 'default', faculty: 'secondary', student: 'outline' }[role] as any ?? 'outline';
}

const roleForm = useForm({ role: '' });

function openEdit(user: User) {
    editingId.value = user.id;
    roleForm.role = user.role;
}

function saveRole(userId: number) {
    roleForm.put(`/admin/users/${userId}/role`, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <Users class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">User Management</h1>
                <p class="text-sm text-muted-foreground">{{ users.total }} total users</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-48">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="search"
                    placeholder="Search name or email…"
                    class="pl-9"
                    @keydown.enter="applyFilters"
                />
            </div>
            <Select v-model="roleFilter" @update:model-value="applyFilters">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="All roles" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All roles</SelectItem>
                    <SelectItem value="admin">Admin</SelectItem>
                    <SelectItem value="faculty">Faculty</SelectItem>
                    <SelectItem value="student">Student</SelectItem>
                </SelectContent>
            </Select>
            <Button variant="outline" @click="applyFilters">Search</Button>
        </div>

        <!-- Table -->
        <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">User</th>
                        <th class="px-4 py-3 text-left font-medium">Role</th>
                        <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Joined</th>
                        <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Verified</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="users.data.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No users found.</td>
                    </tr>
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="editingId === user.id" class="flex items-center gap-2">
                                <Select v-model="roleForm.role" class="w-32">
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="faculty">Faculty</SelectItem>
                                        <SelectItem value="student">Student</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button size="sm" class="h-7 text-xs px-2" @click="saveRole(user.id)" :disabled="roleForm.processing">
                                    <CheckCircle class="h-3.5 w-3.5" />
                                </Button>
                                <Button size="sm" variant="ghost" class="h-7 text-xs px-2" @click="editingId = null">✕</Button>
                            </div>
                            <Badge v-else :variant="roleBadgeVariant(user.role)" class="capitalize text-xs">
                                <component :is="roleIcon(user.role)" class="mr-1 h-3 w-3" />
                                {{ user.role }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground hidden sm:table-cell">{{ user.created_at }}</td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span v-if="user.email_verified_at" class="text-xs text-green-600">Verified</span>
                            <span v-else class="text-xs text-orange-500">Unverified</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-xs h-7"
                                @click="openEdit(user)"
                            >
                                Change Role
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="flex items-center justify-between text-sm">
            <p class="text-muted-foreground">
                Page {{ users.current_page }} of {{ users.last_page }} · {{ users.total }} users
            </p>
            <div class="flex gap-1">
                <template v-for="link in users.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-1.5 text-sm transition-colors hover:bg-muted/40"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : ''"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
