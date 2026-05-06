<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle, Circle, FileText, ArrowLeft, BookOpen, ClipboardList, BarChart3 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ModuleFile = {
    id: number;
    file_name: string;
    file_type: string;
    size_formatted: string;
};

type Module = {
    id: number;
    title: string;
    description: string | null;
    week_number: number | null;
    order: number;
    files: ModuleFile[];
    is_read: boolean;
};

type Section = {
    id: number;
    name: string;
    schedule: string | null;
    room: string | null;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
    faculty: { name: string };
};

defineProps<{
    section: Section;
    modules: Module[];
    progress: { read: number; total: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`${section.subject.code} — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link href="/my-sections">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div class="flex-1 space-y-1">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="text-xl font-semibold">{{ section.subject.name }}</h1>
                    <div class="flex gap-2 shrink-0">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/my-sections/${section.id}/assignments`">
                                <ClipboardList class="mr-1.5 h-3.5 w-3.5" />
                                Assignments
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/my-sections/${section.id}/grades`">
                                <BarChart3 class="mr-1.5 h-3.5 w-3.5" />
                                My Grades
                            </Link>
                        </Button>
                    </div>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }} {{ section.semester.school_year }}
                </p>
                <div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                    <span v-if="section.schedule">{{ section.schedule }}</span>
                    <span v-if="section.room">{{ section.room }}</span>
                    <span>{{ section.faculty.name }}</span>
                </div>
            </div>
        </div>

        <!-- Progress bar -->
        <div v-if="progress.total > 0" class="space-y-1.5">
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Your progress</span>
                <span class="font-medium">{{ progress.read }} / {{ progress.total }} modules read</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${progress.total > 0 ? (progress.read / progress.total) * 100 : 0}%` }"
                />
            </div>
        </div>

        <!-- Module list -->
        <div v-if="modules.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No modules have been published for this section yet.
        </div>

        <div v-else class="space-y-2">
            <Link
                v-for="mod in modules"
                :key="mod.id"
                :href="`/my-sections/${section.id}/modules/${mod.id}`"
                class="flex items-center gap-4 rounded-xl border p-4 hover:bg-muted/30 transition-colors"
            >
                <!-- Read indicator -->
                <div class="shrink-0">
                    <CheckCircle v-if="mod.is_read" class="h-5 w-5 text-green-500" />
                    <Circle v-else class="h-5 w-5 text-muted-foreground/50" />
                </div>

                <!-- Week badge -->
                <span v-if="mod.week_number" class="shrink-0 rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                    Week {{ mod.week_number }}
                </span>

                <!-- Title + files count -->
                <div class="min-w-0 flex-1">
                    <p class="font-medium" :class="{ 'text-muted-foreground': mod.is_read }">{{ mod.title }}</p>
                    <p v-if="mod.description" class="line-clamp-1 text-xs text-muted-foreground">{{ mod.description }}</p>
                    <div v-if="mod.files.length" class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                        <FileText class="h-3 w-3" />
                        {{ mod.files.length }} file{{ mod.files.length !== 1 ? 's' : '' }}
                    </div>
                </div>

                <Badge v-if="mod.is_read" variant="outline" class="shrink-0 text-green-600 border-green-200">Read</Badge>
            </Link>
        </div>
    </div>
</template>
