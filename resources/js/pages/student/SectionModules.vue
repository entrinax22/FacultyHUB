<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle,
    Circle,
    FileText,
    ArrowLeft,
    ClipboardList,
    BarChart3,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ModuleFile = {
    id: string;
    file_name: string;
    file_type: string;
    size_formatted: string;
    url: string;
};

type Module = {
    id: string;
    section_id: string;
    title: string;
    description: string | null;
    week_number: number | null;
    is_published: boolean;
    is_read: boolean;
    files: ModuleFile[];
};

type Section = {
    id: string;
    name: string;
    schedule: string | null;
    subject: {
        id: string;
        code: string;
        name: string;
    } | null;
    semester: {
        id: string;
        name: string;
        school_year: string;
    } | null;
    faculty: {
        id: string;
        name: string;
    } | null;
};

const props = defineProps<{
    section: Section;
    modules: Module[];
    progress: {
        read: number;
        total: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'Modules',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head
        :title="
            section.subject
                ? `${section.subject.code} — ${section.name}`
                : section.name
        "
    />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ============================================================= -->
        <!-- HEADER -->
        <!-- ============================================================= -->

        <div class="flex min-w-0 items-start gap-2 sm:gap-3">

            <!-- Back -->

            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 mt-0.5 shrink-0"
            >
                <Link href="/my-sections">
                    <ArrowLeft class="h-4 w-4" />

                    <span class="sr-only">
                        Back to My Classes
                    </span>
                </Link>
            </Button>

            <div class="min-w-0 flex-1 space-y-3">

                <!-- Title + Actions -->

                <div
                    class="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                >
                    <!-- Title -->

                    <div class="min-w-0">

                        <h1
                            class="break-words text-xl font-semibold sm:text-2xl"
                        >
                            {{ section.subject?.name ?? section.name }}
                        </h1>

                        <p
                            class="mt-1 break-words text-xs text-muted-foreground sm:text-sm"
                        >
                            <span
                                v-if="section.subject"
                                class="font-medium text-foreground"
                            >
                                {{ section.subject.code }}
                            </span>

                            <span v-if="section.subject">
                                ·
                            </span>

                            {{ section.name }}

                            <template v-if="section.semester">
                                ·
                                {{ section.semester.name }}
                                {{ section.semester.school_year }}
                            </template>
                        </p>

                        <!-- Section Details -->

                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="section.schedule"
                                class="break-words"
                            >
                                {{ section.schedule }}
                            </span>

                            <span
                                v-if="section.faculty"
                                class="break-words"
                            >
                                {{ section.faculty.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->

                    <div
                        class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 lg:w-auto lg:shrink-0"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full lg:w-auto"
                            as-child
                        >
                            <Link
                                :href="
                                    `/my-sections/${section.id}/assignments`
                                "
                            >
                                <ClipboardList
                                    class="mr-1.5 h-3.5 w-3.5 shrink-0"
                                />

                                Assignments
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full lg:w-auto"
                            as-child
                        >
                            <Link
                                :href="
                                    `/my-sections/${section.id}/grades`
                                "
                            >
                                <BarChart3
                                    class="mr-1.5 h-3.5 w-3.5 shrink-0"
                                />

                                My Grades
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- PROGRESS -->
        <!-- ============================================================= -->

        <div
            v-if="progress.total > 0"
            class="space-y-2 rounded-xl border bg-card p-4 shadow-sm"
        >
            <div
                class="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="text-muted-foreground">
                    Your progress
                </span>

                <span class="font-medium">
                    {{ progress.read }} / {{ progress.total }}
                    modules read
                </span>
            </div>

            <div
                class="h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{
                        width: `${
                            progress.total > 0
                                ? (progress.read / progress.total) * 100
                                : 0
                        }%`,
                    }"
                />
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- NO MODULES -->
        <!-- ============================================================= -->

        <div
            v-if="modules.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            No modules have been published for this section yet.
        </div>

        <!-- ============================================================= -->
        <!-- MODULE LIST -->
        <!-- ============================================================= -->

        <div
            v-else
            class="min-w-0 space-y-2"
        >
            <Link
                v-for="mod in modules"
                :key="mod.id"
                :href="
                    `/my-sections/${section.id}/modules/${mod.id}`
                "
                class="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-center sm:gap-4"
            >
                <!-- ===================================================== -->
                <!-- READ INDICATOR + WEEK -->
                <!-- ===================================================== -->

                <div
                    class="flex min-w-0 items-start gap-3 sm:contents"
                >
                    <!-- Read indicator -->

                    <div class="shrink-0 pt-0.5">
                        <CheckCircle
                            v-if="mod.is_read"
                            class="h-5 w-5 text-green-500"
                        />

                        <Circle
                            v-else
                            class="h-5 w-5 text-muted-foreground/50"
                        />
                    </div>

                    <!-- Week badge -->

                    <span
                        v-if="mod.week_number"
                        class="shrink-0 rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                    >
                        Week {{ mod.week_number }}
                    </span>
                </div>

                <!-- ===================================================== -->
                <!-- TITLE + DESCRIPTION -->
                <!-- ===================================================== -->

                <div class="min-w-0 flex-1">

                    <p
                        class="break-words font-medium"
                        :class="{
                            'text-muted-foreground': mod.is_read,
                        }"
                    >
                        {{ mod.title }}
                    </p>

                    <p
                        v-if="mod.description"
                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground sm:line-clamp-1"
                    >
                        {{ mod.description }}
                    </p>

                    <!-- Files -->

                    <div
                        v-if="mod.files.length"
                        class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <FileText
                            class="h-3 w-3 shrink-0"
                        />

                        <span>
                            {{ mod.files.length }}
                            file{{ mod.files.length !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- READ BADGE -->
                <!-- ===================================================== -->

                <Badge
                    v-if="mod.is_read"
                    variant="outline"
                    class="w-fit shrink-0 border-green-200 text-green-600"
                >
                    <CheckCircle
                        class="mr-1 h-3 w-3 shrink-0"
                    />

                    Read
                </Badge>
            </Link>
        </div>
    </div>
</template>
