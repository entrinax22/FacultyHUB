<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import {
    Pencil,
    BookOpen,
    GraduationCap,
} from 'lucide-vue-next';

type Enrollment = {
    id: string;
    status: string;

    semester: {
        id: string;
        name: string;
        school_year: string;
    };

    section: {
        id: string;
        name: string;
        schedule: string | null;

        subject: {
            id: string;
            code: string;
            name: string;
        };
    };
};

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
    enrollments: Enrollment[];
};

const props = defineProps<{
    student: Student;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Students',
                href: '/students',
            },
        ],
    },
});
</script>

<template>
    <Head
        :title="`${props.student.first_name} ${props.student.last_name}`"
    />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">

                <!-- Initials -->
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-muted text-xl font-bold"
                >
                    {{ props.student.first_name?.[0] }}
                    {{ props.student.last_name?.[0] }}
                </div>

                <!-- Student Name -->
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ props.student.first_name }}
                        {{ props.student.last_name }}
                    </h1>

                    <p class="font-mono text-sm text-muted-foreground">
                        {{ props.student.student_no }}
                    </p>
                </div>
            </div>

            <!-- Edit -->
            <Button
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="`/students/edit/${props.student.id}`"
                >
                    <Pencil class="mr-1.5 h-3.5 w-3.5" />
                    Edit
                </Link>
            </Button>
        </div>

        <!-- Student Information -->
        <div class="grid gap-4 sm:grid-cols-3">

            <!-- Email -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Email
                    </CardTitle>
                </CardHeader>

                <CardContent>
                    {{ props.student.email }}
                </CardContent>
            </Card>

            <!-- Course -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Course
                    </CardTitle>
                </CardHeader>

                <CardContent>
                    {{ props.student.course }}
                </CardContent>
            </Card>

            <!-- Year Level -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Year Level
                    </CardTitle>
                </CardHeader>

                <CardContent>
                    Year {{ props.student.year_level }}
                </CardContent>
            </Card>

        </div>

        <!-- Enrollment History -->
        <div class="space-y-3">

            <h2 class="flex items-center gap-2 font-semibold">
                <GraduationCap class="h-4 w-4" />
                Enrollment History
            </h2>

            <!-- Empty -->
            <div
                v-if="props.student.enrollments.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
            >
                No enrollment records yet.
            </div>

            <!-- Enrollment Cards -->
            <div
                v-else
                class="grid gap-3 md:grid-cols-2"
            >
                <div
                    v-for="enrollment in props.student.enrollments"
                    :key="enrollment.id"
                    class="space-y-2 rounded-xl border p-4"
                >

                    <!-- Subject -->
                    <div
                        class="flex items-start justify-between"
                    >
                        <div>
                            <p class="font-medium">
                                {{ enrollment.section.subject.code }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                {{ enrollment.section.subject.name }}
                            </p>
                        </div>

                        <!-- Status -->
                        <Badge
                            :variant="
                                enrollment.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            "
                            class="text-xs"
                        >
                            {{ enrollment.status }}
                        </Badge>
                    </div>

                    <!-- Details -->
                    <div
                        class="space-y-0.5 text-xs text-muted-foreground"
                    >
                        <p>
                            Section:
                            {{ enrollment.section.name }}
                        </p>

                        <p>
                            {{ enrollment.semester.name }}
                            {{ enrollment.semester.school_year }}
                        </p>

                        <p
                            v-if="enrollment.section.schedule"
                        >
                            {{ enrollment.section.schedule }}
                        </p>
                    </div>

                    <!-- View Class -->
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        class="w-full"
                    >
                        <Link
                            :href="`/sections/${enrollment.section.id}`"
                        >
                            <BookOpen
                                class="mr-1.5 h-3.5 w-3.5"
                            />
                            View Class
                        </Link>
                    </Button>

                </div>
            </div>

        </div>
    </div>
</template>