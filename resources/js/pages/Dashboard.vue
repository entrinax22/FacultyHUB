```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookMarked,
    CalendarDays,
    ClipboardList,
    GraduationCap,
    Layers,
    Plus,
    Users,
} from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

type Section = {
    id: number;
    name: string;
    schedule: string | null;
    enrollments_count: number;
    subject: {
        code: string;
        name: string;
    };
    semester: {
        name: string;
        school_year: string;
        is_active: boolean;
    };
};

type Semester = {
    id: number;
    name: string;
    school_year: string;
    is_active: boolean;
};

withDefaults(
    defineProps<{
        activeSemester?: Semester | null;
        mySections?: Section[];
        stats?: {
            sections: number;
            students: number;
            modules: number;
            assignments: number;
        };
    }>(),
    {
        activeSemester: null,
        mySections: () => [],
        stats: () => ({
            sections: 0,
            students: 0,
            modules: 0,
            assignments: 0,
        }),
    },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">
                    Dashboard
                </h1>

                <p
                    v-if="activeSemester"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Active semester:
                    <span class="font-medium text-foreground">
                        {{ activeSemester.name }}
                        {{ activeSemester.school_year }}
                    </span>
                </p>

                <p
                    v-else
                    class="mt-1 text-sm text-orange-500"
                >
                    No active semester —
                    <Link
                        href="/semesters"
                        class="font-medium underline underline-offset-2"
                    >
                        set one now
                    </Link>
                </p>
            </div>

            <Button
                as-child
                size="sm"
                class="w-full sm:w-auto"
            >
                <Link href="/sections/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Section
                </Link>
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">

            <!-- Sections -->
            <Card class="min-w-0">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        My Sections
                    </CardTitle>

                    <Layers
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.sections }}
                    </p>

                    <p class="text-[11px] text-muted-foreground sm:text-xs">
                        this semester
                    </p>
                </CardContent>
            </Card>

            <!-- Students -->
            <Card class="min-w-0">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Students
                    </CardTitle>

                    <Users
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.students }}
                    </p>

                    <p class="text-[11px] text-muted-foreground sm:text-xs">
                        enrolled
                    </p>
                </CardContent>
            </Card>

            <!-- Modules -->
            <Card class="min-w-0">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Modules
                    </CardTitle>

                    <BookMarked
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.modules }}
                    </p>

                    <p class="text-[11px] text-muted-foreground sm:text-xs">
                        uploaded
                    </p>
                </CardContent>
            </Card>

            <!-- Assignments -->
            <Card class="min-w-0">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 p-3 pb-2 sm:p-4 sm:pb-2"
                >
                    <CardTitle class="truncate text-xs font-medium sm:text-sm">
                        Assignments
                    </CardTitle>

                    <ClipboardList
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </CardHeader>

                <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
                    <p class="text-2xl font-bold sm:text-3xl">
                        {{ stats.assignments }}
                    </p>

                    <p class="text-[11px] text-muted-foreground sm:text-xs">
                        created
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            <Link
                href="/semesters"
                class="flex min-w-0 flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:bg-muted/40 sm:p-4"
            >
                <CalendarDays class="h-6 w-6 text-primary sm:h-7 sm:w-7" />

                <span class="truncate text-xs font-medium sm:text-sm">
                    Semesters
                </span>
            </Link>

            <Link
                href="/subjects"
                class="flex min-w-0 flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:bg-muted/40 sm:p-4"
            >
                <BookMarked class="h-6 w-6 text-primary sm:h-7 sm:w-7" />

                <span class="truncate text-xs font-medium sm:text-sm">
                    Subjects
                </span>
            </Link>

            <Link
                href="/sections"
                class="flex min-w-0 flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:bg-muted/40 sm:p-4"
            >
                <Layers class="h-6 w-6 text-primary sm:h-7 sm:w-7" />

                <span class="truncate text-xs font-medium sm:text-sm">
                    Sections
                </span>
            </Link>

            <Link
                href="/students"
                class="flex min-w-0 flex-col items-center gap-2 rounded-xl border p-3 text-center transition-colors hover:bg-muted/40 sm:p-4"
            >
                <GraduationCap class="h-6 w-6 text-primary sm:h-7 sm:w-7" />

                <span class="truncate text-xs font-medium sm:text-sm">
                    Students
                </span>
            </Link>
        </div>

        <!-- My Sections -->
        <div class="min-w-0">

            <!-- Section Header -->
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="truncate font-semibold">
                    {{
                        activeSemester
                            ? `My Sections — ${activeSemester.name}`
                            : 'My Sections'
                    }}
                </h2>

                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="w-fit"
                >
                    <Link href="/sections">
                        View all
                    </Link>
                </Button>
            </div>

            <!-- Empty State -->
            <div
                v-if="mySections.length === 0"
                class="rounded-xl border border-dashed p-6 text-center text-muted-foreground sm:p-10"
            >
                <Layers class="mx-auto mb-2 h-8 w-8 opacity-30" />

                <p class="text-sm">
                    No sections yet for this semester.
                </p>

                <Button
                    variant="outline"
                    size="sm"
                    class="mt-3 w-full sm:w-auto"
                    as-child
                >
                    <Link href="/sections/create">
                        Create a Section
                    </Link>
                </Button>
            </div>

            <!-- Section Cards -->
            <div
                v-else
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="section in mySections"
                    :key="section.id"
                    class="flex min-w-0 flex-col space-y-3 rounded-xl border p-4 transition-colors hover:bg-muted/20"
                >
                    <!-- Section Info -->
                    <div class="min-w-0">
                        <p class="truncate font-semibold">
                            {{ section.name }}
                        </p>

                        <p
                            class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
                            :title="`${section.subject.code} — ${section.subject.name}`"
                        >
                            {{ section.subject.code }} —
                            {{ section.subject.name }}
                        </p>

                        <p
                            v-if="section.schedule"
                            class="mt-1 truncate text-xs text-muted-foreground"
                        >
                            {{ section.schedule }}
                        </p>
                    </div>

                    <!-- Students -->
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Users class="h-3.5 w-3.5 shrink-0" />

                        <span>
                            {{ section.enrollments_count }}
                            student{{
                                section.enrollments_count !== 1 ? 's' : ''
                            }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="mt-auto grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/sections/${section.id}`">
                                Roster
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/sections/${section.id}/modules`">
                                Modules
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link
                                :href="`/sections/${section.id}/assignments`"
                            >
                                Tasks
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```
