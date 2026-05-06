<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Users, GraduationCap, Layers, BookOpen, ClipboardList, FileCheck, Loader2, Shield
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Stats = {
    users: number;
    faculty: number;
    students: number;
    sections: number;
    enrollments: number;
    submissions: number;
    pendingGrading: number;
};

type SemesterStat = {
    id: number;
    name: string;
    school_year: string;
    is_active: boolean;
    sections_count: number;
    enrollments_count: number;
};

type RecentSection = {
    id: number;
    name: string;
    subject_code: string;
    faculty_name: string;
    semester: string;
    enrollments_count: number;
};

type ActiveSemester = { id: number; name: string; school_year: string } | null;

defineProps<{
    stats: Stats;
    activeSemester: ActiveSemester;
    semesterStats: SemesterStat[];
    recentSections: RecentSection[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin Panel', href: '/admin' }],
    },
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <Shield class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">Admin Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    System overview ·
                    <span v-if="activeSemester">
                        Active: <span class="font-medium text-foreground">{{ activeSemester.name }} {{ activeSemester.school_year }}</span>
                    </span>
                    <span v-else class="text-orange-500">No active semester</span>
                </p>
            </div>
            <div class="ml-auto flex gap-2">
                <Button size="sm" variant="outline" as-child>
                    <Link href="/admin/users">Manage Users</Link>
                </Button>
                <Button size="sm" as-child>
                    <Link href="/admin/reports">View Reports</Link>
                </Button>
            </div>
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.users }}</p>
                    <p class="text-xs text-muted-foreground">{{ stats.faculty }} faculty · {{ stats.students }} students</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Sections</CardTitle>
                    <Layers class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.sections }}</p>
                    <p class="text-xs text-muted-foreground">all time</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Enrollments</CardTitle>
                    <GraduationCap class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.enrollments }}</p>
                    <p class="text-xs text-muted-foreground">active</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Submissions</CardTitle>
                    <ClipboardList class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.submissions }}</p>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Loader2 v-if="stats.pendingGrading" class="h-3 w-3 animate-spin text-orange-500" />
                        <span v-if="stats.pendingGrading" class="text-orange-500">{{ stats.pendingGrading }} grading</span>
                        <span v-else>all graded</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Semester breakdown -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 font-semibold text-sm">Enrollment by Semester</div>
                <div v-if="semesterStats.length === 0" class="p-6 text-center text-sm text-muted-foreground">No semesters yet.</div>
                <div v-else class="divide-y">
                    <div
                        v-for="sem in semesterStats"
                        :key="sem.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ sem.name }} {{ sem.school_year }}</p>
                            <p class="text-xs text-muted-foreground">{{ sem.sections_count }} sections</p>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <Badge v-if="sem.is_active" variant="default" class="text-xs">Active</Badge>
                            <span class="font-bold">{{ sem.enrollments_count }}</span>
                            <span class="text-muted-foreground text-xs">enrolled</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent sections -->
            <div class="rounded-xl border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <span class="font-semibold text-sm">Recent Sections</span>
                    <Button variant="ghost" size="sm" as-child>
                        <Link href="/sections">View all</Link>
                    </Button>
                </div>
                <div v-if="recentSections.length === 0" class="p-6 text-center text-sm text-muted-foreground">No sections yet.</div>
                <div v-else class="divide-y">
                    <div
                        v-for="section in recentSections"
                        :key="section.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ section.subject_code }} · {{ section.name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ section.faculty_name }} · {{ section.semester }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold">{{ section.enrollments_count }}</p>
                            <p class="text-xs text-muted-foreground">students</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link href="/admin/users" class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40">
                <Users class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Users</span>
            </Link>
            <Link href="/admin/reports" class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40">
                <FileCheck class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Reports</span>
            </Link>
            <Link href="/semesters" class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40">
                <BookOpen class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Semesters</span>
            </Link>
            <Link href="/sections" class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40">
                <Layers class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Sections</span>
            </Link>
        </div>
    </div>
</template>
