<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Users,
    GraduationCap,
    Layers,
    BookOpen,
    ClipboardList,
    FileCheck,
    Loader2,
    Shield,
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

type ActiveSemester = {
    id: number;
    name: string;
    school_year: string;
} | null;

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

    <div class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6">
        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- Title -->
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
                >
                    <Shield class="h-5 w-5 text-primary" />
                </div>

                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold sm:text-2xl">
                        Admin Dashboard
                    </h1>

                    <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                        System overview ·

                        <span v-if="activeSemester">
                            Active:
                            <span class="font-medium text-foreground">
                                {{ activeSemester.name }}
                                {{ activeSemester.school_year }}
                            </span>
                        </span>

                        <span
                            v-else
                            class="font-medium text-orange-500"
                        >
                            No active semester
                        </span>
                    </p>
                </div>
            </div>

            <!-- Header Actions -->
            <div
                class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:w-auto lg:shrink-0"
            >
                <Button
                    size="sm"
                    variant="outline"
                    as-child
                    class="w-full lg:w-auto"
                >
                    <Link href="/admin/users">
                        <Users class="mr-2 h-4 w-4 shrink-0" />
                        <span>Manage Users</span>
                    </Link>
                </Button>

                <Button
                    size="sm"
                    as-child
                    class="w-full lg:w-auto"
                >
                    <Link href="/admin/reports">
                        <FileCheck class="mr-2 h-4 w-4 shrink-0" />
                        <span>View Reports</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- STATS -->
        <!-- ========================================================= -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
            <!-- Total Users -->
            <Card class="min-w-0 overflow-hidden">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Total Users
                    </CardTitle>

                    <Users class="h-4 w-4 shrink-0 text-muted-foreground" />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.users }}
                    </p>

                    <p class="truncate text-[10px] text-muted-foreground sm:text-xs">
                        {{ stats.faculty }} faculty ·
                        {{ stats.students }} students
                    </p>
                </CardContent>
            </Card>

            <!-- Sections -->
            <Card class="min-w-0 overflow-hidden">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Sections
                    </CardTitle>

                    <Layers class="h-4 w-4 shrink-0 text-muted-foreground" />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.sections }}
                    </p>

                    <p class="text-[10px] text-muted-foreground sm:text-xs">
                        all time
                    </p>
                </CardContent>
            </Card>

            <!-- Enrollments -->
            <Card class="min-w-0 overflow-hidden">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Enrollments
                    </CardTitle>

                    <GraduationCap
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.enrollments }}
                    </p>

                    <p class="text-[10px] text-muted-foreground sm:text-xs">
                        active
                    </p>
                </CardContent>
            </Card>

            <!-- Submissions -->
            <Card class="min-w-0 overflow-hidden">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Submissions
                    </CardTitle>

                    <ClipboardList
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.submissions }}
                    </p>

                    <div
                        class="flex min-w-0 items-center gap-1 text-[10px] sm:text-xs"
                    >
                        <Loader2
                            v-if="stats.pendingGrading"
                            class="h-3 w-3 shrink-0 animate-spin text-orange-500"
                        />

                        <span
                            v-if="stats.pendingGrading"
                            class="truncate text-orange-500"
                        >
                            {{ stats.pendingGrading }} grading
                        </span>

                        <span
                            v-else
                            class="truncate text-muted-foreground"
                        >
                            all graded
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ========================================================= -->
        <!-- SEMESTER + RECENT SECTIONS -->
        <!-- ========================================================= -->
        <div class="grid min-w-0 gap-4 lg:grid-cols-2 lg:gap-6">
            <!-- Semester Breakdown -->
            <div class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="border-b px-4 py-3 text-sm font-semibold">
                    Enrollment by Semester
                </div>

                <div
                    v-if="semesterStats.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No semesters yet.
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="sem in semesterStats"
                        :key="sem.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium"
                                :title="`${sem.name} ${sem.school_year}`"
                            >
                                {{ sem.name }}
                                {{ sem.school_year }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                {{ sem.sections_count }}
                                section{{
                                    sem.sections_count !== 1 ? 's' : ''
                                }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 text-sm">
                            <Badge
                                v-if="sem.is_active"
                                variant="default"
                                class="text-xs"
                            >
                                Active
                            </Badge>

                            <span class="font-bold">
                                {{ sem.enrollments_count }}
                            </span>

                            <span class="text-xs text-muted-foreground">
                                enrolled
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sections -->
            <div class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm">
                <div
                    class="flex items-center justify-between gap-3 border-b px-4 py-3"
                >
                    <span class="text-sm font-semibold">
                        Recent Sections
                    </span>

                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="shrink-0"
                    >
                        <Link href="/sections">
                            View all
                        </Link>
                    </Button>
                </div>

                <div
                    v-if="recentSections.length === 0"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No sections yet.
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="section in recentSections"
                        :key="section.id"
                        class="flex min-w-0 items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-medium"
                                :title="`${section.subject_code} · ${section.name}`"
                            >
                                {{ section.subject_code }} ·
                                {{ section.name }}
                            </p>

                            <p
                                class="truncate text-xs text-muted-foreground"
                                :title="`${section.faculty_name} · ${section.semester}`"
                            >
                                {{ section.faculty_name }} ·
                                {{ section.semester }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="font-semibold">
                                {{ section.enrollments_count }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                students
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- QUICK ACTIONS -->
        <!-- ========================================================= -->
        <div class="min-w-0">
            <div class="mb-3">
                <h2 class="text-sm font-semibold">
                    Quick Actions
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Users -->
                <Link
                    href="/admin/users"
                    class="group flex min-w-0 items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <Users class="h-5 w-5 text-primary" />
                    </div>

                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            Manage Users
                        </p>

                        <p class="truncate text-xs text-muted-foreground">
                            Manage faculty and student accounts
                        </p>
                    </div>
                </Link>

                <!-- Reports -->
                <Link
                    href="/admin/reports"
                    class="group flex min-w-0 items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <FileCheck class="h-5 w-5 text-primary" />
                    </div>

                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            Reports
                        </p>

                        <p class="truncate text-xs text-muted-foreground">
                            View system and academic reports
                        </p>
                    </div>
                </Link>

                <!-- Semesters -->
                <Link
                    href="/admin/semesters"
                    class="group flex min-w-0 items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <BookOpen class="h-5 w-5 text-primary" />
                    </div>

                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            Semesters
                        </p>

                        <p class="truncate text-xs text-muted-foreground">
                            Manage academic semesters
                        </p>
                    </div>
                </Link>

                <!-- Sections -->
                <Link
                    href="/sections"
                    class="group flex min-w-0 items-center gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/40"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <Layers class="h-5 w-5 text-primary" />
                    </div>

                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            Sections
                        </p>

                        <p class="truncate text-xs text-muted-foreground">
                            View and manage sections
                        </p>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>