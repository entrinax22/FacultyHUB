<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ChevronRight, GraduationCap, Clock, Star, AlertCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Enrollment = {
    id: number;
    status: string;
    semester: { name: string; school_year: string };
    section: {
        id: number;
        name: string;
        schedule: string | null;
        subject: { code: string; name: string };
        semester: { name: string; school_year: string; is_active: boolean };
    };
};

type Student = {
    id: number;
    first_name: string;
    last_name: string;
    student_no: string;
    course: string;
    year_level: number;
};

type UpcomingAssignment = {
    id: number;
    title: string;
    due_date: string;
    section_name: string;
    subject_code: string;
    section_id: number;
};

type RecentGrade = {
    raw_score: number;
    max_score: number;
    assignment_title: string | null;
    subject_code: string | null;
    section_id: number | null;
};

defineProps<{
    student: Student;
    enrollments: Enrollment[];
    upcoming: UpcomingAssignment[];
    recentGrades: RecentGrade[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
        ],
    },
});

function daysUntil(date: string): number {
    const diff = new Date(date).getTime() - Date.now();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

function dueBadgeVariant(date: string): 'destructive' | 'default' | 'secondary' {
    const d = daysUntil(date);
    if (d <= 1) return 'destructive';
    if (d <= 3) return 'default';
    return 'secondary';
}
</script>

<template>
    <Head title="My Classes" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Student welcome banner -->
        <div class="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-lg font-bold text-primary">
                {{ student.first_name[0] }}{{ student.last_name[0] }}
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ student.first_name }} {{ student.last_name }}</h1>
                <p class="text-sm text-muted-foreground">{{ student.student_no }} · {{ student.course }} · Year {{ student.year_level }}</p>
            </div>
        </div>

        <!-- Notifications row -->
        <div v-if="upcoming.length || recentGrades.length" class="grid gap-4 lg:grid-cols-2">
            <!-- Upcoming due dates -->
            <div v-if="upcoming.length" class="rounded-xl border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <Clock class="h-4 w-4 text-orange-500" />
                    <span class="font-semibold text-sm">Due Soon</span>
                    <Badge variant="secondary" class="ml-auto text-xs">{{ upcoming.length }}</Badge>
                </div>
                <div class="divide-y">
                    <div
                        v-for="a in upcoming"
                        :key="a.id"
                        class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ a.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ a.subject_code }} · {{ a.section_name }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <Badge :variant="dueBadgeVariant(a.due_date)" class="text-xs">
                                {{ daysUntil(a.due_date) === 0 ? 'Today' : daysUntil(a.due_date) === 1 ? 'Tomorrow' : `${daysUntil(a.due_date)}d` }}
                            </Badge>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ new Date(a.due_date).toLocaleDateString() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recently released grades -->
            <div v-if="recentGrades.length" class="rounded-xl border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <Star class="h-4 w-4 text-yellow-500" />
                    <span class="font-semibold text-sm">New Grades Released</span>
                    <Badge variant="secondary" class="ml-auto text-xs">{{ recentGrades.length }}</Badge>
                </div>
                <div class="divide-y">
                    <div
                        v-for="(g, i) in recentGrades"
                        :key="i"
                        class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ g.assignment_title ?? 'Grade' }}</p>
                            <p class="text-xs text-muted-foreground">{{ g.subject_code }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="font-bold">{{ g.raw_score }}</span>
                            <span class="text-muted-foreground"> / {{ g.max_score }}</span>
                            <p class="text-xs text-muted-foreground">
                                {{ ((g.raw_score / g.max_score) * 100).toFixed(0) }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrolled sections -->
        <div>
            <h2 class="mb-3 flex items-center gap-2 font-semibold">
                <GraduationCap class="h-4 w-4" />
                My Enrolled Sections
            </h2>

            <div v-if="enrollments.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <AlertCircle class="mx-auto mb-2 h-8 w-8 text-muted-foreground/40" />
                <p class="text-muted-foreground">You are not enrolled in any sections yet.</p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="enrollment in enrollments"
                    :key="enrollment.id"
                    class="hover:shadow-md transition-shadow"
                >
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between">
                            <CardTitle class="text-base">{{ enrollment.section.subject.code }}</CardTitle>
                            <Badge
                                :variant="enrollment.section.semester.is_active ? 'default' : 'secondary'"
                                class="text-xs"
                            >
                                {{ enrollment.section.semester.is_active ? 'Active' : enrollment.semester.school_year }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ enrollment.section.subject.name }}</p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-1 text-xs text-muted-foreground">
                            <p>Section: {{ enrollment.section.name }}</p>
                            <p v-if="enrollment.section.schedule">{{ enrollment.section.schedule }}</p>
                            <p>{{ enrollment.semester.name }} {{ enrollment.semester.school_year }}</p>
                        </div>
                        <div class="flex gap-2">
                            <Button class="flex-1" size="sm" as-child>
                                <Link :href="`/my-sections/${enrollment.section.id}`">
                                    <BookOpen class="mr-1.5 h-3.5 w-3.5" />
                                    Modules
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/my-sections/${enrollment.section.id}/grades`">
                                    Grades
                                    <ChevronRight class="ml-1 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
