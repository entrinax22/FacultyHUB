<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Search, BookOpen, CheckCircle, Users } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type SectionItem = {
    id: number;
    name: string;
    schedule: string | null;
    subject_code: string;
    subject_name: string;
    faculty_name: string;
    semester: string;
    enrollments_count: number;
    is_enrolled: boolean;
};

const props = defineProps<{
    sections: SectionItem[];
    activeSemester: { id: number; name: string; school_year: string } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Browse Sections', href: '/my-sections/browse' },
        ],
    },
});

const search = ref('');
const enrolling = ref<number | null>(null);

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.sections;
    return props.sections.filter(s =>
        s.subject_code.toLowerCase().includes(q) ||
        s.subject_name.toLowerCase().includes(q) ||
        s.name.toLowerCase().includes(q) ||
        s.faculty_name.toLowerCase().includes(q)
    );
});

function enroll(sectionId: number) {
    enrolling.value = sectionId;
    router.post(`/my-sections/${sectionId}/self-enroll`, {}, {
        onFinish: () => { enrolling.value = null; },
    });
}
</script>

<template>
    <Head title="Browse Sections" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <Search class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">Browse Sections</h1>
                <p class="text-sm text-muted-foreground">
                    <span v-if="activeSemester">{{ activeSemester.name }} {{ activeSemester.school_year }}</span>
                    <span v-else class="text-orange-500">No active semester</span>
                </p>
            </div>
        </div>

        <!-- Search -->
        <div class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input v-model="search" placeholder="Search by subject, section, or faculty…" class="pl-9" />
        </div>

        <div v-if="filtered.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No sections available for enrollment.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="section in filtered"
                :key="section.id"
                class="rounded-xl border bg-card p-4 shadow-sm space-y-3"
                :class="section.is_enrolled ? 'border-primary/30 bg-primary/5' : ''"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ section.subject_code }}</p>
                        <p class="text-sm text-muted-foreground truncate">{{ section.subject_name }}</p>
                    </div>
                    <Badge v-if="section.is_enrolled" variant="default" class="shrink-0 text-xs">
                        <CheckCircle class="mr-1 h-3 w-3" />
                        Enrolled
                    </Badge>
                </div>

                <div class="space-y-1 text-xs text-muted-foreground">
                    <p class="flex items-center gap-1.5">
                        <BookOpen class="h-3.5 w-3.5 shrink-0" />
                        {{ section.name }}
                    </p>
                    <p v-if="section.schedule">{{ section.schedule }}</p>
                    <p>{{ section.faculty_name }}</p>
                    <p class="flex items-center gap-1.5">
                        <Users class="h-3.5 w-3.5 shrink-0" />
                        {{ section.enrollments_count }} enrolled
                    </p>
                </div>

                <Button
                    v-if="!section.is_enrolled"
                    class="w-full"
                    size="sm"
                    :disabled="enrolling === section.id"
                    @click="enroll(section.id)"
                >
                    {{ enrolling === section.id ? 'Enrolling…' : 'Enroll in this Section' }}
                </Button>
                <p v-else class="text-center text-xs text-primary font-medium">
                    You are enrolled in this section
                </p>
            </div>
        </div>
    </div>
</template>
