<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    ChevronRight,
    GraduationCap,
    Clock,
    Star,
    AlertCircle,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Enrollment = {
    id: string;
    status: string;
    semester: {
        name: string;
        school_year: string;
    };
    section: {
        id: string;
        name: string;
        schedule: string | null;
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
};

type Student = {
    id: string;
    first_name: string;
    last_name: string;
    student_no: string;
    course: string;
    year_level: number;
};

type UpcomingAssignment = {
    id: string;
    title: string;
    due_date: string;
    section_name: string;
    subject_code: string;
    section_id: string;
};

type RecentGrade = {
    raw_score: number;
    max_score: number;
    assignment_title: string | null;
    subject_code: string | null;
    section_id: string | null;
};

type ActiveSemester = {
    id: string;
    name: string;
    school_year: string;
};

defineProps<{
    student: Student;
    enrollments: Enrollment[];
    upcoming: UpcomingAssignment[];
    recentGrades: RecentGrade[];
    activeSemester?: ActiveSemester | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
        ],
    },
});

function daysUntil(date: string): number {
    const diff = new Date(date).getTime() - Date.now();

    return Math.ceil(
        diff / (1000 * 60 * 60 * 24),
    );
}

function dueBadgeVariant(
    date: string,
): 'destructive' | 'default' | 'secondary' {
    const d = daysUntil(date);

    if (d <= 1) {
        return 'destructive';
    }

    if (d <= 3) {
        return 'default';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="My Classes" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ============================================================= -->
        <!-- STUDENT WELCOME -->
        <!-- ============================================================= -->

        <div
            class="flex min-w-0 items-center gap-3 rounded-xl border bg-card p-4 shadow-sm sm:gap-4 sm:p-5"
        >
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-bold text-primary sm:h-12 sm:w-12 sm:text-lg"
            >
                {{ student.first_name[0] }}{{ student.last_name[0] }}
            </div>

            <div class="min-w-0">
                <h1
                    class="truncate text-lg font-semibold sm:text-xl"
                    :title="`${student.first_name} ${student.last_name}`"
                >
                    {{ student.first_name }}
                    {{ student.last_name }}
                </h1>

                <p
                    class="mt-0.5 break-words text-xs text-muted-foreground sm:text-sm"
                >
                    {{ student.student_no }}
                    ·
                    {{ student.course }}
                    · Year {{ student.year_level }}
                </p>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- NOTIFICATIONS -->
        <!-- ============================================================= -->

        <div
            v-if="upcoming.length || recentGrades.length"
            class="grid min-w-0 gap-4 lg:grid-cols-2"
        >
            <!-- ========================================================= -->
            <!-- DUE SOON -->
            <!-- ========================================================= -->

            <div
                v-if="upcoming.length"
                class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm"
            >
                <div
                    class="flex items-center gap-2 border-b px-4 py-3"
                >
                    <Clock
                        class="h-4 w-4 shrink-0 text-orange-500"
                    />

                    <span class="min-w-0 truncate text-sm font-semibold">
                        Due Soon
                    </span>

                    <Badge
                        variant="secondary"
                        class="ml-auto shrink-0 text-xs"
                    >
                        {{ upcoming.length }}
                    </Badge>
                </div>

                <div class="divide-y">
                    <div
                        v-for="assignment in upcoming"
                        :key="assignment.id"
                        class="flex min-w-0 items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-medium"
                                :title="assignment.title"
                            >
                                {{ assignment.title }}
                            </p>

                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ assignment.subject_code }}
                                ·
                                {{ assignment.section_name }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <Badge
                                :variant="
                                    dueBadgeVariant(
                                        assignment.due_date,
                                    )
                                "
                                class="text-xs"
                            >
                                {{
                                    daysUntil(
                                        assignment.due_date,
                                    ) === 0
                                        ? 'Today'
                                        : daysUntil(
                                              assignment.due_date,
                                          ) === 1
                                          ? 'Tomorrow'
                                          : `${daysUntil(
                                                assignment.due_date,
                                            )}d`
                                }}
                            </Badge>

                            <p
                                class="mt-0.5 text-[11px] text-muted-foreground sm:text-xs"
                            >
                                {{
                                    new Date(
                                        assignment.due_date,
                                    ).toLocaleDateString()
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- RECENT GRADES -->
            <!-- ========================================================= -->

            <div
                v-if="recentGrades.length"
                class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm"
            >
                <div
                    class="flex items-center gap-2 border-b px-4 py-3"
                >
                    <Star
                        class="h-4 w-4 shrink-0 text-yellow-500"
                    />

                    <span class="min-w-0 truncate text-sm font-semibold">
                        New Grades Released
                    </span>

                    <Badge
                        variant="secondary"
                        class="ml-auto shrink-0 text-xs"
                    >
                        {{ recentGrades.length }}
                    </Badge>
                </div>

                <div class="divide-y">
                    <div
                        v-for="(grade, index) in recentGrades"
                        :key="index"
                        class="flex min-w-0 items-center justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-medium"
                                :title="
                                    grade.assignment_title ??
                                    'Grade'
                                "
                            >
                                {{
                                    grade.assignment_title ??
                                    'Grade'
                                }}
                            </p>

                            <p
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ grade.subject_code }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <div>
                                <span class="font-bold">
                                    {{ grade.raw_score }}
                                </span>

                                <span class="text-muted-foreground">
                                    /
                                    {{ grade.max_score }}
                                </span>
                            </div>

                            <p
                                class="text-[11px] text-muted-foreground sm:text-xs"
                            >
                                {{
                                    (
                                        (grade.raw_score /
                                            grade.max_score) *
                                        100
                                    ).toFixed(0)
                                }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- ENROLLED SECTIONS -->
        <!-- ============================================================= -->

        <div class="min-w-0">
            <h2
                class="mb-3 flex items-center gap-2 font-semibold"
            >
                <GraduationCap class="h-4 w-4 shrink-0" />

                My Enrolled Sections
            </h2>

            <!-- Empty -->
            <div
                v-if="enrollments.length === 0"
                class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center"
            >
                <AlertCircle
                    class="mb-2 h-8 w-8 text-muted-foreground/40"
                />

                <p class="text-sm text-muted-foreground">
                    You are not enrolled in any sections yet.
                </p>
            </div>

            <!-- Section Cards -->
            <div
                v-else
                class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3"
            >
                <Card
                    v-for="enrollment in enrollments"
                    :key="enrollment.id"
                    class="min-w-0 overflow-hidden transition-shadow hover:shadow-md"
                >
                    <!-- Card Header -->
                    <CardHeader class="pb-2">
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <CardTitle
                                class="min-w-0 truncate text-base"
                                :title="
                                    enrollment.section.subject.code
                                "
                            >
                                {{
                                    enrollment.section.subject.code
                                }}
                            </CardTitle>

                            <Badge
                                :variant="
                                    enrollment.section.semester
                                        .is_active
                                        ? 'default'
                                        : 'secondary'
                                "
                                class="shrink-0 text-xs"
                            >
                                {{
                                    enrollment.section.semester
                                        .is_active
                                        ? 'Active'
                                        : enrollment.semester
                                              .school_year
                                }}
                            </Badge>
                        </div>

                        <p
                            class="mt-1 break-words text-sm text-muted-foreground"
                        >
                            {{ enrollment.section.subject.name }}
                        </p>
                    </CardHeader>

                    <!-- Card Content -->
                    <CardContent class="space-y-4">
                        <div
                            class="space-y-2 text-xs text-muted-foreground"
                        >
                            <!-- Section -->
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <span class="shrink-0">
                                    Section
                                </span>

                                <span
                                    class="break-words text-right font-medium text-foreground"
                                >
                                    {{
                                        enrollment.section.name
                                    }}
                                </span>
                            </div>

                            <!-- Schedule -->
                            <div
                                v-if="enrollment.section.schedule"
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <span class="shrink-0">
                                    Schedule
                                </span>

                                <span
                                    class="break-words text-right font-medium text-foreground"
                                >
                                    {{
                                        enrollment.section.schedule
                                    }}
                                </span>
                            </div>

                            <!-- Semester -->
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <span class="shrink-0">
                                    Semester
                                </span>

                                <span
                                    class="break-words text-right font-medium text-foreground"
                                >
                                    {{
                                        enrollment.semester.name
                                    }}
                                    {{
                                        enrollment.semester
                                            .school_year
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div
                            class="grid grid-cols-1 gap-2 border-t pt-4 sm:grid-cols-2"
                        >
                            <Button
                                class="w-full"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="`/my-sections/${enrollment.section.id}`"
                                >
                                    <BookOpen
                                        class="mr-1.5 h-3.5 w-3.5 shrink-0"
                                    />

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
                                    :href="`/my-sections/${enrollment.section.id}/grades`"
                                >
                                    Grades

                                    <ChevronRight
                                        class="ml-1 h-3.5 w-3.5 shrink-0"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
