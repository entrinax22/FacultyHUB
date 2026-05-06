<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Users, BookOpen } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Section = {
    id: number;
    name: string;
    schedule: string | null;
    room: string | null;
    enrollments_count: number;
    semester: { id: number; name: string; school_year: string; is_active: boolean };
    subject: { id: number; code: string; name: string };
    faculty: { id: number; name: string };
};

defineProps<{ sections: Section[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Sections', href: '/sections' },
        ],
    },
});

function deleteSection(id: number) {
    if (confirm('Delete this section? All enrollments will also be removed.')) {
        router.delete(`/sections/${id}`);
    }
}
</script>

<template>
    <Head title="Sections" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Sections</h1>
                <p class="text-sm text-muted-foreground">Manage class sections per semester</p>
            </div>
            <Button as-child>
                <Link href="/sections/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Section
                </Link>
            </Button>
        </div>

        <div v-if="sections.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No sections yet. Create your first section.
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="section in sections"
                :key="section.id"
                class="rounded-xl border p-4 space-y-3 hover:bg-muted/20 transition-colors"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-semibold">{{ section.name }}</h3>
                        <p class="text-sm text-muted-foreground">{{ section.subject.code }} — {{ section.subject.name }}</p>
                    </div>
                    <Badge :variant="section.semester.is_active ? 'default' : 'secondary'" class="text-xs">
                        {{ section.semester.name }}
                    </Badge>
                </div>

                <div class="space-y-1 text-xs text-muted-foreground">
                    <div v-if="section.schedule" class="flex items-center gap-1.5">
                        <BookOpen class="h-3 w-3" />
                        {{ section.schedule }}
                    </div>
                    <div v-if="section.room" class="flex items-center gap-1.5">
                        Room {{ section.room }}
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Users class="h-3 w-3" />
                        {{ section.enrollments_count }} student{{ section.enrollments_count !== 1 ? 's' : '' }}
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <Button variant="outline" size="sm" class="flex-1" as-child>
                        <Link :href="`/sections/${section.id}`">View Class</Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/sections/${section.id}/edit`">
                            <Pencil class="h-3.5 w-3.5" />
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleteSection(section.id)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
