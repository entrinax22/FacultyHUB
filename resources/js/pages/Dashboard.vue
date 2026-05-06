<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookMarked,
    CalendarDays,
    ClipboardList,
    GraduationCap,
    Layers,
    LayoutGrid,
    Plus,
    Users,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    subject: { code: string; name: string };
    semester: { name: string; school_year: string; is_active: boolean };
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
        stats: () => ({ sections: 0, students: 0, modules: 0, assignments: 0 }),
    },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Welcome + active semester -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <p v-if="activeSemester" class="text-sm text-muted-foreground">
                    Active semester:
                    <span class="font-medium text-foreground"
                        >{{ activeSemester.name }}
                        {{ activeSemester.school_year }}</span
                    >
                </p>
                <p v-else class="text-sm text-orange-500">
                    No active semester —
                    <Link href="/semesters" class="underline">set one now</Link>
                </p>
            </div>
            <Button as-child size="sm">
                <Link href="/sections/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Section
                </Link>
            </Button>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium"
                        >My Sections</CardTitle
                    >
                    <Layers class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.sections }}</p>
                    <p class="text-xs text-muted-foreground">this semester</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">Students</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.students }}</p>
                    <p class="text-xs text-muted-foreground">enrolled</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">Modules</CardTitle>
                    <BookMarked class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.modules }}</p>
                    <p class="text-xs text-muted-foreground">uploaded</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium"
                        >Assignments</CardTitle
                    >
                    <ClipboardList class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ stats.assignments }}</p>
                    <p class="text-xs text-muted-foreground">created</p>
                </CardContent>
            </Card>
        </div>

        <!-- Quick links -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                href="/semesters"
                class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40"
            >
                <CalendarDays class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Semesters</span>
            </Link>
            <Link
                href="/subjects"
                class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40"
            >
                <BookMarked class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Subjects</span>
            </Link>
            <Link
                href="/sections"
                class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40"
            >
                <Layers class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Sections</span>
            </Link>
            <Link
                href="/students"
                class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-colors hover:bg-muted/40"
            >
                <GraduationCap class="h-7 w-7 text-primary" />
                <span class="text-sm font-medium">Students</span>
            </Link>
        </div>

        <!-- My sections for this semester -->
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">
                    {{
                        activeSemester
                            ? `My Sections — ${activeSemester.name}`
                            : 'My Sections'
                    }}
                </h2>
                <Button variant="ghost" size="sm" as-child>
                    <Link href="/sections">View all</Link>
                </Button>
            </div>

            <div
                v-if="mySections.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                <Layers class="mx-auto mb-2 h-8 w-8 opacity-30" />
                <p class="text-sm">No sections yet for this semester.</p>
                <Button variant="outline" size="sm" class="mt-3" as-child>
                    <Link href="/sections/create">Create a Section</Link>
                </Button>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="section in mySections"
                    :key="section.id"
                    class="space-y-3 rounded-xl border p-4 transition-colors hover:bg-muted/20"
                >
                    <div>
                        <p class="font-semibold">{{ section.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ section.subject.code }} —
                            {{ section.subject.name }}
                        </p>
                        <p
                            v-if="section.schedule"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ section.schedule }}
                        </p>
                    </div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Users class="h-3.5 w-3.5" />
                        {{ section.enrollments_count }} student{{
                            section.enrollments_count !== 1 ? 's' : ''
                        }}
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/sections/${section.id}`"
                                >Roster</Link
                            >
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/sections/${section.id}/modules`"
                                >Modules</Link
                            >
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full"
                            as-child
                        >
                            <Link :href="`/sections/${section.id}/assignments`"
                                >Tasks</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
